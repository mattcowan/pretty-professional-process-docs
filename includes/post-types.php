<?php
/**
 * Post types, post statuses, taxonomies, and registered meta.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared capability set: every PPPD post type maps onto the pppd_report
 * primitive capabilities so one grant covers the whole plugin.
 *
 * @return array{0:string,1:string}
 */
function pppd_capability_type() {
	return array( 'pppd_report', 'pppd_reports' );
}

/**
 * Register the four custom post types.
 *
 * @return void
 */
function pppd_register_post_types() {
	register_post_type(
		'pppd_report',
		array(
			'labels'          => array(
				'name'          => __( 'Reports', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Report', 'pretty-professional-process-docs' ),
				'add_new_item'  => __( 'Add New Report', 'pretty-professional-process-docs' ),
				'edit_item'     => __( 'Edit Report', 'pretty-professional-process-docs' ),
				'new_item'      => __( 'New Report', 'pretty-professional-process-docs' ),
				'view_item'     => __( 'View Report', 'pretty-professional-process-docs' ),
				'search_items'  => __( 'Search Reports', 'pretty-professional-process-docs' ),
				'not_found'     => __( 'No reports found.', 'pretty-professional-process-docs' ),
				'menu_name'     => __( 'Process Docs', 'pretty-professional-process-docs' ),
			),
			'public'          => true,
			'has_archive'     => false,
			'rewrite'         => array(
				'slug'       => 'report',
				'with_front' => false,
			),
			'menu_icon'       => 'dashicons-media-document',
			'show_in_rest'    => true,
			'rest_base'       => 'pppd-reports',
			'supports'        => array( 'title', 'editor', 'thumbnail', 'revisions', 'author', 'custom-fields' ),
			'capability_type' => pppd_capability_type(),
			'map_meta_cap'    => true,
		)
	);

	register_post_type(
		'pppd_section',
		array(
			'labels'             => array(
				'name'          => __( 'Sections', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Section', 'pretty-professional-process-docs' ),
				'add_new_item'  => __( 'Add New Section', 'pretty-professional-process-docs' ),
				'edit_item'     => __( 'Edit Section', 'pretty-professional-process-docs' ),
				'new_item'      => __( 'New Section', 'pretty-professional-process-docs' ),
				'search_items'  => __( 'Search Sections', 'pretty-professional-process-docs' ),
				'not_found'     => __( 'No sections found.', 'pretty-professional-process-docs' ),
				'parent_item_colon' => __( 'Parent Section:', 'pretty-professional-process-docs' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=pppd_report',
			'hierarchical'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'pppd-sections',
			'supports'           => array( 'title', 'editor', 'revisions', 'comments', 'page-attributes', 'author', 'custom-fields' ),
			'capability_type'    => pppd_capability_type(),
			'map_meta_cap'       => true,
		)
	);

	register_post_type(
		'pppd_change',
		array(
			'labels'             => array(
				'name'          => __( 'Proposed Changes', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Proposed Change', 'pretty-professional-process-docs' ),
				'add_new_item'  => __( 'Add New Proposed Change', 'pretty-professional-process-docs' ),
				'edit_item'     => __( 'Edit Proposed Change', 'pretty-professional-process-docs' ),
				'search_items'  => __( 'Search Proposed Changes', 'pretty-professional-process-docs' ),
				'not_found'     => __( 'No proposed changes found.', 'pretty-professional-process-docs' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=pppd_report',
			'show_in_rest'       => true,
			'rest_base'          => 'pppd-changes',
			'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
			'capability_type'    => pppd_capability_type(),
			'map_meta_cap'       => true,
		)
	);

	register_post_type(
		'pppd_drift',
		array(
			'labels'             => array(
				'name'          => __( 'Drift Runs', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Drift Run', 'pretty-professional-process-docs' ),
				'add_new_item'  => __( 'Add New Drift Run', 'pretty-professional-process-docs' ),
				'edit_item'     => __( 'Edit Drift Run', 'pretty-professional-process-docs' ),
				'search_items'  => __( 'Search Drift Runs', 'pretty-professional-process-docs' ),
				'not_found'     => __( 'No drift runs found.', 'pretty-professional-process-docs' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=pppd_report',
			'show_in_rest'       => true,
			'rest_base'          => 'pppd-drift',
			'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
			'capability_type'    => pppd_capability_type(),
			'map_meta_cap'       => true,
		)
	);
}

/**
 * Register the custom "Rejected" post status used by pppd_change.
 *
 * @return void
 */
function pppd_register_post_statuses() {
	register_post_status(
		'pppd_rejected',
		array(
			'label'                     => __( 'Rejected', 'pretty-professional-process-docs' ),
			'public'                    => false,
			'internal'                  => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of rejected changes. */
			'label_count'               => _n_noop(
				'Rejected <span class="count">(%s)</span>',
				'Rejected <span class="count">(%s)</span>',
				'pretty-professional-process-docs'
			),
		)
	);
}

/**
 * Register the taxonomies.
 *
 * @return void
 */
function pppd_register_taxonomies() {
	register_taxonomy(
		'pppd_section_type',
		'pppd_section',
		array(
			'labels'            => array(
				'name'          => __( 'Section Types', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Section Type', 'pretty-professional-process-docs' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_pppd_reports',
			),
		)
	);

	register_taxonomy(
		'pppd_status',
		'pppd_section',
		array(
			'labels'            => array(
				'name'          => __( 'Statuses', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Status', 'pretty-professional-process-docs' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_pppd_reports',
			),
		)
	);

	// Which registered report type a report is. Terms are synced from the
	// registry (pppd_sync_registry_terms); reports without a term are FRDs.
	register_taxonomy(
		'pppd_report_type',
		'pppd_report',
		array(
			'labels'            => array(
				'name'          => __( 'Report Types', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Report Type', 'pretty-professional-process-docs' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_pppd_reports',
			),
		)
	);

	// The organizing entity a report belongs to. A client has any number of
	// reports of any type; client users gain view access via membership
	// (user meta _pppd_client_ids — see includes/clients.php).
	register_taxonomy(
		'pppd_client',
		'pppd_report',
		array(
			'labels'            => array(
				'name'          => __( 'Clients', 'pretty-professional-process-docs' ),
				'singular_name' => __( 'Client', 'pretty-professional-process-docs' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_pppd_reports',
			),
		)
	);
}

/**
 * Insert default taxonomy terms (activation only; idempotent).
 *
 * @return void
 */
function pppd_insert_default_terms() {
	$section_types = array(
		'narrative'   => __( 'Narrative', 'pretty-professional-process-docs' ),
		'requirement' => __( 'Requirement', 'pretty-professional-process-docs' ),
		'decision'    => __( 'Decision', 'pretty-professional-process-docs' ),
	);

	foreach ( $section_types as $slug => $name ) {
		if ( ! term_exists( $slug, 'pppd_section_type' ) ) {
			wp_insert_term( $name, 'pppd_section_type', array( 'slug' => $slug ) );
		}
	}

	$statuses = array(
		'draft'    => __( 'Draft', 'pretty-professional-process-docs' ),
		'agreed'   => __( 'Agreed', 'pretty-professional-process-docs' ),
		'at-risk'  => __( 'At risk', 'pretty-professional-process-docs' ),
		'built'    => __( 'Built', 'pretty-professional-process-docs' ),
		'verified' => __( 'Verified', 'pretty-professional-process-docs' ),
	);

	foreach ( $statuses as $slug => $name ) {
		if ( ! term_exists( $slug, 'pppd_status' ) ) {
			wp_insert_term( $name, 'pppd_status', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Meta auth callback: only users who can edit reports may write PPPD meta.
 *
 * @return bool
 */
function pppd_meta_auth_callback() {
	return current_user_can( 'edit_pppd_reports' );
}

/**
 * Sanitize a flat array of strings (used for refs, acceptance criteria, paths).
 *
 * @param mixed $value Raw meta value.
 * @return string[]
 */
function pppd_sanitize_string_array( $value ) {
	if ( ! is_array( $value ) ) {
		return array();
	}

	$clean = array();

	foreach ( $value as $item ) {
		if ( ! is_scalar( $item ) ) {
			continue;
		}

		$item = sanitize_text_field( (string) $item );

		if ( '' !== $item ) {
			$clean[] = $item;
		}
	}

	return $clean;
}

/**
 * Sanitize a JSON string by decoding and re-encoding it.
 *
 * Invalid JSON becomes an empty string so garbage never reaches storage.
 *
 * @param mixed $value Raw meta value.
 * @return string
 */
function pppd_sanitize_json_string( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	$decoded = json_decode( $value, true );

	if ( null === $decoded && 'null' !== trim( $value ) ) {
		return '';
	}

	return (string) wp_json_encode( $decoded );
}

/**
 * REST schema fragment for an array-of-strings meta field.
 *
 * @return array
 */
function pppd_string_array_rest_schema() {
	return array(
		'schema' => array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		),
	);
}

/**
 * Register all post meta.
 *
 * @return void
 */
function pppd_register_meta() {
	$common = array(
		'single'        => true,
		'auth_callback' => 'pppd_meta_auth_callback',
	);

	// --- pppd_section ---
	register_post_meta(
		'pppd_section',
		'_pppd_report_id',
		array_merge(
			$common,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_section',
		'_pppd_req_id',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_section',
		'_pppd_acceptance',
		array_merge(
			$common,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'pppd_sanitize_string_array',
				'show_in_rest'      => pppd_string_array_rest_schema(),
			)
		)
	);

	// DEPRECATED as client-facing content since 0.3.0: no longer rendered on
	// the client view. Kept registered (REST contract v1) while consumers
	// migrate to _pppd_impl_notes; writes mirror one-way into empty impl
	// notes (pppd_mirror_dev_prompt). Remove the mirror by 0.5.0.
	register_post_meta(
		'pppd_section',
		'_pppd_dev_prompt',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'show_in_rest'      => true,
			)
		)
	);

	// Internal-only implementation notes: dev/agent hand-off context that
	// must NEVER render on the client-facing view or in client exports.
	register_post_meta(
		'pppd_section',
		'_pppd_impl_notes',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_section',
		'_pppd_code_refs',
		array_merge(
			$common,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'pppd_sanitize_string_array',
				'show_in_rest'      => pppd_string_array_rest_schema(),
			)
		)
	);

	register_post_meta(
		'pppd_section',
		'_pppd_test_refs',
		array_merge(
			$common,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'pppd_sanitize_string_array',
				'show_in_rest'      => pppd_string_array_rest_schema(),
			)
		)
	);

	// Team-only flag: internal sections never render on the client view.
	register_post_meta(
		'pppd_section',
		'_pppd_internal',
		array_merge(
			$common,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		)
	);

	// --- pppd_change ---
	register_post_meta(
		'pppd_change',
		'_pppd_target_section',
		array_merge(
			$common,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_change',
		'_pppd_source',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_change',
		'_pppd_rationale',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_change',
		'_pppd_reject_reason',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_change',
		'_pppd_applied_revision',
		array_merge(
			$common,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		)
	);

	// User ID of the reviewer who approved or rejected the change. Stamped by
	// pppd_approve_change()/pppd_reject_change() and surfaced as reviewed_by in
	// the change summary.
	register_post_meta(
		'pppd_change',
		'_pppd_reviewed_by',
		array_merge(
			$common,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		)
	);

	// GMT MySQL timestamp of the approve/reject decision. Stamped alongside
	// _pppd_reviewed_by; part of the sign-off audit record.
	register_post_meta(
		'pppd_change',
		'_pppd_reviewed_at',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		)
	);

	// --- pppd_report ---
	register_post_meta(
		'pppd_report',
		'_pppd_repo_paths',
		array_merge(
			$common,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'pppd_sanitize_string_array',
				'show_in_rest'      => pppd_string_array_rest_schema(),
			)
		)
	);

	register_post_meta(
		'pppd_report',
		'_pppd_req_prefix',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'default'           => 'FR',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_report',
		'_pppd_req_counter',
		array_merge(
			$common,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_report',
		'_pppd_project_slug',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'show_in_rest'      => true,
			)
		)
	);

	// URL of the exporter-produced tagged (accessible) PDF for this report.
	// Set by the frd `export` workflow after it runs export-pdf.mjs and uploads
	// the file. The front-end "Download accessible PDF" button links here; a
	// browser-print fallback is offered only when this is empty.
	register_post_meta(
		'pppd_report',
		'_pppd_pdf_url',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'show_in_rest'      => true,
			)
		)
	);

	// --- pppd_drift ---
	register_post_meta(
		'pppd_drift',
		'_pppd_report_id',
		array_merge(
			$common,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		)
	);

	register_post_meta(
		'pppd_drift',
		'_pppd_drift_json',
		array_merge(
			$common,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'pppd_sanitize_json_string',
				'show_in_rest'      => true,
			)
		)
	);
}
