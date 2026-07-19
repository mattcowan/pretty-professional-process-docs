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
	add_action( 'admin_bar_menu', 'pppd_admin_bar_preview_link', 90 );

	// Public-report-ID cache: request-scoped even on persistent object caches,
	// and invalidated on flag/status changes so it can never serve stale IDs.
	wp_cache_add_non_persistent_groups( array( 'pppd' ) );
	add_action( 'added_post_meta', 'pppd_maybe_flush_public_ids_on_meta', 10, 3 );
	add_action( 'updated_post_meta', 'pppd_maybe_flush_public_ids_on_meta', 10, 3 );
	add_action( 'deleted_post_meta', 'pppd_maybe_flush_public_ids_on_meta', 10, 3 );
	add_action( 'transition_post_status', 'pppd_maybe_flush_public_ids_on_status', 10, 3 );
}

/**
 * Whether a team viewer asked to see the report exactly as a client sees it
 * (published sections only, no draft flags).
 *
 * Read-only view switch driven by ?pppd_preview=published — no state changes,
 * so no nonce. The parameter is inert for non-team viewers, whose render is
 * publish-only regardless.
 *
 * @return bool
 */
function pppd_is_client_preview() {
	if ( ! pppd_is_team_viewer() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
	return isset( $_GET['pppd_preview'] ) && 'published' === sanitize_key( wp_unslash( $_GET['pppd_preview'] ) );
}

/**
 * Admin-bar toggle between the team view (drafts included, flagged) and the
 * client preview (published sections only) on single report views.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
 * @return void
 */
function pppd_admin_bar_preview_link( $wp_admin_bar ) {
	if ( is_admin() || ! is_singular( 'pppd_report' ) || ! pppd_is_team_viewer() ) {
		return;
	}

	$in_preview = pppd_is_client_preview();

	$wp_admin_bar->add_node(
		array(
			'id'    => 'pppd-preview-toggle',
			'title' => $in_preview
				? __( 'Back to team view (includes drafts)', 'pretty-professional-process-docs' )
				: __( 'View as client (published only)', 'pretty-professional-process-docs' ),
			'href'  => $in_preview
				? remove_query_arg( 'pppd_preview' )
				: add_query_arg( 'pppd_preview', 'published' ),
		)
	);
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
 * Whether a report has been deliberately made world-readable (a published
 * work sample). Requires publish status AND the human-only _pppd_public flag.
 *
 * Public never widens what a reader gets: anonymous visitors still receive
 * only published, non-internal sections.
 *
 * @param int $report_id Report post ID.
 * @return bool
 */
function pppd_report_is_public( $report_id ) {
	$report = get_post( absint( $report_id ) );

	return $report instanceof WP_Post
		&& 'pppd_report' === $report->post_type
		&& 'publish' === $report->post_status
		&& (bool) get_post_meta( $report->ID, '_pppd_public', true );
}

/**
 * All public report IDs (for scoping collection reads).
 *
 * Cached per request in the non-persistent `pppd` cache group (anonymous
 * collection reads hit this); invalidated when `_pppd_public` changes or a
 * report changes status — see pppd_flush_public_report_ids_cache().
 *
 * @return int[]
 */
function pppd_get_public_report_ids() {
	$cached = wp_cache_get( 'public_report_ids', 'pppd' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$ids = array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'pppd_report',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_pppd_public', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		)
	);

	wp_cache_set( 'public_report_ids', $ids, 'pppd' );

	return $ids;
}

/**
 * Drop the cached public-report-ID list when the flag or a report's status
 * changes. Hooked to the `_pppd_public` meta events and to report status
 * transitions (publish gates the flag, so status changes matter too).
 *
 * @return void
 */
function pppd_flush_public_report_ids_cache() {
	wp_cache_delete( 'public_report_ids', 'pppd' );
}

/**
 * Invalidation shim for meta hooks: flush only for the `_pppd_public` key.
 *
 * @param int|int[] $meta_ids  Meta row ID(s) (unused).
 * @param int       $object_id Post ID (unused).
 * @param string    $meta_key  Meta key that changed.
 * @return void
 */
function pppd_maybe_flush_public_ids_on_meta( $meta_ids, $object_id, $meta_key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( '_pppd_public' === $meta_key ) {
		pppd_flush_public_report_ids_cache();
	}
}

/**
 * Invalidation shim for status transitions: flush for reports only.
 *
 * @param string  $new_status New status (unused).
 * @param string  $old_status Old status (unused).
 * @param WP_Post $post       Post.
 * @return void
 */
function pppd_maybe_flush_public_ids_on_status( $new_status, $old_status, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( $post instanceof WP_Post && 'pppd_report' === $post->post_type ) {
		pppd_flush_public_report_ids_cache();
	}
}

/**
 * Frontend guard for single report views: anonymous visitors are sent to
 * log in; logged-in users without view access get an accessible 403.
 * Reports flagged public skip the guard entirely.
 *
 * @return void
 */
function pppd_guard_report_template() {
	if ( ! is_singular( 'pppd_report' ) ) {
		return;
	}

	if ( pppd_report_is_public( get_queried_object_id() ) ) {
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

	if ( pppd_report_is_public( (int) $matches['id'] ) ) {
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

	$viewable = array_values(
		array_unique(
			array_merge(
				pppd_get_viewable_report_ids( get_current_user_id() ),
				pppd_get_public_report_ids()
			)
		)
	);

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

	if ( pppd_report_is_public( $report->ID ) ) {
		return true;
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
