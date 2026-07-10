<?php
/**
 * WordPress Abilities API integration (WP 6.9+/7.0).
 *
 * The plugin's agent-facing operations are registered as typed,
 * schema-validated, permission-gated abilities — discoverable from PHP/JS/
 * REST and, opt-in, over MCP. They COMPOSE WITH the pppd/v1 REST verbs the
 * frd skill depends on; nothing is replaced.
 *
 * MCP posture is DEFAULT-DENY: only the read-only outline ability sets
 * meta.mcp.public. Approve-change, sign-off, and queue-for-github are
 * deliberately NOT registered as abilities at all — a sign-off is a legal
 * record and must never be reachable by an unaudited AI client. That
 * enforcement mirrors the human-only pppd_approve_changes capability.
 *
 * Every ability checks a real minimum capability (never __return_true).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook ability registration (no-op on WordPress without the Abilities API).
 *
 * Both hooks fire lazily, the first time the respective registry is accessed.
 *
 * @return void
 */
function pppd_abilities_init() {
	add_action( 'wp_abilities_api_categories_init', 'pppd_register_ability_category' );
	add_action( 'wp_abilities_api_init', 'pppd_register_abilities' );
}

/**
 * Register the plugin's ability category (required before any ability).
 *
 * @return void
 */
function pppd_register_ability_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'process-docs',
		array(
			'label'       => __( 'Process Docs', 'pretty-professional-process-docs' ),
			'description' => __( 'Agent-facing operations on living process documents: reading outlines, proposing changes, and working the GitHub push queue. Approval and sign-off are human-only and deliberately absent.', 'pretty-professional-process-docs' ),
		)
	);
}

/**
 * Register the plugin's abilities.
 *
 * @return void
 */
function pppd_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability(
		'pppd/get-outline',
		array(
			'label'               => __( 'Get report outline', 'pretty-professional-process-docs' ),
			'description'         => __( 'The section tree of a process-docs report: types, statuses, item IDs, comment counts, and sign-off state. Call before proposing any change; sections with signoff.state "approved" are read-only for agents.', 'pretty-professional-process-docs' ),
			'category'            => 'process-docs',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'report_id' => array(
						'type'        => 'integer',
						'description' => __( 'Report post ID.', 'pretty-professional-process-docs' ),
					),
				),
				'required'   => array( 'report_id' ),
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => static function ( $input ) {
				$report_id = isset( $input['report_id'] ) ? absint( $input['report_id'] ) : 0;
				$report    = get_post( $report_id );

				if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
					return new WP_Error( 'pppd_report_not_found', __( 'Report not found.', 'pretty-professional-process-docs' ) );
				}

				return pppd_get_outline_payload( $report_id );
			},
			'permission_callback' => static function ( $input ) {
				return pppd_user_can_view_report( get_current_user_id(), isset( $input['report_id'] ) ? absint( $input['report_id'] ) : 0 );
			},
			// The one deliberately MCP-visible ability: read-only, view-gated.
			'meta'                => array(
				'annotations'  => array(
					'readonly' => true,
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		)
	);

	wp_register_ability(
		'pppd/propose-change',
		array(
			'label'               => __( 'Propose a section change', 'pretty-professional-process-docs' ),
			'description'         => __( 'File a pending pppd_change proposal against a published section. Never edits content directly; a human reviews and approves in the Review Queue.', 'pretty-professional-process-docs' ),
			'category'            => 'process-docs',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'section_id' => array( 'type' => 'integer' ),
					'title'      => array( 'type' => 'string' ),
					'content'    => array( 'type' => 'string' ),
					'source'     => array( 'type' => 'string' ),
					'rationale'  => array( 'type' => 'string' ),
				),
				'required'   => array( 'section_id', 'content' ),
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => 'pppd_ability_propose_change',
			'permission_callback' => static function () {
				return current_user_can( 'edit_pppd_reports' );
			},
			// Not MCP-exposed: writes stay on audited, credentialed surfaces.
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => false ),
			),
		)
	);

	wp_register_ability(
		'pppd/get-github-queue',
		array(
			'label'               => __( 'Get the GitHub push queue', 'pretty-professional-process-docs' ),
			'description'         => __( 'Approved, signed-off sections a dev/PM flagged ready to become GitHub issues.', 'pretty-professional-process-docs' ),
			'category'            => 'process-docs',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'report_id' => array( 'type' => 'integer' ),
				),
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => static function ( $input ) {
				$items = array();

				foreach ( pppd_get_github_queue( isset( $input['report_id'] ) ? absint( $input['report_id'] ) : 0 ) as $section ) {
					$items[] = pppd_build_github_queue_item( $section );
				}

				return array(
					'count' => count( $items ),
					'items' => $items,
				);
			},
			'permission_callback' => static function () {
				return current_user_can( 'edit_pppd_reports' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly' => true,
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => false ),
			),
		)
	);

	wp_register_ability(
		'pppd/mark-issue-pushed',
		array(
			'label'               => __( 'Mark a queued item pushed', 'pretty-professional-process-docs' ),
			'description'         => __( 'Record the GitHub issue created for a queued section (queued → pushed; idempotent, never double-pushes).', 'pretty-professional-process-docs' ),
			'category'            => 'process-docs',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'section_id'   => array( 'type' => 'integer' ),
					'issue_url'    => array( 'type' => 'string' ),
					'issue_number' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'section_id', 'issue_url', 'issue_number' ),
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => static function ( $input ) {
				return pppd_mark_github_pushed(
					absint( $input['section_id'] ),
					(string) $input['issue_url'],
					absint( $input['issue_number'] )
				);
			},
			'permission_callback' => static function () {
				return current_user_can( 'edit_pppd_reports' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => false ),
			),
		)
	);

	// NOT registered, on purpose: approve-change, sign-off-section,
	// queue-for-github. Approval surfaces stay human-only (the
	// pppd_approve_changes capability) and are never AI-exposable.
}

/**
 * Execute callback for pppd/propose-change.
 *
 * @param array<string, mixed> $input Validated input.
 * @return array<string, mixed>|WP_Error
 */
function pppd_ability_propose_change( $input ) {
	$section = get_post( absint( $input['section_id'] ) );

	if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
		return new WP_Error( 'pppd_section_not_found', __( 'Target section not found.', 'pretty-professional-process-docs' ) );
	}

	$change_id = wp_insert_post(
		array(
			'post_type'    => 'pppd_change',
			'post_status'  => 'pending',
			'post_title'   => isset( $input['title'] ) && '' !== (string) $input['title']
				? sanitize_text_field( (string) $input['title'] )
				/* translators: %s: target section title. */
				: sprintf( __( 'Proposed change: %s', 'pretty-professional-process-docs' ), get_the_title( $section ) ),
			'post_content' => (string) $input['content'],
		),
		true
	);

	if ( is_wp_error( $change_id ) ) {
		return $change_id;
	}

	update_post_meta( $change_id, '_pppd_target_section', (int) $section->ID );
	update_post_meta( $change_id, '_pppd_source', isset( $input['source'] ) ? sanitize_text_field( (string) $input['source'] ) : '' );
	update_post_meta( $change_id, '_pppd_rationale', isset( $input['rationale'] ) ? sanitize_textarea_field( (string) $input['rationale'] ) : '' );

	return pppd_get_change_summary( get_post( $change_id ) );
}
