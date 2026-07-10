<?php
/**
 * Per-section sign-off: the legal record, staleness, provenance, and the
 * approval lock's REST posture.
 *
 * @package PPPD
 */

/**
 * Tests for includes/signoff.php.
 */
class Test_Signoff extends WP_UnitTestCase {

	/**
	 * Seed roles/caps the way activation does.
	 */
	public function set_up() {
		parent::set_up();

		pppd_grant_capabilities();
	}

	/**
	 * Report + published section fixture.
	 *
	 * @return array{0:int,1:int} [report ID, section ID].
	 */
	protected function fixture() {
		$report_id = self::factory()->post->create(
			array(
				'post_type'   => 'pppd_report',
				'post_status' => 'publish',
			)
		);

		$section_id = self::factory()->post->create(
			array(
				'post_type'    => 'pppd_section',
				'post_status'  => 'publish',
				'post_content' => 'Original content.',
				'post_date'    => '2026-07-01 09:00:00',
			)
		);
		update_post_meta( $section_id, '_pppd_report_id', $report_id );

		return array( $report_id, $section_id );
	}

	public function test_signoff_records_user_time_and_state() {
		list( , $section_id ) = $this->fixture();
		$client_id            = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( 'none', pppd_get_section_signoff( $section_id )['state'] );

		$record = pppd_sign_off_section( $section_id, $client_id );

		$this->assertSame( 'approved', $record['state'] );
		$this->assertSame( $client_id, $record['by'] );
		$this->assertNotSame( '', $record['at'] );
		$this->assertSame( 1, did_action( 'pppd_section_approved' ) );
	}

	public function test_draft_sections_cannot_be_signed_off() {
		list( , $section_id ) = $this->fixture();
		wp_update_post(
			array(
				'ID'          => $section_id,
				'post_status' => 'draft',
			)
		);

		$result = pppd_sign_off_section( $section_id, self::factory()->user->create() );

		$this->assertWPError( $result );
		$this->assertSame( 'pppd_not_published', $result->get_error_code() );
	}

	public function test_content_change_flips_signoff_to_stale() {
		list( , $section_id ) = $this->fixture();

		pppd_sign_off_section( $section_id, self::factory()->user->create() );

		// A later edit (modified > approved_at).
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_content'      => 'Edited after approval.',
				'post_modified'     => '2036-01-01 00:00:00',
				'post_modified_gmt' => '2036-01-01 00:00:00',
			),
			array( 'ID' => $section_id )
		);
		clean_post_cache( $section_id );

		$this->assertSame( 'stale', pppd_get_section_signoff( $section_id )['state'] );
	}

	public function test_approved_change_records_reapproval_provenance() {
		list( , $section_id ) = $this->fixture();
		$editor_id            = self::factory()->user->create( array( 'role' => 'editor' ) );

		pppd_sign_off_section( $section_id, self::factory()->user->create() );

		$change_id = self::factory()->post->create(
			array(
				'post_type'    => 'pppd_change',
				'post_status'  => 'pending',
				'post_content' => 'Replacement content.',
			)
		);
		update_post_meta( $change_id, '_pppd_target_section', $section_id );

		// The clock inside a test is frozen enough that modified == approved_at;
		// nudge the approval timestamp back so the applied change is "later".
		update_post_meta( $section_id, '_pppd_approved_at', '2026-07-01 09:00:00' );

		$summary = pppd_approve_change( $change_id, $editor_id );

		$this->assertIsArray( $summary );

		$signoff = pppd_get_section_signoff( $section_id );

		$this->assertSame( 'stale', $signoff['state'], 'An applied change must invalidate the sign-off' );
		$this->assertSame( $change_id, $signoff['source'], 'Provenance must record which change did it' );
	}

	public function test_reapproval_restores_approved_state_and_clears_provenance() {
		list( , $section_id ) = $this->fixture();

		pppd_sign_off_section( $section_id, self::factory()->user->create() );
		update_post_meta( $section_id, '_pppd_approved_at', '2026-07-01 09:00:00' );
		update_post_meta( $section_id, '_pppd_reapproval_source', 123 );

		pppd_sign_off_section( $section_id, self::factory()->user->create() );

		$signoff = pppd_get_section_signoff( $section_id );

		$this->assertSame( 'approved', $signoff['state'] );
		$this->assertSame( 0, $signoff['source'] );
	}

	public function test_signoff_meta_is_never_rest_writable_even_by_admins() {
		list( , $section_id ) = $this->fixture();
		$admin_id             = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		foreach ( array( '_pppd_approved_by', '_pppd_approved_at', '_pppd_approved_revision', '_pppd_reapproval_source' ) as $key ) {
			$this->assertFalse(
				current_user_can( 'edit_post_meta', $section_id, $key ),
				"{$key} must be read-only over REST — the sign-off is a legal record"
			);
		}
	}

	public function test_outline_exposes_signoff_state_to_agents() {
		list( $report_id, $section_id ) = $this->fixture();

		pppd_sign_off_section( $section_id, self::factory()->user->create() );

		$payload = pppd_get_outline_payload( $report_id );

		$this->assertSame( 'approved', $payload['sections'][0]['signoff']['state'] );
		$this->assertFalse( $payload['sections'][0]['internal'] );
	}
}
