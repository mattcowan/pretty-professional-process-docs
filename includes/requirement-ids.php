<?php
/**
 * Requirement ID assignment.
 *
 * IDs are assigned once, on first publish of a requirement-type section,
 * and are never reassigned or reused.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maybe assign a requirement ID when a section is saved.
 *
 * Hooked to save_post_pppd_section at priority 20 so the meta box save
 * (priority 10) has already stored _pppd_report_id.
 *
 * @param int     $post_id Section post ID.
 * @param WP_Post $post    Section post object.
 * @param bool    $update  Whether this is an update.
 * @return void
 */
function pppd_maybe_assign_requirement_id( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( 'publish' !== $post->post_status ) {
		return;
	}

	if ( ! has_term( 'requirement', 'pppd_section_type', $post ) ) {
		return;
	}

	// Guard: an existing ID short-circuits — IDs are never reassigned.
	$existing = get_post_meta( $post_id, '_pppd_req_id', true );

	if ( is_string( $existing ) && '' !== $existing ) {
		return;
	}

	$report_id = absint( get_post_meta( $post_id, '_pppd_report_id', true ) );

	if ( 0 === $report_id ) {
		return;
	}

	$report = get_post( $report_id );

	if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
		return;
	}

	$counter = absint( get_post_meta( $report_id, '_pppd_req_counter', true ) ) + 1;
	update_post_meta( $report_id, '_pppd_req_counter', $counter );

	$prefix = get_post_meta( $report_id, '_pppd_req_prefix', true );

	if ( ! is_string( $prefix ) || '' === $prefix ) {
		$prefix = 'FR';
	}

	update_post_meta( $post_id, '_pppd_req_id', sprintf( '%s-%03d', $prefix, $counter ) );
}

/**
 * REST-created sections: terms and meta are assigned AFTER wp_insert_post
 * fires save_post, so the save_post callback sees neither the requirement
 * term nor _pppd_report_id. Re-run the assignment once the full REST insert
 * (fields, meta, terms) has completed.
 *
 * @param WP_Post $post Section post object.
 * @return void
 */
function pppd_maybe_assign_requirement_id_rest( $post ) {
	pppd_maybe_assign_requirement_id( $post->ID, get_post( $post->ID ), true );
}
