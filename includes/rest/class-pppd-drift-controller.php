<?php
/**
 * REST controller: drift runs.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST /pppd/v1/reports/{id}/drift — record a drift run.
 * GET  /pppd/v1/reports/{id}/drift/latest — fetch the latest drift run.
 */
class PPPD_Drift_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pppd/v1';

	/**
	 * Allowed drift verdicts.
	 *
	 * @var string[]
	 */
	protected $verdicts = array( 'covered', 'partial', 'missing', 'orphan' );

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/reports/(?P<id>\d+)/drift',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_drift' ),
				'permission_callback' => array( $this, 'write_permission_check' ),
				'args'                => array(
					'id'      => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'summary' => array(
						'type'     => 'string',
						'required' => true,
					),
					'items'   => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reports/(?P<id>\d+)/drift/latest',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_latest' ),
				'permission_callback' => array( $this, 'read_permission_check' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Ensure the report exists.
	 *
	 * @param int $report_id Report ID.
	 * @return true|WP_Error
	 */
	protected function report_exists( $report_id ) {
		$report = get_post( absint( $report_id ) );

		if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
			return new WP_Error(
				'pppd_report_not_found',
				__( 'Report not found.', 'pretty-professional-process-docs' ),
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * Permission check for recording drift runs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function write_permission_check( $request ) {
		if ( ! current_user_can( 'edit_pppd_reports' ) ) {
			return new WP_Error(
				'pppd_forbidden',
				__( 'You are not allowed to record drift runs.', 'pretty-professional-process-docs' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $this->report_exists( $request['id'] );
	}

	/**
	 * Permission check for reading drift runs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function read_permission_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'pppd_forbidden',
				__( 'You are not allowed to read drift runs.', 'pretty-professional-process-docs' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $this->report_exists( $request['id'] );
	}

	/**
	 * Validate and store a drift run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_drift( $request ) {
		$report_id = absint( $request['id'] );
		$summary   = $request->get_param( 'summary' );
		$items     = $request->get_param( 'items' );

		if ( ! is_string( $summary ) ) {
			return new WP_Error(
				'pppd_invalid_summary',
				__( 'The drift summary must be a string.', 'pretty-professional-process-docs' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $items ) ) {
			return new WP_Error(
				'pppd_invalid_items',
				__( 'Drift items must be an array.', 'pretty-professional-process-docs' ),
				array( 'status' => 400 )
			);
		}

		$validated = array();

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error(
					'pppd_invalid_item',
					/* translators: %d: item index. */
					sprintf( __( 'Drift item %d is not an object.', 'pretty-professional-process-docs' ), (int) $index ),
					array( 'status' => 400 )
				);
			}

			$verdict = isset( $item['verdict'] ) && is_string( $item['verdict'] ) ? sanitize_key( $item['verdict'] ) : '';

			if ( ! in_array( $verdict, $this->verdicts, true ) ) {
				return new WP_Error(
					'pppd_invalid_verdict',
					/* translators: 1: item index, 2: allowed verdict list. */
					sprintf(
						__( 'Drift item %1$d has an invalid verdict. Allowed: %2$s.', 'pretty-professional-process-docs' ),
						(int) $index,
						implode( ', ', $this->verdicts )
					),
					array( 'status' => 400 )
				);
			}

			$validated[] = array(
				'req_id'    => isset( $item['req_id'] ) && is_scalar( $item['req_id'] ) ? sanitize_text_field( (string) $item['req_id'] ) : '',
				'verdict'   => $verdict,
				'code_refs' => isset( $item['code_refs'] ) ? pppd_sanitize_string_array( $item['code_refs'] ) : array(),
				'test_refs' => isset( $item['test_refs'] ) ? pppd_sanitize_string_array( $item['test_refs'] ) : array(),
				'notes'     => isset( $item['notes'] ) && is_scalar( $item['notes'] ) ? sanitize_text_field( (string) $item['notes'] ) : '',
			);
		}

		$drift_id = wp_insert_post(
			array(
				'post_type'    => 'pppd_drift',
				'post_status'  => 'publish',
				/* translators: %s: date/time of the drift run. */
				'post_title'   => sprintf( __( 'Drift run %s', 'pretty-professional-process-docs' ), current_time( 'Y-m-d H:i' ) ),
				'post_content' => wp_kses_post( $summary ),
				'post_author'  => get_current_user_id(),
				'meta_input'   => array(
					'_pppd_report_id'  => $report_id,
					'_pppd_drift_json' => wp_json_encode( $validated ),
				),
			),
			true
		);

		if ( is_wp_error( $drift_id ) ) {
			$drift_id->add_data( array( 'status' => 500 ) );

			return $drift_id;
		}

		$drift = get_post( $drift_id );

		$response = rest_ensure_response(
			array(
				'id'      => (int) $drift_id,
				'date'    => get_post_time( 'c', true, $drift ),
				'summary' => (string) $drift->post_content,
				'items'   => $validated,
			)
		);
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Fetch the latest drift run for a report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_latest( $request ) {
		$drift = pppd_get_latest_drift( absint( $request['id'] ) );

		if ( ! $drift instanceof WP_Post ) {
			return new WP_Error(
				'pppd_drift_not_found',
				__( 'No drift runs recorded for this report yet.', 'pretty-professional-process-docs' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'id'      => (int) $drift->ID,
				'date'    => get_post_time( 'c', true, $drift ),
				'summary' => (string) $drift->post_content,
				'items'   => pppd_get_drift_items( $drift ),
			)
		);
	}
}
