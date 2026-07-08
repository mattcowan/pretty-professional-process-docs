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

	$counter = pppd_claim_next_requirement_number( $report_id );

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

/**
 * Atomically claim the next requirement number for a report.
 *
 * The counter lives in the _pppd_req_counter post meta. A naive
 * read-then-write races when two requirement sections are published for the
 * same report concurrently: both reads see the same value and both hand out
 * the same number. Instead we compare-and-swap in a retry loop:
 *
 *   - The first requirement seeds the counter with a `unique` insert.
 *   - Subsequent requirements pass the previously-read value as
 *     update_post_meta()'s $prev_value, so the underlying UPDATE only matches
 *     while the stored value is unchanged. A racing writer that already bumped
 *     the counter makes the conditional update affect zero rows; we re-read
 *     (bypassing the meta cache) and try again with the fresh value.
 *
 * @since 0.1.0
 *
 * @param int $report_id Report post ID.
 * @return int The number claimed for this requirement (>= 1).
 */
function pppd_claim_next_requirement_number( $report_id ) {
	$max_attempts = 10;

	for ( $attempt = 0; $attempt < $max_attempts; $attempt++ ) {
		$raw = get_post_meta( $report_id, '_pppd_req_counter', true );

		// No counter yet: claim FR-001 with an insert that only lands when the
		// key is still absent. A racing seeder makes this fail; we fall through
		// to the update branch on the next iteration.
		if ( '' === $raw || null === $raw || false === $raw ) {
			if ( add_post_meta( $report_id, '_pppd_req_counter', 1, true ) ) {
				return 1;
			}

			wp_cache_delete( $report_id, 'post_meta' );
			continue;
		}

		$current = absint( $raw );
		$next    = $current + 1;

		// Conditional update: matches only while the stored value is $current.
		if ( update_post_meta( $report_id, '_pppd_req_counter', $next, $current ) ) {
			return $next;
		}

		// Another request advanced the counter between our read and write.
		// Drop the stale meta cache so the next read hits the database truth.
		wp_cache_delete( $report_id, 'post_meta' );
	}

	// Retries exhausted (pathological contention): fall back to a best-effort
	// non-atomic bump so a number is still assigned.
	$counter = absint( get_post_meta( $report_id, '_pppd_req_counter', true ) ) + 1;
	update_post_meta( $report_id, '_pppd_req_counter', $counter );

	return $counter;
}
