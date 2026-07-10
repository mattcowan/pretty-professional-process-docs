<?php
/**
 * Per-section client sign-off — the legal approval record.
 *
 * A sign-off stamps who approved a section, when (GMT), and at which
 * revision. Nothing may silently invalidate it: sign-off meta is read-only
 * over REST (auth_callback returns false; only this module writes it), and
 * any later content change — including a human-approved pppd_change — flips
 * the section to "changed since approval — re-approval required" with
 * provenance, computed from the immutable timestamps rather than a mutable
 * flag.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register sign-off meta (REST-readable, never REST-writable) and handlers.
 *
 * @return void
 */
function pppd_register_signoff_meta() {
	$readonly = array(
		'single'        => true,
		'show_in_rest'  => true,
		// The sign-off record is stamped exclusively by pppd_sign_off_section().
		'auth_callback' => '__return_false',
	);

	register_post_meta(
		'pppd_section',
		'_pppd_approved_by',
		array_merge(
			$readonly,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		)
	);

	register_post_meta(
		'pppd_section',
		'_pppd_approved_at',
		array_merge(
			$readonly,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		)
	);

	register_post_meta(
		'pppd_section',
		'_pppd_approved_revision',
		array_merge(
			$readonly,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		)
	);

	// Provenance of the edit that invalidated a sign-off: the pppd_change ID
	// (0 = a direct edit outside the change workflow).
	register_post_meta(
		'pppd_section',
		'_pppd_reapproval_source',
		array_merge(
			$readonly,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		)
	);
}

/**
 * Hook up the sign-off action handler and change-approval provenance.
 *
 * @return void
 */
function pppd_signoff_init() {
	add_action( 'admin_post_pppd_approve_section', 'pppd_handle_section_approval' );
	add_action( 'pppd_change_approved', 'pppd_record_reapproval_source', 10, 4 );
}

/**
 * The sign-off state of a section.
 *
 * @param WP_Post|int $section Section post or ID.
 * @return array{state: string, by: int, at: string, revision: int, source: int}
 *         state: 'none' (never approved), 'approved' (current), or 'stale'
 *         (content changed after approval — re-approval required).
 */
function pppd_get_section_signoff( $section ) {
	$section = get_post( $section );

	$record = array(
		'state'    => 'none',
		'by'       => 0,
		'at'       => '',
		'revision' => 0,
		'source'   => 0,
	);

	if ( ! $section instanceof WP_Post ) {
		return $record;
	}

	$approved_at = (string) get_post_meta( $section->ID, '_pppd_approved_at', true );

	if ( '' === $approved_at ) {
		return $record;
	}

	$record['by']       = absint( get_post_meta( $section->ID, '_pppd_approved_by', true ) );
	$record['at']       = $approved_at;
	$record['revision'] = absint( get_post_meta( $section->ID, '_pppd_approved_revision', true ) );
	$record['source']   = absint( get_post_meta( $section->ID, '_pppd_reapproval_source', true ) );
	$record['state']    = ( $section->post_modified_gmt > $approved_at ) ? 'stale' : 'approved';

	return $record;
}

/**
 * Record a sign-off on a section as a given user.
 *
 * Shared by the frontend handler and (later) any other approval surface.
 * Does NOT check capabilities — callers gate access.
 *
 * @param int $section_id Section post ID.
 * @param int $user_id    Approving user ID.
 * @return array<string, mixed>|WP_Error The new sign-off record.
 */
function pppd_sign_off_section( $section_id, $user_id ) {
	$section = get_post( absint( $section_id ) );

	if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
		return new WP_Error( 'pppd_section_not_found', __( 'Section not found.', 'pretty-professional-process-docs' ), array( 'status' => 404 ) );
	}

	if ( 'publish' !== $section->post_status ) {
		return new WP_Error( 'pppd_not_published', __( 'Only published sections can be approved.', 'pretty-professional-process-docs' ), array( 'status' => 409 ) );
	}

	$revisions = wp_get_post_revisions( $section->ID, array( 'posts_per_page' => 1 ) );
	$latest    = empty( $revisions ) ? null : array_shift( $revisions );

	update_post_meta( $section->ID, '_pppd_approved_by', absint( $user_id ) );
	update_post_meta( $section->ID, '_pppd_approved_at', current_time( 'mysql', true ) );
	update_post_meta( $section->ID, '_pppd_approved_revision', ( $latest instanceof WP_Post ) ? (int) $latest->ID : 0 );
	delete_post_meta( $section->ID, '_pppd_reapproval_source' );

	/**
	 * Fires after a section is signed off.
	 *
	 * @param int     $section_id Section post ID.
	 * @param int     $user_id    Approving user ID.
	 * @param WP_Post $section    Section post.
	 */
	do_action( 'pppd_section_approved', (int) $section->ID, absint( $user_id ), $section );

	return pppd_get_section_signoff( $section );
}

/**
 * Handle the frontend per-section approve form (admin-post.php).
 *
 * Any logged-in user who can VIEW the report may sign off — clients approve
 * their own sections; they still can never edit content (comment ≠ edit).
 *
 * @return void
 */
function pppd_handle_section_approval() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}

	$section_id = isset( $_POST['pppd_section'] ) ? absint( wp_unslash( $_POST['pppd_section'] ) ) : 0;

	if ( ! isset( $_POST['_pppd_signoff_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_pppd_signoff_nonce'] ) ), 'pppd_signoff_' . $section_id ) ) {
		wp_die( esc_html__( 'Your approval could not be verified — please go back and try again.', 'pretty-professional-process-docs' ), '', array( 'response' => 403 ) );
	}

	$report_id = absint( get_post_meta( $section_id, '_pppd_report_id', true ) );

	if ( 0 === $report_id || ! pppd_user_can_view_report( get_current_user_id(), $report_id ) ) {
		wp_die( esc_html__( 'You do not have access to this report.', 'pretty-professional-process-docs' ), '', array( 'response' => 403 ) );
	}

	$result = pppd_sign_off_section( $section_id, get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ) );
	}

	$section  = get_post( $section_id );
	$redirect = get_permalink( $report_id ) . '#' . pppd_section_anchor( $section );

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * When an approved change lands on a signed-off section, record which change
 * invalidated the sign-off (the staleness itself falls out of the timestamp
 * comparison in pppd_get_section_signoff()).
 *
 * @param int $change_id   Change post ID.
 * @param int $section_id  Target section post ID.
 * @param int $revision_id Revision created by the applied edit.
 * @param int $user_id     Reviewing user ID.
 * @return void
 */
function pppd_record_reapproval_source( $change_id, $section_id, $revision_id, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$approved_at = (string) get_post_meta( $section_id, '_pppd_approved_at', true );

	if ( '' === $approved_at ) {
		return;
	}

	update_post_meta( $section_id, '_pppd_reapproval_source', absint( $change_id ) );
}
