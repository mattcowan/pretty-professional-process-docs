<?php
/**
 * Shared helpers: section trees, status badges, drift lookups.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get every published section belonging to a report, ordered by
 * menu_order then title.
 *
 * @param int $report_id Report post ID.
 * @return WP_Post[]
 */
function pppd_get_report_section_posts( $report_id ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'pppd_section',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'meta_key'               => '_pppd_report_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'             => (string) absint( $report_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		)
	);

	return $query->posts;
}

/**
 * Group an ordered list of sections by parent ID.
 *
 * Sections whose parent is not part of the set are treated as roots so
 * orphaned branches still render.
 *
 * @param WP_Post[] $sections Ordered section posts.
 * @return array<int, WP_Post[]> Map of parent ID to ordered children.
 */
function pppd_group_sections_by_parent( $sections ) {
	$ids     = array_map( 'intval', wp_list_pluck( $sections, 'ID' ) );
	$grouped = array();

	foreach ( $sections as $section ) {
		$parent = in_array( (int) $section->post_parent, $ids, true ) ? (int) $section->post_parent : 0;

		if ( ! isset( $grouped[ $parent ] ) ) {
			$grouped[ $parent ] = array();
		}

		$grouped[ $parent ][] = $section;
	}

	return $grouped;
}

/**
 * Flatten a grouped section tree depth-first.
 *
 * @param array<int, WP_Post[]> $grouped Output of pppd_group_sections_by_parent().
 * @param int                   $parent  Parent ID to start from.
 * @param int                   $depth   Current depth.
 * @return array<int, array{post: WP_Post, depth: int}>
 */
function pppd_flatten_section_tree( $grouped, $parent = 0, $depth = 0 ) {
	$flat = array();

	if ( empty( $grouped[ $parent ] ) ) {
		return $flat;
	}

	foreach ( $grouped[ $parent ] as $section ) {
		$flat[] = array(
			'post'  => $section,
			'depth' => $depth,
		);

		$flat = array_merge( $flat, pppd_flatten_section_tree( $grouped, (int) $section->ID, $depth + 1 ) );
	}

	return $flat;
}

/**
 * Get a report's sections as a flat, hierarchy-ordered list with depths.
 *
 * @param int $report_id Report post ID.
 * @return array<int, array{post: WP_Post, depth: int}>
 */
function pppd_get_report_sections( $report_id ) {
	$posts = pppd_get_report_section_posts( $report_id );

	return pppd_flatten_section_tree( pppd_group_sections_by_parent( $posts ) );
}

/**
 * Get the first term slug a section has in a taxonomy, or '' if none.
 *
 * @param WP_Post|int $section  Section post or ID.
 * @param string      $taxonomy Taxonomy name.
 * @return string
 */
function pppd_get_section_term_slug( $section, $taxonomy ) {
	$terms = get_the_terms( $section, $taxonomy );

	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}

	return (string) $terms[0]->slug;
}

/**
 * Get the first term a section has in the pppd_status taxonomy.
 *
 * @param WP_Post|int $section Section post or ID.
 * @return WP_Term|null
 */
function pppd_get_section_status_term( $section ) {
	$terms = get_the_terms( $section, 'pppd_status' );

	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return null;
	}

	return $terms[0];
}

/**
 * Symbol map for status badges (status is never conveyed by colour alone).
 *
 * @return array<string, string>
 */
function pppd_status_symbols() {
	return array(
		'draft'    => '◌',
		'agreed'   => '●',
		'at-risk'  => '▲',
		'built'    => '■',
		'verified' => '✔',
	);
}

/**
 * Get the most recent drift run post for a report, or null.
 *
 * @param int $report_id Report post ID.
 * @return WP_Post|null
 */
function pppd_get_latest_drift( $report_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'pppd_drift',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_pppd_report_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => (string) absint( $report_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	return empty( $query->posts ) ? null : $query->posts[0];
}

/**
 * Decode the stored drift items for a drift post.
 *
 * @param WP_Post|null $drift Drift post.
 * @return array<int, array<string, mixed>>
 */
function pppd_get_drift_items( $drift ) {
	if ( ! $drift instanceof WP_Post ) {
		return array();
	}

	$raw = get_post_meta( $drift->ID, '_pppd_drift_json', true );

	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}

	$items = json_decode( $raw, true );

	return is_array( $items ) ? $items : array();
}

/**
 * Render a nested <ol> table-of-contents list for a grouped section tree.
 *
 * Escapes everything it prints.
 *
 * @param array<int, WP_Post[]> $grouped Output of pppd_group_sections_by_parent().
 * @param int                   $parent  Parent ID to render.
 * @return void
 */
function pppd_render_toc_list( $grouped, $parent = 0 ) {
	if ( empty( $grouped[ $parent ] ) ) {
		return;
	}

	echo '<ol>';

	foreach ( $grouped[ $parent ] as $section ) {
		printf(
			'<li><a href="#%1$s">%2$s</a>',
			esc_attr( $section->post_name ),
			esc_html( get_the_title( $section ) )
		);

		pppd_render_toc_list( $grouped, (int) $section->ID );

		echo '</li>';
	}

	echo '</ol>';
}
