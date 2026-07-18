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
					'id'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					// Additive (contract v1): which post statuses to include.
					// Team-only; the default (publish) is unchanged for every
					// existing caller. Accepts CSV or repeated params.
					'status' => array(
						'type'     => 'array',
						'items'    => array(
							'type' => 'string',
							'enum' => pppd_authoring_statuses(),
						),
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Permission check: a real report the current user may view (team
	 * capability, client membership, or per-report assignment). The status
	 * parameter — seeing unpublished sections — is team-only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check( $request ) {
		$check = pppd_rest_view_permission_check( $request );

		if ( true !== $check ) {
			return $check;
		}

		if ( null !== $request->get_param( 'status' ) && ! pppd_is_team_viewer() ) {
			return new WP_Error(
				'pppd_forbidden_status',
				__( 'Only team users may request unpublished sections.', 'pretty-professional-process-docs' ),
				array( 'status' => 403 )
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
		$statuses = $request->get_param( 'status' );

		return rest_ensure_response(
			pppd_get_outline_payload( absint( $request['id'] ), empty( $statuses ) ? 'publish' : $statuses )
		);
	}
}

/**
 * Build the outline payload (shared by the REST route and the pppd/get-outline
 * ability — one shape, one source of truth).
 *
 * Sections are filtered through pppd_filter_visible_sections() before
 * grouping, so non-team callers never receive team-only (_pppd_internal)
 * rows and visible children of an internal parent re-root instead of
 * vanishing — the same rule the front-end template applies.
 *
 * @param int             $report_id Report post ID.
 * @param string|string[] $statuses  Post statuses to include. Default 'publish';
 *                                   the REST route only ever passes more for
 *                                   team callers.
 * @return array<string, mixed>
 */
function pppd_get_outline_payload( $report_id, $statuses = 'publish' ) {
	$report   = get_post( $report_id );
	$sections = array();

	$posts   = pppd_filter_visible_sections( pppd_get_report_section_posts( $report_id, $statuses ) );
	$grouped = pppd_group_sections_by_parent( $posts );

	foreach ( pppd_flatten_section_tree( $grouped ) as $entry ) {
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
			// Additive (contract v1): the WordPress post status — is the
			// section part of the published document — as distinct from the
			// pppd_status workflow term above.
			'post_status'   => (string) $section->post_status,
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
