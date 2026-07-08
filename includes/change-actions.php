<?php
/**
 * Shared approve/reject logic for proposed changes.
 *
 * Single source of truth used by both the REST changes controller and the
 * admin Review Queue handlers.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a summary array for a change post.
 *
 * @param WP_Post $change Change post.
 * @return array<string, mixed>
 */
function pppd_get_change_summary( $change ) {
	return array(
		'id'               => (int) $change->ID,
		'title'            => get_the_title( $change ),
		'status'           => (string) $change->post_status,
		'target_section'   => absint( get_post_meta( $change->ID, '_pppd_target_section', true ) ),
		'source'           => (string) get_post_meta( $change->ID, '_pppd_source', true ),
		'rationale'        => (string) get_post_meta( $change->ID, '_pppd_rationale', true ),
		'reject_reason'    => (string) get_post_meta( $change->ID, '_pppd_reject_reason', true ),
		'applied_revision' => absint( get_post_meta( $change->ID, '_pppd_applied_revision', true ) ),
		'reviewed_by'      => absint( get_post_meta( $change->ID, '_pppd_reviewed_by', true ) ),
	);
}

/**
 * Approve a pending change: copy its content onto the target section
 * (creating a revision), publish the change, and stamp audit meta.
 *
 * @param int $change_id Change post ID.
 * @param int $user_id   Reviewing user ID. Defaults to the current user.
 * @return array<string, mixed>|WP_Error Change summary on success.
 */
function pppd_approve_change( $change_id, $user_id = 0 ) {
	$change = get_post( absint( $change_id ) );

	if ( ! $change instanceof WP_Post || 'pppd_change' !== $change->post_type ) {
		return new WP_Error(
			'pppd_change_not_found',
			__( 'Proposed change not found.', 'pretty-professional-process-docs' ),
			array( 'status' => 404 )
		);
	}

	if ( 'pending' !== $change->post_status ) {
		return new WP_Error(
			'pppd_change_not_pending',
			__( 'Only pending changes can be approved.', 'pretty-professional-process-docs' ),
			array( 'status' => 409 )
		);
	}

	$section_id = absint( get_post_meta( $change->ID, '_pppd_target_section', true ) );
	$section    = ( $section_id > 0 ) ? get_post( $section_id ) : null;

	if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
		return new WP_Error(
			'pppd_target_not_found',
			__( 'The target section for this change no longer exists.', 'pretty-professional-process-docs' ),
			array( 'status' => 404 )
		);
	}

	$report_id = absint( get_post_meta( $section->ID, '_pppd_report_id', true ) );
	$report    = ( $report_id > 0 ) ? get_post( $report_id ) : null;

	if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
		return new WP_Error(
			'pppd_report_not_found',
			__( 'The target section is not attached to a valid report.', 'pretty-professional-process-docs' ),
			array( 'status' => 409 )
		);
	}

	// Copy the proposed content onto the section; WordPress records a revision.
	$updated = wp_update_post(
		array(
			'ID'           => $section->ID,
			'post_content' => $change->post_content,
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		$updated->add_data( array( 'status' => 500 ) );

		return $updated;
	}

	$published = wp_update_post(
		array(
			'ID'          => $change->ID,
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $published ) ) {
		$published->add_data( array( 'status' => 500 ) );

		return $published;
	}

	$revisions = wp_get_post_revisions( $section->ID, array( 'posts_per_page' => 1 ) );
	$latest    = empty( $revisions ) ? null : array_shift( $revisions );

	if ( $latest instanceof WP_Post ) {
		update_post_meta( $change->ID, '_pppd_applied_revision', (int) $latest->ID );
	}

	$user_id = ( $user_id > 0 ) ? absint( $user_id ) : get_current_user_id();
	update_post_meta( $change->ID, '_pppd_reviewed_by', $user_id );

	return pppd_get_change_summary( get_post( $change->ID ) );
}

/**
 * Reject a pending change with an optional reason.
 *
 * @param int    $change_id Change post ID.
 * @param string $reason    Optional rejection reason.
 * @param int    $user_id   Reviewing user ID. Defaults to the current user.
 * @return array<string, mixed>|WP_Error Change summary on success.
 */
function pppd_reject_change( $change_id, $reason = '', $user_id = 0 ) {
	$change = get_post( absint( $change_id ) );

	if ( ! $change instanceof WP_Post || 'pppd_change' !== $change->post_type ) {
		return new WP_Error(
			'pppd_change_not_found',
			__( 'Proposed change not found.', 'pretty-professional-process-docs' ),
			array( 'status' => 404 )
		);
	}

	if ( 'pending' !== $change->post_status ) {
		return new WP_Error(
			'pppd_change_not_pending',
			__( 'Only pending changes can be rejected.', 'pretty-professional-process-docs' ),
			array( 'status' => 409 )
		);
	}

	$rejected = wp_update_post(
		array(
			'ID'          => $change->ID,
			'post_status' => 'pppd_rejected',
		),
		true
	);

	if ( is_wp_error( $rejected ) ) {
		$rejected->add_data( array( 'status' => 500 ) );

		return $rejected;
	}

	update_post_meta( $change->ID, '_pppd_reject_reason', sanitize_text_field( (string) $reason ) );

	$user_id = ( $user_id > 0 ) ? absint( $user_id ) : get_current_user_id();
	update_post_meta( $change->ID, '_pppd_reviewed_by', $user_id );

	return pppd_get_change_summary( get_post( $change->ID ) );
}
