<?php
/**
 * Client entity: membership, per-report assignment, and the shared
 * access-check helper the Phase 3 read guards will route through.
 *
 * @package PPPD
 */

/**
 * Tests for pppd_user_can_view_report() and client membership plumbing.
 */
class Test_Clients extends WP_UnitTestCase {

	/**
	 * Seed roles/caps the way activation does.
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
	}

	/**
	 * Create a client term, a report attached to it, and a subscriber.
	 *
	 * @return array{0:int,1:int,2:int} [client term ID, report ID, user ID].
	 */
	protected function fixture() {
		$term = wp_insert_term( 'Acme Co', 'pppd_client', array( 'slug' => 'acme-co' ) );

		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $report_id, (int) $term['term_id'], 'pppd_client' );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		return array( (int) $term['term_id'], $report_id, $user_id );
	}

	public function test_client_members_can_view_their_client_reports() {
		list( $term_id, $report_id, $user_id ) = $this->fixture();

		$this->assertFalse( pppd_user_can_view_report( $user_id, $report_id ) );

		update_user_meta( $user_id, '_pppd_client_ids', array( $term_id ) );

		$this->assertTrue( pppd_user_can_view_report( $user_id, $report_id ) );
	}

	public function test_membership_does_not_leak_to_other_clients_reports() {
		list( , , $user_id ) = $this->fixture();

		$other = wp_insert_term( 'Other LLC', 'pppd_client', array( 'slug' => 'other-llc' ) );
		update_user_meta( $user_id, '_pppd_client_ids', array( (int) $other['term_id'] ) );

		$unrelated_report = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);

		$acme_report_ids = get_posts(
			array(
				'post_type' => 'pppd_report',
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'pppd_client',
						'field'    => 'slug',
						'terms'    => 'acme-co',
					),
				),
				'fields'    => 'ids',
			)
		);

		$this->assertFalse( pppd_user_can_view_report( $user_id, (int) $acme_report_ids[0] ) );
		$this->assertFalse( pppd_user_can_view_report( $user_id, $unrelated_report ) );
	}

	public function test_per_report_assignment_grants_access_without_membership() {
		list( , $report_id, $user_id ) = $this->fixture();

		update_post_meta( $report_id, '_pppd_assigned_user_ids', array( $user_id ) );

		$this->assertTrue( pppd_user_can_view_report( $user_id, $report_id ) );
	}

	public function test_report_editors_always_have_access() {
		list( , $report_id ) = $this->fixture();

		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$agent_id  = self::factory()->user->create( array( 'role' => 'pppd_agent' ) );

		$this->assertTrue( pppd_user_can_view_report( $editor_id, $report_id ) );
		$this->assertTrue( pppd_user_can_view_report( $agent_id, $report_id ) );
	}

	public function test_deleting_a_client_term_cleans_up_membership_meta() {
		list( $term_id, , $user_id ) = $this->fixture();

		$other = wp_insert_term( 'Keep Me', 'pppd_client', array( 'slug' => 'keep-me' ) );
		update_user_meta( $user_id, '_pppd_client_ids', array( $term_id, (int) $other['term_id'] ) );

		wp_delete_term( $term_id, 'pppd_client' );

		$this->assertSame( array( (int) $other['term_id'] ), pppd_get_user_client_ids( $user_id ) );
	}
}
