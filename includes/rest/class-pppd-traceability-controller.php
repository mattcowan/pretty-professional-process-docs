<?php
/**
 * REST controller: traceability matrix (JSON + CSV).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * GET /pppd/v1/reports/{id}/traceability — one row per requirement section.
 * Add ?format=csv for a downloadable CSV.
 */
class PPPD_Traceability_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pppd/v1';

	/**
	 * Register routes and the raw-CSV server filter.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/reports/(?P<id>\d+)/traceability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_traceability' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'id'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'format' => array(
						'type'              => 'string',
						'default'           => 'json',
						'enum'              => array( 'json', 'csv' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		add_filter( 'rest_pre_serve_request', array( $this, 'serve_csv' ), 10, 4 );
	}

	/**
	 * Permission check: reader access plus a real report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'pppd_forbidden',
				__( 'You are not allowed to read traceability data.', 'pretty-professional-process-docs' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$report = get_post( absint( $request['id'] ) );

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
	 * Build the traceability rows.
	 *
	 * @param int $report_id Report ID.
	 * @return array<int, array<string, mixed>>
	 */
	protected function build_rows( $report_id ) {
		$drift          = pppd_get_latest_drift( $report_id );
		$drift_verdicts = array();

		foreach ( pppd_get_drift_items( $drift ) as $item ) {
			if ( ! empty( $item['req_id'] ) && is_string( $item['req_id'] ) ) {
				$drift_verdicts[ $item['req_id'] ] = isset( $item['verdict'] ) ? (string) $item['verdict'] : 'none';
			}
		}

		$rows = array();

		foreach ( pppd_get_report_sections( $report_id ) as $entry ) {
			$section = $entry['post'];

			if ( 'requirement' !== pppd_get_section_term_slug( $section, 'pppd_section_type' ) ) {
				continue;
			}

			$req_id     = (string) get_post_meta( $section->ID, '_pppd_req_id', true );
			$acceptance = get_post_meta( $section->ID, '_pppd_acceptance', true );
			$code_refs  = get_post_meta( $section->ID, '_pppd_code_refs', true );
			$test_refs  = get_post_meta( $section->ID, '_pppd_test_refs', true );

			$rows[] = array(
				'req_id'           => $req_id,
				'title'            => get_the_title( $section ),
				'status'           => pppd_get_section_term_slug( $section, 'pppd_status' ),
				'acceptance_count' => is_array( $acceptance ) ? count( $acceptance ) : 0,
				'code_refs'        => is_array( $code_refs ) ? $code_refs : array(),
				'test_refs'        => is_array( $test_refs ) ? $test_refs : array(),
				'drift_verdict'    => ( '' !== $req_id && isset( $drift_verdicts[ $req_id ] ) ) ? $drift_verdicts[ $req_id ] : 'none',
				'section_id'       => (int) $section->ID,
			);
		}

		return $rows;
	}

	/**
	 * Respond with JSON rows, or a CSV response when ?format=csv.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_traceability( $request ) {
		$report_id = absint( $request['id'] );
		$rows      = $this->build_rows( $report_id );

		if ( 'csv' !== $request->get_param( 'format' ) ) {
			return rest_ensure_response( $rows );
		}

		$csv = $this->rows_to_csv( $rows );

		return new WP_REST_Response(
			$csv,
			200,
			array(
				'Content-Type'        => 'text/csv; charset=utf-8',
				'Content-Disposition' => 'attachment; filename="pppd-traceability-report-' . $report_id . '.csv"',
			)
		);
	}

	/**
	 * Turn rows into an RFC 4180 CSV string with formula-injection guards.
	 *
	 * @param array<int, array<string, mixed>> $rows Traceability rows.
	 * @return string
	 */
	protected function rows_to_csv( $rows ) {
		$lines   = array();
		$lines[] = implode(
			',',
			array_map(
				array( $this, 'csv_cell' ),
				array( 'req_id', 'title', 'status', 'acceptance_count', 'code_refs', 'test_refs', 'drift_verdict', 'section_id' )
			)
		);

		foreach ( $rows as $row ) {
			$lines[] = implode(
				',',
				array_map(
					array( $this, 'csv_cell' ),
					array(
						$row['req_id'],
						$row['title'],
						$row['status'],
						(string) $row['acceptance_count'],
						implode( '; ', $row['code_refs'] ),
						implode( '; ', $row['test_refs'] ),
						$row['drift_verdict'],
						(string) $row['section_id'],
					)
				)
			);
		}

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Escape a single CSV cell.
	 *
	 * Cells beginning with =, +, - or @ are prefixed with a single quote to
	 * defuse spreadsheet formula injection, then RFC 4180 quoting applies.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	protected function csv_cell( $value ) {
		$value = (string) $value;

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			$value = "'" . $value;
		}

		if ( 1 === preg_match( '/[",\r\n]/', $value ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}

	/**
	 * Serve CSV responses raw instead of JSON-encoded.
	 *
	 * @param bool             $served  Whether the request has been served.
	 * @param WP_HTTP_Response $result  Response object.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server instance.
	 * @return bool
	 */
	public function serve_csv( $served, $result, $request, $server ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $served ) {
			return $served;
		}

		if ( 0 === preg_match( '#^/pppd/v1/reports/\d+/traceability$#', $request->get_route() ) ) {
			return $served;
		}

		$headers      = $result->get_headers();
		$content_type = isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : '';

		if ( 0 !== strpos( $content_type, 'text/csv' ) ) {
			return $served;
		}

		$data = $result->get_data();

		if ( ! is_string( $data ) ) {
			return $served;
		}

		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV payload; every cell is sanitized, formula-escaped, and RFC 4180 quoted above.

		return true;
	}
}
