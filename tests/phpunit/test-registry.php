<?php
/**
 * Registry acceptance: a third party registers a new report type + section
 * (item) type without editing plugin core, and everything downstream — term
 * sync, field meta, ID assignment, traceability — works.
 *
 * @package PPPD
 */

/**
 * Tests for the report-type/section-type registry.
 */
class Test_Registry extends WP_UnitTestCase {

	/**
	 * Seed roles/caps the way activation does (the traceability route is
	 * team-only since 0.3.0).
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
	}

	/**
	 * Register the third-party fixture types (security-audit / finding) the
	 * way an extension would, then apply registry side effects.
	 *
	 * @return void
	 */
	protected function register_third_party_types() {
		pppd_register_section_type(
			'finding',
			array(
				'label'        => 'Finding',
				'is_item'      => true,
				'has_ids'      => true,
				'id_prefix'    => 'SEC',
				'traceable'    => true,
				'meta_partial' => PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php',
				'fields'       => array(
					'_pppd_severity'    => array(
						'type'       => 'string',
						'visibility' => 'client',
					),
					'_pppd_remediation' => array(
						'type'       => 'string',
						'visibility' => 'internal',
					),
				),
			)
		);

		pppd_register_report_type(
			'security-audit',
			array(
				'label'                => 'Security Audit',
				'section_types'        => array( 'narrative', 'finding' ),
				'default_section_type' => 'finding',
				'id_scheme'            => array(
					'prefix' => 'SA',
					'format' => '%s-%03d',
				),
			)
		);

		delete_option( 'pppd_registered_type_hash' );
		pppd_registry_apply();
	}

	/**
	 * Create a published, typed section attached to a report.
	 *
	 * @param int    $report_id Report ID.
	 * @param string $type      Section type slug.
	 * @return int Section ID.
	 */
	protected function create_published_section( $report_id, $type ) {
		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'draft',
			)
		);

		update_post_meta( $section_id, '_pppd_report_id', $report_id );
		wp_set_object_terms( $section_id, $type, 'pppd_section_type' );
		wp_update_post(
			array(
				'ID'          => $section_id,
				'post_status' => 'publish',
			)
		);

		return $section_id;
	}

	public function test_builtin_types_are_registered_through_the_public_api() {
		$registry = pppd_registry();

		foreach ( array( 'frd', 'user-access-model', 'change-order', 'content-strategy' ) as $slug ) {
			$this->assertNotNull( $registry->get_report_type( $slug ), "Report type {$slug} missing" );
		}

		foreach ( array( 'narrative', 'requirement', 'decision' ) as $slug ) {
			$this->assertNotNull( $registry->get_section_type( $slug ), "Section type {$slug} missing" );
		}

		$this->assertTrue( $registry->section_type_supports( 'requirement', 'has_ids' ) );
		$this->assertTrue( $registry->section_type_supports( 'requirement', 'traceable' ) );
		$this->assertFalse( $registry->section_type_supports( 'narrative', 'has_ids' ) );
		$this->assertFalse( $registry->section_type_supports( 'unregistered-type', 'has_ids' ) );
	}

	public function test_third_party_type_syncs_terms_and_registers_field_meta() {
		$this->register_third_party_types();

		$this->assertNotNull( term_exists( 'finding', 'pppd_section_type' ) );
		$this->assertNotNull( term_exists( 'security-audit', 'pppd_report_type' ) );

		$this->assertTrue( registered_meta_key_exists( 'post', '_pppd_severity', 'pppd_section' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', '_pppd_remediation', 'pppd_section' ) );

		$this->assertSame( array( '_pppd_remediation' ), pppd_get_internal_field_keys( 'finding' ) );
	}

	public function test_third_party_items_get_ids_from_their_own_prefix_and_counter() {
		$this->register_third_party_types();

		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $report_id, 'security-audit', 'pppd_report_type' );

		$first  = $this->create_published_section( $report_id, 'finding' );
		$second = $this->create_published_section( $report_id, 'finding' );

		$this->assertSame( 'SEC-001', get_post_meta( $first, '_pppd_req_id', true ) );
		$this->assertSame( 'SEC-002', get_post_meta( $second, '_pppd_req_id', true ) );

		// Non-requirement types count per prefix, not in the legacy counter.
		$this->assertSame( 2, (int) get_post_meta( $report_id, '_pppd_counter_sec', true ) );
		$this->assertSame( '', (string) get_post_meta( $report_id, '_pppd_req_counter', true ) );
	}

	public function test_third_party_traceable_items_appear_in_the_traceability_matrix() {
		$this->register_third_party_types();

		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $report_id, 'security-audit', 'pppd_report_type' );

		$this->create_published_section( $report_id, 'finding' );
		$this->create_published_section( $report_id, 'narrative' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request  = new WP_REST_Request( 'GET', '/pppd/v1/reports/' . $report_id . '/traceability' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$rows = $response->get_data();

		$this->assertCount( 1, $rows, 'Only the traceable finding should produce a row' );
		$this->assertSame( 'SEC-001', $rows[0]['req_id'] );
		$this->assertSame( 'finding', $rows[0]['type'] );
	}

	public function test_section_meta_partial_resolves_from_the_registry() {
		$this->register_third_party_types();

		$report_id = self::factory()->post->create( array( 'post_type' => 'pppd_report' ) );

		$requirement = $this->create_published_section( $report_id, 'requirement' );
		$narrative   = $this->create_published_section( $report_id, 'narrative' );
		$finding     = $this->create_published_section( $report_id, 'finding' );

		$this->assertSame(
			PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php',
			pppd_get_section_meta_partial( get_post( $requirement ) )
		);
		$this->assertSame( '', pppd_get_section_meta_partial( get_post( $narrative ) ) );
		$this->assertSame(
			PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php',
			pppd_get_section_meta_partial( get_post( $finding ) )
		);
	}

	public function test_reports_without_a_type_term_default_to_frd() {
		$report_id = self::factory()->post->create( array( 'post_type' => 'pppd_report' ) );

		$this->assertSame( 'frd', pppd_get_report_type( $report_id ) );

		$type = pppd_get_report_type_object( $report_id );

		$this->assertSame( 'FR', $type['id_scheme']['prefix'] );
	}

	/**
	 * Must run LAST in this class: the registry singleton persists for the
	 * whole PHP process, so the deliberately-broken fixture types registered
	 * here would make any later pppd_registry_apply() call re-trigger the
	 * _doing_it_wrong this test expects.
	 */
	public function test_duplicate_id_prefixes_within_a_report_type_trigger_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'pppd_register_report_type' );

		pppd_register_section_type(
			'finding-a',
			array(
				'has_ids'   => true,
				'id_prefix' => 'DUP',
			)
		);
		pppd_register_section_type(
			'finding-b',
			array(
				'has_ids'   => true,
				'id_prefix' => 'DUP',
			)
		);
		pppd_register_report_type(
			'dup-report',
			array(
				'section_types' => array( 'finding-a', 'finding-b' ),
			)
		);

		pppd_validate_registry();
	}
}
