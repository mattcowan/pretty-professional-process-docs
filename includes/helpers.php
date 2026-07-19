<?php
/**
 * Shared helpers: section trees, status badges, drift lookups.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get every section belonging to a report, ordered by menu_order then title.
 *
 * @param int             $report_id Report post ID.
 * @param string|string[] $statuses  Post statuses to include. Default 'publish'
 *                                   (the client-facing render); authoring
 *                                   surfaces pass pppd_authoring_statuses().
 * @return WP_Post[]
 */
function pppd_get_report_section_posts( $report_id, $statuses = 'publish' ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'pppd_section',
			'post_status'            => $statuses,
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
 * @param int             $report_id Report post ID.
 * @param string|string[] $statuses  Post statuses to include. Default 'publish'.
 * @return array<int, array{post: WP_Post, depth: int}>
 */
function pppd_get_report_sections( $report_id, $statuses = 'publish' ) {
	$posts = pppd_get_report_section_posts( $report_id, $statuses );

	return pppd_flatten_section_tree( pppd_group_sections_by_parent( $posts ) );
}

/**
 * The post statuses authoring surfaces work across (everything an editor can
 * still act on, unlike the publish-only client render).
 *
 * @return string[]
 */
function pppd_authoring_statuses() {
	return array( 'publish', 'draft', 'pending', 'future', 'private' );
}

/**
 * The post statuses the front-end report render should include for the
 * current viewer.
 *
 * Clients and anonymous visitors always get the publish-only document. Team
 * viewers get the full authoring set — with every non-published section
 * visibly flagged in the render — unless they asked to see the report exactly
 * as a client sees it (pppd_is_client_preview()).
 *
 * @param int $report_id Report post ID (reserved for future per-report rules).
 * @return string|string[]
 */
function pppd_report_render_statuses( $report_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( ! pppd_is_team_viewer() || pppd_is_client_preview() ) {
		return 'publish';
	}

	return pppd_authoring_statuses();
}

/**
 * Build the authoring outline for a report: every section (all authoring
 * statuses) with depth, badges, edit links, and per-row movement flags for
 * the outline metabox / reorder endpoint.
 *
 * @param int $report_id Report post ID.
 * @return array<int, array<string, mixed>>
 */
function pppd_get_authoring_outline( $report_id ) {
	$posts   = pppd_get_report_section_posts( $report_id, pppd_authoring_statuses() );
	$grouped = pppd_group_sections_by_parent( $posts );
	$ids     = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
	$entries = array();

	foreach ( pppd_flatten_section_tree( $grouped ) as $entry ) {
		$section = $entry['post'];
		$depth   = (int) $entry['depth'];

		// Effective parent (orphaned branches are treated as roots, matching
		// pppd_group_sections_by_parent()).
		$parent   = in_array( (int) $section->post_parent, $ids, true ) ? (int) $section->post_parent : 0;
		$siblings = isset( $grouped[ $parent ] ) ? $grouped[ $parent ] : array();
		$index    = 0;

		foreach ( $siblings as $i => $sibling ) {
			if ( (int) $sibling->ID === (int) $section->ID ) {
				$index = (int) $i;
				break;
			}
		}

		$edit_link = get_edit_post_link( $section->ID, 'raw' );

		$entries[] = array(
			'id'          => (int) $section->ID,
			// Decoded: this payload feeds both esc_html() in PHP and
			// textContent in JS — entities must not arrive pre-encoded.
			'title'       => wp_specialchars_decode( get_the_title( $section ), ENT_QUOTES ),
			'parent'      => $parent,
			'depth'       => $depth,
			'type'        => pppd_get_section_type_slug( $section ),
			'post_status' => (string) $section->post_status,
			'status'      => pppd_get_section_term_slug( $section, 'pppd_status' ),
			'req_id'      => (string) get_post_meta( $section->ID, '_pppd_req_id', true ),
			'edit_link'   => is_string( $edit_link ) ? $edit_link : '',
			'anchor'      => pppd_section_anchor( $section ),
			'can_up'      => $index > 0,
			'can_down'    => $index < count( $siblings ) - 1,
			'can_indent'  => $index > 0,
			'can_outdent' => $depth > 0,
		);
	}

	return $entries;
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
 * Build the DOM anchor (fragment id) for a section.
 *
 * Section anchors are prefixed with `pppd-section-` so a section slug can never
 * collide with the report template's fixed IDs (executive-summary,
 * drift-summary), which would otherwise produce duplicate IDs and break the
 * TOC, skip link, and aria-labelledby targeting. Every place that emits or
 * links to a section anchor MUST route through this helper so the id used in
 * markup and the fragment used in links stay in lockstep.
 *
 * @since 0.1.0
 *
 * @param WP_Post $section Section post.
 * @return string Anchor id (unescaped; escape at the point of output).
 */
function pppd_section_anchor( $section ) {
	// Drafts have no slug until publish; fall back to the post ID so two
	// draft sections in the team-view render never share a DOM id.
	if ( '' === (string) $section->post_name ) {
		return 'pppd-section-id-' . (int) $section->ID;
	}

	return 'pppd-section-' . $section->post_name;
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
			esc_attr( pppd_section_anchor( $section ) ),
			esc_html( get_the_title( $section ) )
		);

		pppd_render_toc_list( $grouped, (int) $section->ID );

		echo '</li>';
	}

	echo '</ol>';
}
