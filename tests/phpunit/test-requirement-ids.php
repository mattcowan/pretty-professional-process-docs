<?php
/**
 * Requirement/item ID invariants.
 *
 * The pre-registry behavior is a REST-contract invariant (docs/rest-contract.md):
 * requirement sections in an FRD get FR-001, FR-002, … from the per-report
 * _pppd_req_counter meta, assigned on first publish, never reassigned.
 *
 * @package PPPD
 */

/**
 * Tests for item ID assignment.
 */
class Test_Requirement_Ids extends WP_UnitTestCase {

	/**
	 * Create a report post.
	 *
	 * @param string $report_type Optional pppd_report_type term slug.
	 * @return int Report ID.
	 */
	protected function create_report( $report_type = '' ) {
		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
				'post_title'  => 'Test Report',
			)
		);

		if ( '' !== $report_type ) {
			wp_set_object_terms( $report_id, $report_type, 'pppd_report_type' );
		}

		return $report_id;
	}

	/**
	 * Create a section as draft, type it, then publish (exercising the
	 * save_post_pppd_section hook path IDs are assigned on).
	 *
	 * @param int    $report_id Report ID.
	 * @param string $type      Section type term slug.
	 * @return int Section ID.
	 */
	protected function create_published_section( $report_id, $type ) {
		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'draft',
				'post_title'  => 'Test Section',
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

	public function test_frd_requirements_get_sequential_fr_ids_from_legacy_counter() {
		$report_id = $this->create_report();

		$first  = $this->create_published_section( $report_id, 'requirement' );
		$second = $this->create_published_section( $report_id, 'requirement' );

		$this->assertSame( 'FR-001', get_post_meta( $first, '_pppd_req_id', true ) );
		$this->assertSame( 'FR-002', get_post_meta( $second, '_pppd_req_id', true ) );
		$this->assertSame( 2, (int) get_post_meta( $report_id, '_pppd_req_counter', true ) );
	}

	public function test_explicit_report_prefix_overrides_and_counter_continues() {
		$report_id = $this->create_report();

		$this->create_published_section( $report_id, 'requirement' );

		update_post_meta( $report_id, '_pppd_req_prefix', 'REQ' );

		$second = $this->create_published_section( $report_id, 'requirement' );

		$this->assertSame( 'REQ-002', get_post_meta( $second, '_pppd_req_id', true ) );
	}

	public function test_ids_are_never_reassigned() {
		$report_id  = $this->create_report();
		$section_id = $this->create_published_section( $report_id, 'requirement' );

		update_post_meta( $report_id, '_pppd_req_prefix', 'REQ' );
		wp_update_post( array( 'ID' => $section_id ) ); // Re-save.

		$this->assertSame( 'FR-001', get_post_meta( $section_id, '_pppd_req_id', true ) );
		$this->assertSame( 1, (int) get_post_meta( $report_id, '_pppd_req_counter', true ) );
	}

	public function test_narrative_sections_get_no_id() {
		$report_id  = $this->create_report();
		$section_id = $this->create_published_section( $report_id, 'narrative' );

		$this->assertSame( '', get_post_meta( $section_id, '_pppd_req_id', true ) );
	}

	public function test_draft_sections_get_no_id_until_publish() {
		$report_id  = $this->create_report();
		$section_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_section',
				'post_status' => 'draft',
			)
		);

		update_post_meta( $section_id, '_pppd_report_id', $report_id );
		wp_set_object_terms( $section_id, 'requirement', 'pppd_section_type' );
		wp_update_post( array( 'ID' => $section_id ) ); // Still draft.

		$this->assertSame( '', get_post_meta( $section_id, '_pppd_req_id', true ) );
	}

	public function test_change_order_reports_use_their_id_scheme_prefix() {
		$report_id = $this->create_report( 'change-order' );

		$section_id = $this->create_published_section( $report_id, 'requirement' );

		$this->assertSame( 'CO-001', get_post_meta( $section_id, '_pppd_req_id', true ) );
		// Requirement sections keep the legacy counter key regardless of prefix.
		$this->assertSame( 1, (int) get_post_meta( $report_id, '_pppd_req_counter', true ) );
	}

	public function test_item_id_assigned_action_fires() {
		$report_id = $this->create_report();
		$fired     = array();

		add_action(
			'pppd_item_id_assigned',
			static function ( $post_id, $item_id, $for_report ) use ( &$fired ) {
				$fired = array( $post_id, $item_id, $for_report );
			},
			10,
			3
		);

		$section_id = $this->create_published_section( $report_id, 'requirement' );

		$this->assertSame( array( $section_id, 'FR-001', $report_id ), $fired );
	}
}
