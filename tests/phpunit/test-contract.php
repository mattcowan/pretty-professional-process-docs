<?php
/**
 * Contract guard: asserts the frozen REST surface documented in
 * docs/rest-contract.md against the live registrations.
 *
 * If a change makes this fail, either the change broke the v1 contract
 * (stop — that needs a major version + lockstep frd-skill update) or it
 * added surface (update the fixture AND docs/rest-contract.md additively).
 *
 * @package PPPD
 */

/**
 * Tests pinning the v1 REST contract.
 */
class Test_Contract extends WP_UnitTestCase {

	/**
	 * Seed roles/caps and default terms the way activation does, and re-run
	 * the per-request init registrations the test suite tears down between
	 * classes (it unregisters all meta keys and deletes all terms, but the
	 * registry's hash-gate option survives — so force a re-sync).
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
		pppd_insert_default_terms();
		pppd_register_meta();
		pppd_register_client_meta();
		pppd_register_signoff_meta();
		pppd_register_github_meta();

		delete_option( 'pppd_registered_type_hash' );
		pppd_registry_apply();
	}

	public function test_post_types_and_rest_bases() {
		$expected = array(
			'pppd_report'  => 'pppd-reports',
			'pppd_section' => 'pppd-sections',
			'pppd_change'  => 'pppd-changes',
			'pppd_drift'   => 'pppd-drift',
		);

		foreach ( $expected as $post_type => $rest_base ) {
			$object = get_post_type_object( $post_type );

			$this->assertNotNull( $object, "Post type {$post_type} missing" );
			$this->assertTrue( (bool) $object->show_in_rest, "{$post_type} not REST-exposed" );
			$this->assertSame( $rest_base, $object->rest_base, "{$post_type} rest_base changed" );
		}
	}

	public function test_taxonomies_and_frozen_term_slugs() {
		foreach ( array( 'pppd_section_type', 'pppd_status', 'pppd_report_type', 'pppd_client' ) as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), "Taxonomy {$taxonomy} missing" );
		}

		foreach ( array( 'narrative', 'requirement', 'decision' ) as $slug ) {
			$this->assertNotNull( term_exists( $slug, 'pppd_section_type' ), "Frozen section type term {$slug} missing" );
		}

		foreach ( array( 'draft', 'agreed', 'at-risk', 'built', 'verified' ) as $slug ) {
			$this->assertNotNull( term_exists( $slug, 'pppd_status' ), "Frozen status term {$slug} missing" );
		}

		foreach ( array( 'frd', 'user-access-model', 'change-order', 'content-strategy' ) as $slug ) {
			$this->assertNotNull( term_exists( $slug, 'pppd_report_type' ), "Report type term {$slug} missing" );
		}
	}

	public function test_registered_meta_keys() {
		$expected = array(
			'pppd_section' => array( '_pppd_report_id', '_pppd_req_id', '_pppd_acceptance', '_pppd_dev_prompt', '_pppd_impl_notes', '_pppd_internal', '_pppd_code_refs', '_pppd_test_refs', '_pppd_approved_by', '_pppd_approved_at', '_pppd_approved_revision', '_pppd_reapproval_source', '_pppd_github_status', '_pppd_github_issue_url', '_pppd_github_issue_number' ),
			'pppd_change'  => array( '_pppd_target_section', '_pppd_source', '_pppd_rationale', '_pppd_reject_reason', '_pppd_applied_revision', '_pppd_reviewed_by', '_pppd_reviewed_at' ),
			'pppd_report'  => array( '_pppd_repo_paths', '_pppd_req_prefix', '_pppd_req_counter', '_pppd_project_slug', '_pppd_pdf_url', '_pppd_assigned_user_ids', '_pppd_github_repo', '_pppd_github_trigger', '_pppd_public' ),
			'pppd_drift'   => array( '_pppd_report_id', '_pppd_drift_json' ),
		);

		foreach ( $expected as $subtype => $keys ) {
			foreach ( $keys as $key ) {
				$this->assertTrue(
					registered_meta_key_exists( 'post', $key, $subtype ),
					"Meta {$key} missing on {$subtype}"
				);
			}
		}
	}

	public function test_custom_routes_exist() {
		$routes = rest_get_server()->get_routes( 'pppd/v1' );

		$expected = array(
			'/pppd/v1/reports/(?P<id>\d+)/outline',
			'/pppd/v1/reports/(?P<id>\d+)/traceability',
			'/pppd/v1/reports/(?P<id>\d+)/drift',
			'/pppd/v1/reports/(?P<id>\d+)/drift/latest',
			'/pppd/v1/changes/(?P<id>\d+)/approve',
			'/pppd/v1/changes/(?P<id>\d+)/reject',
			'/pppd/v1/reports/(?P<id>\d+)/reorder',
			'/pppd/v1/github/queue',
			'/pppd/v1/github/queue/(?P<section>\d+)/pushed',
		);

		foreach ( $expected as $route ) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} missing" );
		}
	}

	public function test_agent_role_can_edit_but_never_delete_or_approve() {
		$role = get_role( 'pppd_agent' );

		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( 'edit_pppd_reports' ) );
		$this->assertTrue( $role->has_cap( 'publish_pppd_reports' ) );
		$this->assertTrue( $role->has_cap( 'create_pppd_reports' ) );
		$this->assertFalse( $role->has_cap( 'delete_pppd_reports' ) );
		$this->assertFalse( $role->has_cap( 'pppd_approve_changes' ) );
	}

	public function test_agents_cannot_update_published_sections_via_rest() {
		$agent_id = self::factory()->user->create( array( 'role' => 'pppd_agent' ) );

		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'publish',
				'post_title'  => 'Published section',
			)
		);

		wp_set_current_user( $agent_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pppd-sections/' . $section_id );
		$request->set_body_params( array( 'content' => 'agent rewrite attempt' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'pppd_approval_required', $response->get_data()['code'] );
	}

	public function test_agents_can_create_sections_and_edit_drafts_via_rest() {
		$agent_id = self::factory()->user->create( array( 'role' => 'pppd_agent' ) );
		wp_set_current_user( $agent_id );

		$create = new WP_REST_Request( 'POST', '/wp/v2/pppd-sections' );
		$create->set_body_params(
			array(
				'title'  => 'Agent-created section',
				'status' => 'draft',
			)
		);

		$created = rest_get_server()->dispatch( $create );
		$this->assertSame( 201, $created->get_status() );

		$section_id = (int) $created->get_data()['id'];

		$update = new WP_REST_Request( 'POST', '/wp/v2/pppd-sections/' . $section_id );
		$update->set_body_params( array( 'content' => 'draft edit' ) );

		$this->assertSame( 200, rest_get_server()->dispatch( $update )->get_status() );
	}

	public function test_outline_response_shape_and_contract_version() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $admin_id );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' )
		);

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( PPPD_CONTRACT_VERSION, $data['pppd_contract_version'] );
		$this->assertSame( 'frd', $data['report']['type'] );

		foreach ( array( 'id', 'title', 'link', 'project_slug' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['report'], "Outline report.{$key} missing" );
		}

		$this->assertArrayHasKey( 'sections', $data );
	}

	public function test_outline_section_rows_carry_both_status_axes() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$report_id  = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $section_id, '_pppd_report_id', $report_id );

		wp_set_current_user( $admin_id );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' )
		);

		$rows = $response->get_data()['sections'];

		$this->assertCount( 1, $rows );
		// Both axes: `status` is the pppd_status workflow term, `post_status`
		// (additive, v0.4.0) is the WordPress publication state.
		$this->assertArrayHasKey( 'status', $rows[0] );
		$this->assertSame( 'publish', $rows[0]['post_status'] );
	}

	public function test_outline_rejects_invalid_status_values() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/outline' );
		$request->set_query_params( array( 'status' => 'bogus' ) );

		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_change_approval_stamps_reviewer_timestamp_and_fires_hook() {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$report_id  = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $section_id, '_pppd_report_id', $report_id );

		$change_id = self::factory()->post->create(
			array(
				'post_type'    => 'pppd_change',
				'post_status'  => 'pending',
				'post_content' => 'proposed new content',
			)
		);
		update_post_meta( $change_id, '_pppd_target_section', $section_id );

		$fired = did_action( 'pppd_change_approved' );

		$summary = pppd_approve_change( $change_id, $editor_id );

		$this->assertIsArray( $summary );
		$this->assertSame( $editor_id, $summary['reviewed_by'] );
		$this->assertNotSame( '', $summary['reviewed_at'], 'reviewed_at timestamp missing' );
		$this->assertSame( $fired + 1, did_action( 'pppd_change_approved' ) );
		$this->assertSame( 'proposed new content', get_post( $section_id )->post_content );
	}
}
