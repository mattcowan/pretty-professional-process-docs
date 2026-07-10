<?php
/**
 * Client access control: who can see a report, and which parts.
 *
 * Reports stop being world-readable here: viewing a report requires being
 * logged in AND passing pppd_user_can_view_report() (team capability, client
 * membership, or per-report assignment — includes/clients.php). Enforced at
 * every read surface: the single-report template, search, core REST, and the
 * plugin's own pppd/v1 read routes (via pppd_rest_view_permission_check()).
 *
 * "Team viewer" = anyone with edit_pppd_reports (admin, editor/PM, agent).
 * Internal content — implementation notes, internal-visibility fields,
 * team-only sections, drift tooling — renders for team viewers only.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook up the read guards.
 *
 * @return void
 */
function pppd_access_init() {
	add_action( 'template_redirect', 'pppd_guard_report_template' );
	add_action( 'pre_get_posts', 'pppd_exclude_reports_from_search' );
	add_filter( 'rest_request_before_callbacks', 'pppd_guard_report_rest_single', 10, 3 );
	add_filter( 'rest_pppd_report_query', 'pppd_scope_report_rest_collection', 10, 2 );
}

/**
 * Whether the current user is a team viewer (sees internal content).
 *
 * @return bool
 */
function pppd_is_team_viewer() {
	return current_user_can( 'edit_pppd_reports' );
}

/**
 * Whether a section is marked team-only.
 *
 * @param WP_Post|int $section Section post or ID.
 * @return bool
 */
function pppd_section_is_internal( $section ) {
	$section_id = $section instanceof WP_Post ? $section->ID : absint( $section );

	return (bool) get_post_meta( $section_id, '_pppd_internal', true );
}

/**
 * Filter a report's section posts down to what the current user may see.
 *
 * Team viewers see everything; everyone else loses team-only sections.
 *
 * @param WP_Post[] $sections Section posts.
 * @return WP_Post[]
 */
function pppd_filter_visible_sections( $sections ) {
	if ( pppd_is_team_viewer() ) {
		return $sections;
	}

	return array_values(
		array_filter(
			$sections,
			static function ( $section ) {
				return ! pppd_section_is_internal( $section );
			}
		)
	);
}

/**
 * Frontend guard for single report views: anonymous visitors are sent to
 * log in; logged-in users without view access get an accessible 403.
 *
 * @return void
 */
function pppd_guard_report_template() {
	if ( ! is_singular( 'pppd_report' ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}

	if ( pppd_user_can_view_report( get_current_user_id(), get_queried_object_id() ) ) {
		return;
	}

	wp_die(
		esc_html__( 'This report belongs to another client. If you believe you should have access, ask the project team to add you.', 'pretty-professional-process-docs' ),
		esc_html__( 'Report access restricted', 'pretty-professional-process-docs' ),
		array( 'response' => 403 )
	);
}

/**
 * Keep reports out of search results for non-team users (titles and excerpts
 * would otherwise leak across clients).
 *
 * @param WP_Query $query Query.
 * @return void
 */
function pppd_exclude_reports_from_search( $query ) {
	if ( is_admin() || ! $query->is_search() || pppd_is_team_viewer() ) {
		return;
	}

	$post_types = $query->get( 'post_type' );

	if ( empty( $post_types ) ) {
		$searchable = get_post_types( array( 'exclude_from_search' => false ) );
		unset( $searchable['pppd_report'] );
		$query->set( 'post_type', array_values( $searchable ) );
	} elseif ( is_array( $post_types ) ) {
		$query->set( 'post_type', array_values( array_diff( $post_types, array( 'pppd_report' ) ) ) );
	} elseif ( 'pppd_report' === $post_types ) {
		$query->set( 'post__in', array( 0 ) );
	}
}

/**
 * Guard single-report reads on the core wp/v2 route.
 *
 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send.
 * @param array<string, mixed>                             $handler  Route handler.
 * @param WP_REST_Request                                  $request  Request.
 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
 */
function pppd_guard_report_rest_single( $response, $handler, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) ) {
		return $response;
	}

	if ( 0 === preg_match( '#^/wp/v2/pppd-reports/(?P<id>\d+)$#', (string) $request->get_route(), $matches ) ) {
		return $response;
	}

	if ( pppd_user_can_view_report( get_current_user_id(), (int) $matches['id'] ) ) {
		return $response;
	}

	return new WP_Error(
		'pppd_forbidden',
		__( 'You are not allowed to view this report.', 'pretty-professional-process-docs' ),
		array( 'status' => rest_authorization_required_code() )
	);
}

/**
 * Scope wp/v2 report collection reads for non-team users to the reports they
 * can view (client membership or assignment).
 *
 * @param array<string, mixed> $args    WP_Query args.
 * @param WP_REST_Request      $request Request.
 * @return array<string, mixed>
 */
function pppd_scope_report_rest_collection( $args, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( pppd_is_team_viewer() ) {
		return $args;
	}

	$viewable = pppd_get_viewable_report_ids( get_current_user_id() );

	$args['post__in'] = empty( $viewable ) ? array( 0 ) : $viewable;

	return $args;
}

/**
 * All report IDs a non-team user can view: their clients' reports plus
 * individually-assigned ones.
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function pppd_get_viewable_report_ids( $user_id ) {
	$user_id = absint( $user_id );

	if ( 0 === $user_id ) {
		return array();
	}

	$ids        = array();
	$client_ids = pppd_get_user_client_ids( $user_id );

	if ( ! empty( $client_ids ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'pppd_report',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'pppd_client',
						'field'    => 'term_id',
						'terms'    => $client_ids,
					),
				),
			)
		);
	}

	// Assignment meta is a serialized array, so fetch candidates by key
	// existence and confirm membership in PHP (exact match, no LIKE traps).
	$assigned = get_posts(
		array(
			'post_type'      => 'pppd_report',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_pppd_assigned_user_ids',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	foreach ( $assigned as $report_id ) {
		$users = get_post_meta( (int) $report_id, '_pppd_assigned_user_ids', true );

		if ( is_array( $users ) && in_array( $user_id, array_map( 'absint', $users ), true ) ) {
			$ids[] = (int) $report_id;
		}
	}

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Shared permission callback for pppd/v1 read routes (outline, traceability,
 * drift/latest): a real report the current user may view.
 *
 * Replaces the original bare current_user_can('read') check — a client
 * subscriber could previously read the outline of ANY report. Team users and
 * agents (edit_pppd_reports) are unaffected.
 *
 * @param WP_REST_Request $request Request (expects an id route param).
 * @return true|WP_Error
 */
function pppd_rest_view_permission_check( $request ) {
	$report = get_post( absint( $request['id'] ) );

	if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
		return new WP_Error(
			'pppd_report_not_found',
			__( 'Report not found.', 'pretty-professional-process-docs' ),
			array( 'status' => 404 )
		);
	}

	if ( ! pppd_user_can_view_report( get_current_user_id(), $report->ID ) ) {
		return new WP_Error(
			'pppd_forbidden',
			__( 'You are not allowed to view this report.', 'pretty-professional-process-docs' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	return true;
}
