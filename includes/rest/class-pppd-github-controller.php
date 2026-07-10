<?php
/**
 * REST controller: the GitHub push queue the agent layer consumes.
 *
 * GET  /pppd/v1/github/queue                — queued items (agent-readable)
 * POST /pppd/v1/github/queue/{section}/pushed — stamp the created issue back
 *
 * The plugin never talks to GitHub; these routes are the entire integration
 * surface on the WordPress side.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * GitHub queue endpoints.
 */
class PPPD_GitHub_Controller {

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
			'/github/queue',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_queue' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'report' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/github/queue/(?P<section>\d+)/pushed',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mark_pushed' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'section'      => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'issue_url'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'issue_number' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission check: report editors (the agent role qualifies).
	 *
	 * @return true|WP_Error
	 */
	public function permission_check() {
		if ( current_user_can( 'edit_pppd_reports' ) ) {
			return true;
		}

		return new WP_Error(
			'pppd_forbidden',
			__( 'You are not allowed to work the GitHub queue.', 'pretty-professional-process-docs' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * List queued items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_queue( $request ) {
		$items = array();

		foreach ( pppd_get_github_queue( absint( $request['report'] ) ) as $section ) {
			$items[] = pppd_build_github_queue_item( $section );
		}

		return rest_ensure_response(
			array(
				'count' => count( $items ),
				'items' => $items,
			)
		);
	}

	/**
	 * Stamp a created issue back onto its queued section.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_pushed( $request ) {
		$result = pppd_mark_github_pushed(
			absint( $request['section'] ),
			(string) $request['issue_url'],
			absint( $request['issue_number'] )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
