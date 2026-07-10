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
