<?php
/**
 * GitHub queue: eligibility, triggers, idempotent push marking, and the
 * agent-facing REST surface. The plugin never holds a GitHub credential.
 *
 * @package PPPD
 */

/**
 * Tests for includes/github-queue.php + the queue controller.
 */
class Test_Github_Queue extends WP_UnitTestCase {

	/**
	 * Seed roles/caps the way activation does.
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
	}

	/**
	 * Report (with repo) + signed-off published section.
	 *
	 * @param string $trigger Trigger mode.
	 * @return array{0:int,1:int} [report ID, section ID].
	 */
	protected function fixture( $trigger = 'manual' ) {
		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $report_id, '_pppd_github_repo', 'mattcowan/example-app' );
		update_post_meta( $report_id, '_pppd_github_trigger', $trigger );

		$section_id = self::factory()->post->create(
			array(
				'post_type'    => 'pppd_section',
				'post_status'  => 'publish',
				'post_title'   => 'Member can switch company',
				'post_content' => 'The switcher lists all companies.',
				'post_date'    => '2026-07-01 09:00:00',
			)
		);
		update_post_meta( $section_id, '_pppd_report_id', $report_id );

		return array( $report_id, $section_id );
	}

	public function test_only_signed_off_sections_are_eligible() {
		list( , $section_id ) = $this->fixture();

		$result = pppd_queue_section_for_github( $section_id );
		$this->assertWPError( $result );
		$this->assertSame( 'pppd_not_approved', $result->get_error_code() );

		pppd_sign_off_section( $section_id, self::factory()->user->create() );

		$this->assertTrue( pppd_queue_section_for_github( $section_id, 1 ) );
		$this->assertSame( 'queued', get_post_meta( $section_id, '_pppd_github_status', true ) );

		// Never double-queue.
		$again = pppd_queue_section_for_github( $section_id, 1 );
		$this->assertWPError( $again );
		$this->assertSame( 'pppd_already_queued', $again->get_error_code() );
	}

	public function test_missing_repo_blocks_queueing() {
		list( $report_id, $section_id ) = $this->fixture();
		delete_post_meta( $report_id, '_pppd_github_repo' );

		pppd_sign_off_section( $section_id, self::factory()->user->create() );

		$result = pppd_queue_section_for_github( $section_id );

		$this->assertWPError( $result );
		$this->assertSame( 'pppd_no_repo', $result->get_error_code() );
	}

	public function test_auto_trigger_queues_on_signoff_manual_does_not() {
		list( , $auto_section ) = $this->fixture( 'auto' );
		pppd_sign_off_section( $auto_section, self::factory()->user->create() );

		$this->assertSame( 'queued', get_post_meta( $auto_section, '_pppd_github_status', true ) );

		list( , $manual_section ) = $this->fixture( 'manual' );
		pppd_sign_off_section( $manual_section, self::factory()->user->create() );

		$this->assertSame( '', get_post_meta( $manual_section, '_pppd_github_status', true ) );
	}

	public function test_queue_rest_surface_is_agent_readable_and_idempotent() {
		list( , $section_id ) = $this->fixture();
		pppd_sign_off_section( $section_id, self::factory()->user->create() );
		pppd_queue_section_for_github( $section_id, 1 );

		$agent_id = self::factory()->user->create( array( 'role' => 'pppd_agent' ) );
		wp_set_current_user( $agent_id );

		$queue = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pppd/v1/github/queue' ) )->get_data();

		$this->assertSame( 1, $queue['count'] );
		$this->assertSame( 'mattcowan/example-app', $queue['items'][0]['repo'] );
		$this->assertSame( $section_id, $queue['items'][0]['section_id'] );
		$this->assertNotEmpty( $queue['items'][0]['labels'] );

		$push = new WP_REST_Request( 'POST', '/pppd/v1/github/queue/' . $section_id . '/pushed' );
		$push->set_body_params(
			array(
				'issue_url'    => 'https://github.com/mattcowan/example-app/issues/42',
				'issue_number' => 42,
			)
		);

		$response = rest_get_server()->dispatch( $push );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'pushed', get_post_meta( $section_id, '_pppd_github_status', true ) );
		$this->assertSame( 42, (int) get_post_meta( $section_id, '_pppd_github_issue_number', true ) );

		// Second push attempt must 409 — never double-push.
		$this->assertSame( 409, rest_get_server()->dispatch( $push )->get_status() );

		// And the queue is now empty.
		$this->assertSame( 0, rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pppd/v1/github/queue' ) )->get_data()['count'] );
	}

	public function test_queue_rest_surface_rejects_clients() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pppd/v1/github/queue' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_github_config_meta_is_human_only_over_rest() {
		list( $report_id ) = $this->fixture();

		$agent_id = self::factory()->user->create( array( 'role' => 'pppd_agent' ) );
		wp_set_current_user( $agent_id );

		$this->assertFalse(
			current_user_can( 'edit_post_meta', $report_id, '_pppd_github_repo' ),
			'The agent role must never set the target repo'
		);

		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( current_user_can( 'edit_post_meta', $report_id, '_pppd_github_repo' ) );
	}
}
