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
	 * Permission check: a real report the current user may view (team
	 * capability, client membership, or per-report assignment).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check( $request ) {
		return pppd_rest_view_permission_check( $request );
	}

	/**
	 * Build the outline response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_outline( $request ) {
		return rest_ensure_response( pppd_get_outline_payload( absint( $request['id'] ) ) );
	}
}

/**
 * Build the outline payload (shared by the REST route and the pppd/get-outline
 * ability — one shape, one source of truth).
 *
 * @param int $report_id Report post ID.
 * @return array<string, mixed>
 */
function pppd_get_outline_payload( $report_id ) {
	$report   = get_post( $report_id );
	$sections = array();

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
			// Additive (contract v1): the sign-off record. Agent runs MUST
			// treat signoff.state 'approved' as read-only — skip the section
			// or propose a change flagged for re-approval.
			'signoff'       => pppd_get_section_signoff( $section ),
			'internal'      => pppd_section_is_internal( $section ),
		);
	}

	return array(
		// Additive (contract v1): lets skills detect an old plugin instead of
		// failing obscurely on a missing feature.
		'pppd_contract_version' => PPPD_CONTRACT_VERSION,
		'report'                => array(
			'id'           => (int) $report->ID,
			'title'        => get_the_title( $report ),
			'link'         => get_permalink( $report ),
			'project_slug' => (string) get_post_meta( $report->ID, '_pppd_project_slug', true ),
			// Additive (contract v1): registered report type slug.
			'type'         => pppd_get_report_type( $report ),
		),
		'sections'              => $sections,
	);
}
