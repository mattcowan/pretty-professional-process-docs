<?php
/**
 * Capabilities and roles.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * The primitive capabilities for the shared pppd_report capability set.
 *
 * @return string[]
 */
function pppd_get_capabilities() {
	return array(
		'edit_pppd_reports',
		'edit_others_pppd_reports',
		'edit_private_pppd_reports',
		'edit_published_pppd_reports',
		'publish_pppd_reports',
		'read_private_pppd_reports',
		'delete_pppd_reports',
		'delete_private_pppd_reports',
		'delete_others_pppd_reports',
		'delete_published_pppd_reports',
		'create_pppd_reports',
	);
}

/**
 * Grant capabilities to administrator + editor and create the agent role.
 *
 * Runs on activation. The pppd_agent role gets read + edit/publish/create
 * capabilities but no delete_* capabilities and no pppd_approve_changes
 * (approval stays human-only).
 *
 * @return void
 */
function pppd_grant_capabilities() {
	$all_caps = array_merge( pppd_get_capabilities(), array( 'pppd_approve_changes' ) );

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );

		if ( null === $role ) {
			continue;
		}

		foreach ( $all_caps as $cap ) {
			$role->add_cap( $cap );
		}
	}

	$agent_caps = array( 'read' => true );

	foreach ( pppd_get_capabilities() as $cap ) {
		if ( 0 === strpos( $cap, 'delete_' ) ) {
			continue;
		}

		$agent_caps[ $cap ] = true;
	}

	if ( null === get_role( 'pppd_agent' ) ) {
		add_role(
			'pppd_agent',
			__( 'Process Docs Agent', 'pretty-professional-process-docs' ),
			$agent_caps
		);
	}
}

/**
 * Enforce the approval gate at the REST layer.
 *
 * Agents hold report-level edit caps, which would otherwise let them rewrite
 * published sections directly via core wp/v2 routes — bypassing the
 * proposed-change Review Queue entirely. Creation and draft edits stay open
 * (an initial FRD import needs them); UPDATING a published section requires
 * the human-only pppd_approve_changes capability. The approve workflow itself
 * runs as the reviewing user, so approved changes still land.
 *
 * Hooked to rest_pre_insert_pppd_section.
 *
 * @param stdClass|WP_Error $prepared_post Post object about to be inserted.
 * @param WP_REST_Request   $request       The request.
 * @return stdClass|WP_Error
 */
function pppd_guard_published_section_updates( $prepared_post, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( is_wp_error( $prepared_post ) || empty( $prepared_post->ID ) ) {
		return $prepared_post;
	}

	$existing = get_post( (int) $prepared_post->ID );

	if ( ! $existing instanceof WP_Post || 'publish' !== $existing->post_status ) {
		return $prepared_post;
	}

	if ( current_user_can( 'pppd_approve_changes' ) ) {
		return $prepared_post;
	}

	return new WP_Error(
		'pppd_approval_required',
		__( 'Published sections can only be modified through the proposed-change review workflow.', 'pretty-professional-process-docs' ),
		array( 'status' => 403 )
	);
}

/**
 * Remove all PPPD capabilities and the agent role.
 *
 * Called from uninstall.php only — deactivation intentionally leaves
 * roles/capabilities in place.
 *
 * @return void
 */
function pppd_remove_capabilities() {
	$all_caps = array_merge( pppd_get_capabilities(), array( 'pppd_approve_changes' ) );

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );

		if ( null === $role ) {
			continue;
		}

		foreach ( $all_caps as $cap ) {
			$role->remove_cap( $cap );
		}
	}

	remove_role( 'pppd_agent' );
}
