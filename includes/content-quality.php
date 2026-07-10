<?php
/**
 * Content quality enforcement: accessible-table normalization at the source
 * (ATAG Part A — the tool must not let inaccessible content ship) and the
 * dev-prompt → implementation-notes deprecation mirror, plus the one-time
 * upgrade migration that applies both to pre-existing content.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook up save-time normalization and the deprecation mirror.
 *
 * @return void
 */
function pppd_content_quality_init() {
	add_filter( 'wp_insert_post_data', 'pppd_filter_post_data_tables', 10, 2 );
	add_action( 'added_post_meta', 'pppd_mirror_dev_prompt', 10, 4 );
	add_action( 'updated_post_meta', 'pppd_mirror_dev_prompt', 10, 4 );
	add_action( 'admin_init', 'pppd_maybe_upgrade' );
}

/**
 * Normalize table headers in report/section content on every save.
 *
 * A header cell that names nothing is the accessibility bug AXE flags as
 * "empty table header": assistive tech announces a blank header for every
 * cell in that column/row. The repair keeps the author's table intact but
 * removes the false header semantic — an empty <th> becomes a <td>. Fixed
 * here, at the source, never patched in the template (req 12).
 *
 * @param array<string, mixed> $data    Slashed post data about to be saved.
 * @param array<string, mixed> $postarr Raw post array.
 * @return array<string, mixed>
 */
function pppd_filter_post_data_tables( $data, $postarr ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! isset( $data['post_type'] ) || ! in_array( $data['post_type'], array( 'pppd_report', 'pppd_section' ), true ) ) {
		return $data;
	}

	if ( ! isset( $data['post_content'] ) || ! is_string( $data['post_content'] ) ) {
		return $data;
	}

	$content    = wp_unslash( $data['post_content'] );
	$normalized = pppd_normalize_table_headers( $content );

	if ( $normalized !== $content ) {
		$data['post_content'] = wp_slash( $normalized );
	}

	return $data;
}

/**
 * Convert empty <th> cells to <td> cells.
 *
 * "Empty" means no text content: whitespace, &nbsp;, <br> tags, or empty
 * inline wrappers only. The scope attribute is dropped from converted cells
 * (it is meaningless on <td> here); all other attributes are kept.
 *
 * @param string $content Post content (block or classic markup).
 * @return string
 */
function pppd_normalize_table_headers( $content ) {
	if ( false === stripos( $content, '<th' ) ) {
		return $content;
	}

	$normalized = preg_replace_callback(
		'#<th\b([^>]*)>(.*?)</th>#is',
		static function ( $matches ) {
			$inner = $matches[2];

			// Strip markup and whitespace-ish entities; anything left is content.
			$text = trim( str_ireplace( array( '&nbsp;', '&#160;' ), '', wp_strip_all_tags( $inner ) ) );

			if ( '' !== $text ) {
				return $matches[0];
			}

			$attributes = preg_replace( '#\sscope\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $matches[1] );

			return '<td' . $attributes . '>' . $inner . '</td>';
		},
		$content
	);

	return is_string( $normalized ) ? $normalized : $content;
}

/**
 * One-way deprecation mirror: a write to _pppd_dev_prompt fills
 * _pppd_impl_notes when (and only when) the notes are still empty, so
 * existing frd-skill flows keep populating the field the client view's
 * replacement reads. Time-boxed — remove with _pppd_dev_prompt's meta box
 * era by 0.5.0; never mirror in the other direction.
 *
 * @param int    $meta_id  Meta row ID.
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @param mixed  $value    New value.
 * @return void
 */
function pppd_mirror_dev_prompt( $meta_id, $post_id, $meta_key, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( '_pppd_dev_prompt' !== $meta_key || 'pppd_section' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( ! is_string( $value ) || '' === $value ) {
		return;
	}

	$notes = get_post_meta( $post_id, '_pppd_impl_notes', true );

	if ( is_string( $notes ) && '' !== $notes ) {
		return;
	}

	update_post_meta( $post_id, '_pppd_impl_notes', $value );
}

/**
 * Run one-time upgrade routines when the stored plugin version is behind.
 *
 * 0.3.0: normalize table headers in all existing report/section content
 * (wp_update_post — revisions preserve the pre-repair markup) and seed
 * _pppd_impl_notes from _pppd_dev_prompt where empty.
 *
 * @return void
 */
function pppd_maybe_upgrade() {
	$stored = (string) get_option( 'pppd_version' );

	if ( version_compare( $stored, PPPD_VERSION, '>=' ) ) {
		return;
	}

	if ( version_compare( $stored, '0.3.0', '<' ) ) {
		$posts = get_posts(
			array(
				'post_type'      => array( 'pppd_report', 'pppd_section' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $posts as $post ) {
			$normalized = pppd_normalize_table_headers( $post->post_content );

			if ( $normalized !== $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_content' => $normalized,
					)
				);
			}

			if ( 'pppd_section' === $post->post_type ) {
				$prompt = get_post_meta( $post->ID, '_pppd_dev_prompt', true );
				$notes  = get_post_meta( $post->ID, '_pppd_impl_notes', true );

				if ( is_string( $prompt ) && '' !== $prompt && ( ! is_string( $notes ) || '' === $notes ) ) {
					update_post_meta( $post->ID, '_pppd_impl_notes', $prompt );
				}
			}
		}
	}

	update_option( 'pppd_version', PPPD_VERSION );
}
