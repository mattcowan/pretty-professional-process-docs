<?php
/**
 * Item ID assignment (requirement IDs and any other ID-bearing section type).
 *
 * IDs are assigned once, on first publish of a section whose registered type
 * declares has_ids, and are never reassigned or reused. Which types bear IDs,
 * and under which prefix, comes from the registry (class-pppd-registry.php);
 * the historical behavior — requirement sections in an FRD get FR-001,
 * FR-002, … from the per-report _pppd_req_counter meta — is preserved
 * exactly.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maybe assign an item ID when a section is saved.
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

	if ( ! pppd_section_type_supports( $post, 'has_ids' ) ) {
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

	$type_slug = pppd_get_section_type_slug( $post );
	$prefix    = pppd_resolve_item_id_prefix( $post, $report );
	$counter   = pppd_claim_next_requirement_number( $report_id, pppd_item_counter_meta_key( $type_slug, $prefix ) );

	$report_type = pppd_get_report_type_object( $report );
	$format      = ( null !== $report_type && ! empty( $report_type['id_scheme']['format'] ) ) ? (string) $report_type['id_scheme']['format'] : '%s-%03d';

	$item_id = sprintf( $format, $prefix, $counter ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- ID format, not a translatable string.

	update_post_meta( $post_id, '_pppd_req_id', $item_id );

	/**
	 * Fires after an item ID is assigned to a section.
	 *
	 * @param int    $post_id   Section post ID.
	 * @param string $item_id   The assigned ID (e.g. FR-007).
	 * @param int    $report_id Report post ID.
	 */
	do_action( 'pppd_item_id_assigned', $post_id, $item_id, $report_id );
}

/**
 * Resolve the ID prefix for a section within its report.
 *
 * Order: an explicit per-report _pppd_req_prefix meta row (only for section
 * types that declare report_prefix_override — the requirement built-in), then
 * the section type's own id_prefix, then the report type's id_scheme prefix,
 * then 'FR'. Filterable via pppd_item_id_prefix.
 *
 * @param WP_Post $section Section post.
 * @param WP_Post $report  Report post.
 * @return string
 */
function pppd_resolve_item_id_prefix( $section, $report ) {
	$type_slug    = pppd_get_section_type_slug( $section );
	$section_type = pppd_registry()->get_section_type( $type_slug );
	$report_type  = pppd_get_report_type_object( $report );

	$prefix = '';

	if ( null !== $section_type && ! empty( $section_type['report_prefix_override'] ) && metadata_exists( 'post', $report->ID, '_pppd_req_prefix' ) ) {
		$prefix = (string) get_post_meta( $report->ID, '_pppd_req_prefix', true );
	}

	if ( '' === $prefix && null !== $section_type ) {
		$prefix = (string) $section_type['id_prefix'];
	}

	if ( '' === $prefix && null !== $report_type && ! empty( $report_type['id_scheme']['prefix'] ) ) {
		$prefix = (string) $report_type['id_scheme']['prefix'];
	}

	if ( '' === $prefix ) {
		$prefix = 'FR';
	}

	/**
	 * Filter the resolved item-ID prefix for a section.
	 *
	 * @param string  $prefix  Resolved prefix.
	 * @param WP_Post $section Section post.
	 * @param WP_Post $report  Report post.
	 */
	return (string) apply_filters( 'pppd_item_id_prefix', $prefix, $section, $report );
}

/**
 * The per-report counter meta key for a section type + prefix pair.
 *
 * Requirement sections keep the legacy _pppd_req_counter key — a contract
 * invariant; existing FRD reports must keep numbering from where they left
 * off. Every other ID-bearing type counts per prefix.
 *
 * @param string $type_slug Section type slug.
 * @param string $prefix    Resolved ID prefix.
 * @return string Meta key.
 */
function pppd_item_counter_meta_key( $type_slug, $prefix ) {
	if ( 'requirement' === $type_slug ) {
		return '_pppd_req_counter';
	}

	return '_pppd_counter_' . sanitize_key( $prefix );
}

/**
 * REST-created sections: terms and meta are assigned AFTER wp_insert_post
 * fires save_post, so the save_post callback sees neither the section type
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
 * Atomically claim the next item number for a report's counter.
 *
 * The counter lives in per-report post meta ($meta_key). A naive
 * read-then-write races when two sections are published for the same report
 * concurrently: both reads see the same value and both hand out the same
 * number. Instead we compare-and-swap in a retry loop:
 *
 *   - The first item seeds the counter with a `unique` insert.
 *   - Subsequent items pass the previously-read value as
 *     update_post_meta()'s $prev_value, so the underlying UPDATE only matches
 *     while the stored value is unchanged. A racing writer that already bumped
 *     the counter makes the conditional update affect zero rows; we re-read
 *     (bypassing the meta cache) and try again with the fresh value.
 *
 * @since 0.1.0
 * @since 0.2.0 The $meta_key parameter.
 *
 * @param int    $report_id Report post ID.
 * @param string $meta_key  Counter meta key. Default _pppd_req_counter.
 * @return int The number claimed (>= 1).
 */
function pppd_claim_next_requirement_number( $report_id, $meta_key = '_pppd_req_counter' ) {
	$max_attempts = 10;

	for ( $attempt = 0; $attempt < $max_attempts; $attempt++ ) {
		$raw = get_post_meta( $report_id, $meta_key, true );

		// No counter yet: claim number 1 with an insert that only lands when
		// the key is still absent. A racing seeder makes this fail; we fall
		// through to the update branch on the next iteration.
		if ( '' === $raw || null === $raw || false === $raw ) {
			if ( add_post_meta( $report_id, $meta_key, 1, true ) ) {
				return 1;
			}

			wp_cache_delete( $report_id, 'post_meta' );
			continue;
		}

		$current = absint( $raw );
		$next    = $current + 1;

		// Conditional update: matches only while the stored value is $current.
		if ( update_post_meta( $report_id, $meta_key, $next, $current ) ) {
			return $next;
		}

		// Another request advanced the counter between our read and write.
		// Drop the stale meta cache so the next read hits the database truth.
		wp_cache_delete( $report_id, 'post_meta' );
	}

	// Retries exhausted (pathological contention): fall back to a best-effort
	// non-atomic bump so a number is still assigned.
	$counter = absint( get_post_meta( $report_id, $meta_key, true ) ) + 1;
	update_post_meta( $report_id, $meta_key, $counter );

	return $counter;
}
