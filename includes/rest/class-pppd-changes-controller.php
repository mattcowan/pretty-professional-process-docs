<?php
/**
 * REST controller: approve / reject proposed changes.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST /pppd/v1/changes/{id}/approve and /pppd/v1/changes/{id}/reject.
 *
 * Both delegate to the shared functions in includes/change-actions.php so
 * REST and the admin Review Queue behave identically.
 */
class PPPD_Changes_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pppd/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/changes/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/changes/(?P<id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'id'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'reason' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission check: approval is a human-only capability.
	 *
	 * @return true|WP_Error
	 */
	public function permission_check() {
		if ( ! current_user_can( 'pppd_approve_changes' ) ) {
			return new WP_Error(
				'pppd_forbidden',
				__( 'You are not allowed to review proposed changes.', 'pretty-professional-process-docs' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Approve a pending change.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( $request ) {
		$result = pppd_approve_change( absint( $request['id'] ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Reject a pending change with an optional reason.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject( $request ) {
		$reason = $request->get_param( 'reason' );
		$result = pppd_reject_change( absint( $request['id'] ), is_string( $reason ) ? $reason : '' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
