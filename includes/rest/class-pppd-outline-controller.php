<?php
/**
 * REST controller: report outline.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * GET /pppd/v1/reports/{id}/outline — the section tree of a report.
 */
class PPPD_Outline_Controller {

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
			'/reports/(?P<id>\d+)/outline',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_outline' ),
				'permission_callback' => array( $this, 'permission_check' ),
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
	 * Permission check: reader access plus a real report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'pppd_forbidden',
				__( 'You are not allowed to read report outlines.', 'pretty-professional-process-docs' ),
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
	 * Build the outline response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_outline( $request ) {
		$report_id = absint( $request['id'] );
		$report    = get_post( $report_id );
		$sections  = array();

		foreach ( pppd_get_report_sections( $report_id ) as $entry ) {
			$section = $entry['post'];

			$edit_link = get_edit_post_link( $section->ID, 'raw' );

			$sections[] = array(
				'id'            => (int) $section->ID,
				'title'         => get_the_title( $section ),
				'parent'        => (int) $section->post_parent,
				'order'         => (int) $section->menu_order,
				'depth'         => (int) $entry['depth'],
				'type'          => pppd_get_section_term_slug( $section, 'pppd_section_type' ),
				'status'        => pppd_get_section_term_slug( $section, 'pppd_status' ),
				'req_id'        => (string) get_post_meta( $section->ID, '_pppd_req_id', true ),
				'comment_count' => (int) get_comments_number( $section ),
				'edit_link'     => is_string( $edit_link ) ? $edit_link : '',
			);
		}

		return rest_ensure_response(
			array(
				'report'   => array(
					'id'           => (int) $report->ID,
					'title'        => get_the_title( $report ),
					'link'         => get_permalink( $report ),
					'project_slug' => (string) get_post_meta( $report->ID, '_pppd_project_slug', true ),
				),
				'sections' => $sections,
			)
		);
	}
}
