<?php
/**
 * Client access control: REST read guards, internal-content filtering, and
 * the tightened pppd/v1 read routes.
 *
 * @package PPPD
 */

/**
 * Tests for includes/access.php.
 */
class Test_Access extends WP_UnitTestCase {

	/**
	 * Seed roles/caps the way activation does.
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
		// The suite unregisters all meta between tests; the REST meta-write
		// tests need the plugin registrations back (same pattern as
		// Test_Contract).
		pppd_register_meta();
		pppd_register_client_meta();
		pppd_register_signoff_meta();
		pppd_register_github_meta();
	}

	/**
	 * Client term + report + member subscriber + outsider subscriber.
	 *
	 * @return array{0:int,1:int,2:int} [report ID, member user ID, outsider user ID].
	 */
	protected function fixture() {
		$term = wp_insert_term( 'Acme', 'pppd_client', array( 'slug' => 'acme' ) );

		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $report_id, (int) $term['term_id'], 'pppd_client' );

		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $member, '_pppd_client_ids', array( (int) $term['term_id'] ) );

		$outsider = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		return array( $report_id, $member, $outsider );
	}

	public function test_outline_is_scoped_to_report_viewers() {
		list( $report_id, $member, $outsider ) = $this->fixture();

		$request = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' );

		wp_set_current_user( $member );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( $outsider );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_traceability_and_drift_are_team_only() {
		list( $report_id, $member ) = $this->fixture();

		wp_set_current_user( $member );

		foreach ( array( 'traceability', 'drift/latest' ) as $route ) {
			$response = rest_get_server()->dispatch(
				new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/' . $route )
			);

			$this->assertSame( 403, $response->get_status(), "Client members must not read {$route} (internal tooling)" );
		}
	}

	public function test_core_rest_single_report_requires_view_access() {
		list( $report_id, $member, $outsider ) = $this->fixture();

		$request = new WP_REST_Request( 'GET', '/wp/v2/pppd-reports/' . $report_id );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( $outsider );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );

		wp_set_current_user( $member );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_core_rest_collection_is_scoped_to_viewable_reports() {
		list( $report_id, $member ) = $this->fixture();

		// A second report belonging to nobody the member knows.
		self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $member );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pppd-reports' ) );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertSame( array( $report_id ), array_values( array_map( 'intval', $ids ) ) );
	}

	public function test_internal_sections_are_hidden_from_clients_but_not_team() {
		list( $report_id, $member ) = $this->fixture();

		$visible  = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'publish',
			)
		);
		$internal = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $visible, '_pppd_report_id', $report_id );
		update_post_meta( $internal, '_pppd_report_id', $report_id );
		update_post_meta( $internal, '_pppd_internal', 1 );

		$sections = pppd_get_report_section_posts( $report_id );

		wp_set_current_user( $member );
		$this->assertSame( array( $visible ), wp_list_pluck( pppd_filter_visible_sections( $sections ), 'ID' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertCount( 2, pppd_filter_visible_sections( $sections ) );
	}

	/**
	 * Create a section attached to a report.
	 *
	 * @param int    $report_id Report ID.
	 * @param string $status    Post status.
	 * @param bool   $internal  Whether the section is team-only.
	 * @return int Section ID.
	 */
	protected function create_section( $report_id, $status = 'publish', $internal = false ) {
		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => $status,
			)
		);
		update_post_meta( $section_id, '_pppd_report_id', $report_id );

		if ( $internal ) {
			update_post_meta( $section_id, '_pppd_internal', 1 );
		}

		return $section_id;
	}

	public function test_outline_defaults_to_published_sections_only() {
		list( $report_id ) = $this->fixture();

		$published = $this->create_section( $report_id, 'publish' );
		$this->create_section( $report_id, 'draft' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $published ), wp_list_pluck( $response->get_data()['sections'], 'id' ) );
	}

	public function test_outline_status_param_returns_drafts_for_team() {
		list( $report_id ) = $this->fixture();

		$published = $this->create_section( $report_id, 'publish' );
		$draft     = $this->create_section( $report_id, 'draft' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' );
		$request->set_query_params( array( 'status' => 'publish,draft' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$rows = $response->get_data()['sections'];

		$this->assertEqualSets( array( $published, $draft ), wp_list_pluck( $rows, 'id' ) );

		$statuses = array_column( $rows, 'post_status', 'id' );
		$this->assertSame( 'draft', $statuses[ $draft ] );
		$this->assertSame( 'publish', $statuses[ $published ] );
	}

	public function test_outline_status_param_is_403_for_non_team() {
		list( $report_id, $member ) = $this->fixture();

		wp_set_current_user( $member );

		$request = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' );
		$request->set_query_params( array( 'status' => 'draft' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'pppd_forbidden_status', $response->get_data()['code'] );
	}

	public function test_outline_rows_hide_internal_sections_from_clients() {
		list( $report_id, $member ) = $this->fixture();

		$visible  = $this->create_section( $report_id, 'publish' );
		$internal = $this->create_section( $report_id, 'publish', true );

		$request = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' );

		wp_set_current_user( $member );
		$this->assertSame(
			array( $visible ),
			wp_list_pluck( rest_get_server()->dispatch( $request )->get_data()['sections'], 'id' ),
			'Client members must not receive rows for internal sections'
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertEqualSets(
			array( $visible, $internal ),
			wp_list_pluck( rest_get_server()->dispatch( $request )->get_data()['sections'], 'id' )
		);
	}

	public function test_render_statuses_are_publish_only_for_clients_and_anonymous() {
		list( $report_id, $member ) = $this->fixture();

		wp_set_current_user( 0 );
		$this->assertSame( 'publish', pppd_report_render_statuses( $report_id ) );

		wp_set_current_user( $member );
		$this->assertSame( 'publish', pppd_report_render_statuses( $report_id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( pppd_authoring_statuses(), pppd_report_render_statuses( $report_id ) );

		// The client preview switches a team viewer back to the client render.
		$_GET['pppd_preview'] = 'published';
		$this->assertSame( 'publish', pppd_report_render_statuses( $report_id ) );
		unset( $_GET['pppd_preview'] );
	}

	public function test_draft_flag_renders_in_section_partial() {
		list( $report_id ) = $this->fixture();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$pppd_report = get_post( $report_id );
		$pppd_depth  = 0;

		$pppd_section = get_post( $this->create_section( $report_id, 'draft' ) );
		ob_start();
		require PPPD_PLUGIN_DIR . 'templates/partials/section.php';
		$draft_html = ob_get_clean();

		$this->assertStringContainsString( 'not part of the signed document', $draft_html );
		$this->assertStringContainsString( 'pppd-section--unpublished', $draft_html );
		$this->assertStringContainsString( 'pppd-section-id-' . $pppd_section->ID, $draft_html, 'Draft sections need an ID-based anchor (empty slug)' );

		$pppd_section = get_post( $this->create_section( $report_id, 'publish' ) );
		ob_start();
		require PPPD_PLUGIN_DIR . 'templates/partials/section.php';
		$published_html = ob_get_clean();

		$this->assertStringNotContainsString( 'not part of the signed document', $published_html );
		$this->assertStringNotContainsString( 'pppd-section--unpublished', $published_html );
	}

	public function test_public_flag_opens_read_surfaces_to_anonymous() {
		list( $report_id ) = $this->fixture();

		wp_set_current_user( 0 );

		// Unflagged: anonymous is blocked everywhere.
		$this->assertFalse( pppd_report_is_public( $report_id ) );
		$single = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pppd-reports/' . $report_id ) );
		$this->assertSame( 401, $single->get_status() );

		update_post_meta( $report_id, '_pppd_public', 1 );

		$this->assertTrue( pppd_report_is_public( $report_id ) );
		$single = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pppd-reports/' . $report_id ) );
		$this->assertSame( 200, $single->get_status() );

		$outline = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' ) );
		$this->assertSame( 200, $outline->get_status() );
	}

	public function test_public_report_never_exposes_drafts_or_internal_to_anonymous() {
		list( $report_id ) = $this->fixture();
		update_post_meta( $report_id, '_pppd_public', 1 );

		$visible = $this->create_section( $report_id, 'publish' );
		$this->create_section( $report_id, 'publish', true ); // internal
		$this->create_section( $report_id, 'draft' );

		wp_set_current_user( 0 );

		// Outline rows: published + non-internal only.
		$outline = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' ) );
		$this->assertSame( array( $visible ), wp_list_pluck( $outline->get_data()['sections'], 'id' ) );

		// The status param stays team-only even on a public report.
		$request = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' );
		$request->set_query_params( array( 'status' => 'draft' ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );

		// Front-end render statuses stay publish-only for anonymous.
		$this->assertSame( 'publish', pppd_report_render_statuses( $report_id ) );
	}

	public function test_public_flag_only_applies_to_published_reports() {
		$draft_report = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $draft_report, '_pppd_public', 1 );

		$this->assertFalse( pppd_report_is_public( $draft_report ), 'A draft report is never public, flag or no flag' );
	}

	public function test_public_flag_is_not_writable_by_the_agent_role() {
		list( $report_id ) = $this->fixture();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'pppd_agent' ) ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pppd-reports/' . $report_id );
		$request->set_body_params( array( 'meta' => array( '_pppd_public' => true ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Agents must never be able to expose a report publicly' );
		$this->assertEmpty( get_post_meta( $report_id, '_pppd_public', true ) );
	}

	public function test_agents_and_editors_pass_every_view_gate() {
		list( $report_id ) = $this->fixture();
		$agent_id          = self::factory()->user->create( array( 'role' => 'pppd_agent' ) );

		wp_set_current_user( $agent_id );

		$outline = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' )
		);

		$this->assertSame( 200, $outline->get_status() );

		$trace = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/traceability' )
		);

		$this->assertSame( 200, $trace->get_status() );
	}
}
