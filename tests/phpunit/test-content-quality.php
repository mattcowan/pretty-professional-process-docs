<?php
/**
 * Content quality: empty-<th> normalization at the source and the
 * dev-prompt → implementation-notes deprecation mirror.
 *
 * @package PPPD
 */

/**
 * Tests for includes/content-quality.php.
 */
class Test_Content_Quality extends WP_UnitTestCase {

	public function test_empty_th_cells_become_td_and_lose_scope() {
		$html = '<table><thead><tr><th scope="col"></th><th scope="col">Name</th><th> &nbsp; <br/> </th></tr></thead><tbody><tr><td>1</td><td>2</td><td>3</td></tr></tbody></table>';

		$fixed = pppd_normalize_table_headers( $html );

		$this->assertStringNotContainsString( '<th scope="col"></th>', $fixed );
		$this->assertStringContainsString( '<th scope="col">Name</th>', $fixed, 'Populated headers must survive untouched' );
		$this->assertSame( substr_count( $html, '<td' ) + 2, substr_count( $fixed, '<td' ), 'Two empty headers should have become td cells' );
		$this->assertStringNotContainsString( '<td scope', $fixed, 'scope must be dropped from converted cells' );
	}

	public function test_content_without_tables_is_untouched() {
		$html = '<p>No tables here &amp; nothing to do.</p>';

		$this->assertSame( $html, pppd_normalize_table_headers( $html ) );
	}

	public function test_normalization_runs_on_section_save_but_not_other_post_types() {
		$bad = '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th></th><th>Col</th></tr></thead><tbody><tr><td>a</td><td>b</td></tr></tbody></table></figure><!-- /wp:table -->';

		$section_id = self::factory()->post->create(
			array(
				'post_type'    => 'pppd_section',
				'post_content' => $bad,
			)
		);

		$this->assertStringNotContainsString( '<th></th>', get_post( $section_id )->post_content );
		$this->assertStringContainsString( '<th>Col</th>', get_post( $section_id )->post_content );

		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $bad,
			)
		);

		$this->assertStringContainsString( '<th></th>', get_post( $page_id )->post_content, 'Other post types are not the plugin\'s to rewrite' );
	}

	public function test_dev_prompt_mirrors_into_empty_impl_notes_only() {
		$section_id = self::factory()->post->create( array( 'post_type' => 'pppd_section' ) );

		update_post_meta( $section_id, '_pppd_dev_prompt', 'Build the switcher.' );

		$this->assertSame( 'Build the switcher.', get_post_meta( $section_id, '_pppd_impl_notes', true ) );

		// Existing notes are never overwritten.
		update_post_meta( $section_id, '_pppd_impl_notes', 'Curated notes.' );
		update_post_meta( $section_id, '_pppd_dev_prompt', 'Different prompt.' );

		$this->assertSame( 'Curated notes.', get_post_meta( $section_id, '_pppd_impl_notes', true ) );

		// One-directional: notes never flow back into the prompt.
		$this->assertSame( 'Different prompt.', get_post_meta( $section_id, '_pppd_dev_prompt', true ) );
	}

	public function test_upgrade_migration_fixes_existing_content_and_seeds_notes() {
		remove_filter( 'wp_insert_post_data', 'pppd_filter_post_data_tables', 10 );
		remove_action( 'added_post_meta', 'pppd_mirror_dev_prompt', 10 );
		remove_action( 'updated_post_meta', 'pppd_mirror_dev_prompt', 10 );

		$section_id = self::factory()->post->create(
			array(
				'post_type'    => 'pppd_section',
				'post_content' => '<table><thead><tr><th></th><th>Col</th></tr></thead></table>',
			)
		);
		update_post_meta( $section_id, '_pppd_dev_prompt', 'Legacy prompt.' );

		add_filter( 'wp_insert_post_data', 'pppd_filter_post_data_tables', 10, 2 );
		add_action( 'added_post_meta', 'pppd_mirror_dev_prompt', 10, 4 );
		add_action( 'updated_post_meta', 'pppd_mirror_dev_prompt', 10, 4 );

		update_option( 'pppd_version', '0.2.0' );
		pppd_maybe_upgrade();

		$this->assertStringNotContainsString( '<th></th>', get_post( $section_id )->post_content );
		$this->assertSame( 'Legacy prompt.', get_post_meta( $section_id, '_pppd_impl_notes', true ) );
		$this->assertSame( PPPD_VERSION, get_option( 'pppd_version' ) );
	}
}
