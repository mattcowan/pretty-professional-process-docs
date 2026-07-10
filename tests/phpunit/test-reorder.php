<?php
/**
 * Outline reorder endpoint: sibling moves, hierarchy moves, and its guards.
 *
 * @package PPPD
 */

/**
 * Tests for PPPD_Reorder_Controller.
 */
class Test_Reorder extends WP_UnitTestCase {

	/**
	 * Seed roles/caps the way activation does.
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
	}

	/**
	 * Report with three ordered top-level sections A, B, C.
	 *
	 * @return array{0:int,1:int[]} [report ID, section IDs in order].
	 */
	protected function fixture() {
		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);

		$ids = array();

		foreach ( array( 'A', 'B', 'C' ) as $i => $title ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'pppd_section',
					'post_status' => 'publish',
					'post_title'  => $title,
					'menu_order'  => ( $i + 1 ) * 10,
				)
			);
			update_post_meta( end( $ids ), '_pppd_report_id', $report_id );
		}

		return array( $report_id, $ids );
	}

	/**
	 * Dispatch a reorder call.
	 *
	 * @param int    $report_id  Report ID.
	 * @param int    $section_id Section ID.
	 * @param string $direction  Move direction.
	 * @return WP_REST_Response
	 */
	protected function reorder( $report_id, $section_id, $direction ) {
		$request = new WP_REST_Request( 'POST', '/pppd/v1/reports/' . $report_id . '/reorder' );
		$request->set_body_params(
			array(
				'section'   => $section_id,
				'direction' => $direction,
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The outline's section IDs in rendered order.
	 *
	 * @param int $report_id Report ID.
	 * @return int[]
	 */
	protected function order( $report_id ) {
		return array_map( 'intval', wp_list_pluck( pppd_get_authoring_outline( $report_id ), 'id' ) );
	}

	public function test_up_and_down_swap_siblings() {
		list( $report_id, $ids ) = $this->fixture();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 200, $this->reorder( $report_id, $ids[2], 'up' )->get_status() );
		$this->assertSame( array( $ids[0], $ids[2], $ids[1] ), $this->order( $report_id ) );

		$this->assertSame( 200, $this->reorder( $report_id, $ids[0], 'down' )->get_status() );
		$this->assertSame( array( $ids[2], $ids[0], $ids[1] ), $this->order( $report_id ) );
	}

	public function test_edge_moves_conflict_cleanly() {
		list( $report_id, $ids ) = $this->fixture();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 409, $this->reorder( $report_id, $ids[0], 'up' )->get_status() );
		$this->assertSame( 409, $this->reorder( $report_id, $ids[0], 'outdent' )->get_status() );
		$this->assertSame( 409, $this->reorder( $report_id, $ids[0], 'indent' )->get_status(), 'First sibling has nothing to nest under' );
	}

	public function test_indent_nests_under_previous_sibling_and_outdent_reverses() {
		list( $report_id, $ids ) = $this->fixture();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 200, $this->reorder( $report_id, $ids[1], 'indent' )->get_status() );

		$outline = pppd_get_authoring_outline( $report_id );
		$by_id   = array_column( $outline, null, 'id' );

		$this->assertSame( $ids[0], $by_id[ $ids[1] ]['parent'] );
		$this->assertSame( 1, $by_id[ $ids[1] ]['depth'] );

		$this->assertSame( 200, $this->reorder( $report_id, $ids[1], 'outdent' )->get_status() );

		$outline = pppd_get_authoring_outline( $report_id );
		$by_id   = array_column( $outline, null, 'id' );

		$this->assertSame( 0, $by_id[ $ids[1] ]['parent'] );
		$this->assertSame( array( $ids[0], $ids[1], $ids[2] ), $this->order( $report_id ), 'Outdent lands directly after the old parent' );
	}

	public function test_cross_report_moves_are_rejected() {
		list( $report_id )       = $this->fixture();
		list( , $foreign_ids )   = $this->fixture();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 409, $this->reorder( $report_id, $foreign_ids[0], 'up' )->get_status() );
	}

	public function test_clients_cannot_reorder() {
		list( $report_id, $ids ) = $this->fixture();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->reorder( $report_id, $ids[1], 'up' )->get_status() );
	}
}
