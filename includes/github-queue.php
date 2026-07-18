<?php
/**
 * GitHub integration: queue + config only. Agent-gated by design.
 *
 * The plugin stores each report's target repo and a queue of approved,
 * signed-off sections flagged ready for GitHub (queued → pushed, with the
 * resulting issue URL/number stamped back). It never calls the GitHub API
 * and holds no credential — the push lives in the agent layer, which reads
 * the queue over REST and writes results back. Not real-time by design:
 * issues appear on the next agent run.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta auth callback for human-only (dev/PM) settings.
 *
 * @return bool
 */
function pppd_human_meta_auth_callback() {
	return current_user_can( 'pppd_approve_changes' );
}

/**
 * Sanitize an owner/name GitHub repo reference ('' when malformed).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function pppd_sanitize_github_repo( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	return ( 1 === preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $value ) ) ? $value : '';
}

/**
 * Register GitHub queue/config meta.
 *
 * Report config is writable by humans only (never the agent role); per-item
 * queue state is read-only over REST — it moves exclusively through
 * pppd_queue_section_for_github() and the mark-pushed endpoint.
 *
 * @return void
 */
function pppd_register_github_meta() {
	$human_only = array(
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => 'pppd_human_meta_auth_callback',
	);

	register_post_meta(
		'pppd_report',
		'_pppd_github_repo',
		array_merge(
			$human_only,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'pppd_sanitize_github_repo',
			)
		)
	);

	// 'manual' (default): a dev/PM queues approved items explicitly.
	// 'auto': client sign-off queues the item automatically.
	register_post_meta(
		'pppd_report',
		'_pppd_github_trigger',
		array_merge(
			$human_only,
			array(
				'type'              => 'string',
				'default'           => 'manual',
				'sanitize_callback' => static function ( $value ) {
					return in_array( $value, array( 'manual', 'auto' ), true ) ? $value : 'manual';
				},
			)
		)
	);

	$readonly = array(
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => '__return_false',
	);

	foreach ( array(
		'_pppd_github_status'       => 'sanitize_key',
		'_pppd_github_issue_url'    => 'esc_url_raw',
		'_pppd_github_queued_at'    => 'sanitize_text_field',
		'_pppd_github_pushed_at'    => 'sanitize_text_field',
	) as $key => $sanitize ) {
		register_post_meta(
			'pppd_section',
			$key,
			array_merge(
				$readonly,
				array(
					'type'              => 'string',
					'sanitize_callback' => $sanitize,
				)
			)
		);
	}

	foreach ( array( '_pppd_github_issue_number', '_pppd_github_queued_by' ) as $key ) {
		register_post_meta(
			'pppd_section',
			$key,
			array_merge(
				$readonly,
				array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				)
			)
		);
	}
}

/**
 * Hook up the queue handlers.
 *
 * @return void
 */
function pppd_github_queue_init() {
	add_action( 'admin_post_pppd_queue_github', 'pppd_handle_queue_github' );
	add_action( 'pppd_section_approved', 'pppd_maybe_auto_queue_github', 10, 2 );
}

/**
 * Whether a section is eligible for the GitHub queue right now.
 *
 * Eligible = currently signed off (approved, not stale), report has a target
 * repo, and the item has never been queued or pushed (never double-push).
 *
 * @param int $section_id Section post ID.
 * @return true|WP_Error
 */
function pppd_github_queue_eligibility( $section_id ) {
	$section_id = absint( $section_id );
	$report_id  = absint( get_post_meta( $section_id, '_pppd_report_id', true ) );

	if ( 0 === $report_id ) {
		return new WP_Error( 'pppd_no_report', __( 'The section is not attached to a report.', 'pretty-professional-process-docs' ), array( 'status' => 409 ) );
	}

	if ( '' === (string) get_post_meta( $report_id, '_pppd_github_repo', true ) ) {
		return new WP_Error( 'pppd_no_repo', __( 'The report has no target GitHub repository configured.', 'pretty-professional-process-docs' ), array( 'status' => 409 ) );
	}

	$signoff = pppd_get_section_signoff( $section_id );

	if ( 'approved' !== $signoff['state'] ) {
		return new WP_Error( 'pppd_not_approved', __( 'Only sections with a current sign-off can be queued for GitHub.', 'pretty-professional-process-docs' ), array( 'status' => 409 ) );
	}

	if ( '' !== (string) get_post_meta( $section_id, '_pppd_github_status', true ) ) {
		return new WP_Error( 'pppd_already_queued', __( 'This section is already queued or pushed.', 'pretty-professional-process-docs' ), array( 'status' => 409 ) );
	}

	return true;
}

/**
 * Flag a section as ready for GitHub.
 *
 * Does NOT check capabilities — callers gate access.
 *
 * @param int $section_id Section post ID.
 * @param int $user_id    Queuing user ID (0 for the auto trigger).
 * @return true|WP_Error
 */
function pppd_queue_section_for_github( $section_id, $user_id = 0 ) {
	$eligible = pppd_github_queue_eligibility( $section_id );

	if ( is_wp_error( $eligible ) ) {
		return $eligible;
	}

	update_post_meta( $section_id, '_pppd_github_status', 'queued' );
	update_post_meta( $section_id, '_pppd_github_queued_at', current_time( 'mysql', true ) );
	update_post_meta( $section_id, '_pppd_github_queued_by', absint( $user_id ) );

	return true;
}

/**
 * Handle the dev/PM "queue for GitHub" form (admin-post.php).
 *
 * @return void
 */
function pppd_handle_queue_github() {
	if ( ! current_user_can( 'pppd_approve_changes' ) ) {
		wp_die( esc_html__( 'Queuing for GitHub is limited to the project team.', 'pretty-professional-process-docs' ), '', array( 'response' => 403 ) );
	}

	$section_id = isset( $_POST['pppd_section'] ) ? absint( wp_unslash( $_POST['pppd_section'] ) ) : 0;

	if ( ! isset( $_POST['_pppd_github_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_pppd_github_nonce'] ) ), 'pppd_github_' . $section_id ) ) {
		wp_die( esc_html__( 'The request could not be verified — please go back and try again.', 'pretty-professional-process-docs' ), '', array( 'response' => 403 ) );
	}

	$result = pppd_queue_section_for_github( $section_id, get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ) );
	}

	$report_id = absint( get_post_meta( $section_id, '_pppd_report_id', true ) );
	$section   = get_post( $section_id );

	wp_safe_redirect( get_permalink( $report_id ) . '#' . pppd_section_anchor( $section ) );
	exit;
}

/**
 * Auto-queue on client sign-off when the report opts in.
 *
 * @param int $section_id Section post ID.
 * @param int $user_id    Approving user ID.
 * @return void
 */
function pppd_maybe_auto_queue_github( $section_id, $user_id ) {
	$report_id = absint( get_post_meta( $section_id, '_pppd_report_id', true ) );

	if ( 0 === $report_id || 'auto' !== (string) get_post_meta( $report_id, '_pppd_github_trigger', true ) ) {
		return;
	}

	$result = pppd_queue_section_for_github( $section_id, absint( $user_id ) );

	// Ineligibility (no repo, already pushed) is fine here — auto mode just
	// queues what it can.
	unset( $result );
}

/**
 * Build the queue item payload the agent turns into a GitHub issue.
 *
 * @param WP_Post $section Queued section post.
 * @return array<string, mixed>
 */
function pppd_build_github_queue_item( $section ) {
	$report_id  = absint( get_post_meta( $section->ID, '_pppd_report_id', true ) );
	$acceptance = get_post_meta( $section->ID, '_pppd_acceptance', true );
	$signoff    = pppd_get_section_signoff( $section );
	$req_id     = (string) get_post_meta( $section->ID, '_pppd_req_id', true );

	return array(
		'section_id' => (int) $section->ID,
		'report_id'  => $report_id,
		'repo'       => (string) get_post_meta( $report_id, '_pppd_github_repo', true ),
		'req_id'     => $req_id,
		'title'      => trim( ( '' !== $req_id ? $req_id . ': ' : '' ) . get_the_title( $section ) ),
		// Block-authored sections are rendered to plain HTML so issue bodies
		// never carry <!-- wp:* --> serialization comments; raw-HTML sections
		// pass through unchanged.
		'content'    => has_blocks( $section->post_content ) ? trim( do_blocks( $section->post_content ) ) : (string) $section->post_content,
		'acceptance' => is_array( $acceptance ) ? $acceptance : array(),
		'labels'     => array_values(
			array_filter(
				array(
					pppd_get_section_type_slug( $section ),
					pppd_get_report_type( $report_id ),
					(string) get_post_meta( $report_id, '_pppd_project_slug', true ),
				)
			)
		),
		'approved'   => array(
			'by' => (int) $signoff['by'],
			'at' => (string) $signoff['at'],
		),
		'queued_at'  => (string) get_post_meta( $section->ID, '_pppd_github_queued_at', true ),
		'report'     => get_the_title( $report_id ),
		'permalink'  => get_permalink( $report_id ) . '#' . pppd_section_anchor( $section ),
	);
}

/**
 * All currently queued (not yet pushed) sections, oldest first.
 *
 * @param int $report_id Optional report filter.
 * @return WP_Post[]
 */
function pppd_get_github_queue( $report_id = 0 ) {
	$args = array(
		'post_type'      => 'pppd_section',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'modified',
		'order'          => 'ASC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_pppd_github_status',
				'value' => 'queued',
			),
		),
	);

	if ( $report_id > 0 ) {
		$args['meta_query'][] = array(
			'key'   => '_pppd_report_id',
			'value' => (string) absint( $report_id ),
		);
	}

	$query = new WP_Query( $args );

	return $query->posts;
}

/**
 * Mark a queued section pushed, stamping the resulting issue.
 *
 * Idempotent: only queued items transition; a second call 409s so an agent
 * can never double-push.
 *
 * @param int    $section_id   Section post ID.
 * @param string $issue_url    GitHub issue URL.
 * @param int    $issue_number GitHub issue number.
 * @return array<string, mixed>|WP_Error The updated item payload.
 */
function pppd_mark_github_pushed( $section_id, $issue_url, $issue_number ) {
	$section = get_post( absint( $section_id ) );

	if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
		return new WP_Error( 'pppd_section_not_found', __( 'Section not found.', 'pretty-professional-process-docs' ), array( 'status' => 404 ) );
	}

	if ( 'queued' !== (string) get_post_meta( $section->ID, '_pppd_github_status', true ) ) {
		return new WP_Error( 'pppd_not_queued', __( 'Only queued sections can be marked pushed.', 'pretty-professional-process-docs' ), array( 'status' => 409 ) );
	}

	$issue_url = esc_url_raw( (string) $issue_url );

	if ( '' === $issue_url ) {
		return new WP_Error( 'pppd_bad_issue_url', __( 'A valid issue URL is required.', 'pretty-professional-process-docs' ), array( 'status' => 400 ) );
	}

	// GitHub issue numbers start at 1; recording 0 would leave the section in
	// an unusable pushed state (and, being idempotent, unrepairable via REST).
	$issue_number = absint( $issue_number );

	if ( $issue_number < 1 ) {
		return new WP_Error( 'pppd_bad_issue_number', __( 'A positive issue number is required.', 'pretty-professional-process-docs' ), array( 'status' => 400 ) );
	}

	update_post_meta( $section->ID, '_pppd_github_status', 'pushed' );
	update_post_meta( $section->ID, '_pppd_github_issue_url', $issue_url );
	update_post_meta( $section->ID, '_pppd_github_issue_number', $issue_number );
	update_post_meta( $section->ID, '_pppd_github_pushed_at', current_time( 'mysql', true ) );

	$item                 = pppd_build_github_queue_item( $section );
	$item['status']       = 'pushed';
	$item['issue_url']    = $issue_url;
	$item['issue_number'] = $issue_number;

	return $item;
}
