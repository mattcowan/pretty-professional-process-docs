<?php
/**
 * REST controller: outline reordering for the report authoring dashboard.
 *
 * POST /pppd/v1/reports/{id}/reorder — move a section up/down among its
 * siblings or indent/outdent it one level. The server recomputes menu_order
 * for every affected sibling group and returns the fresh authoring outline,
 * which the admin JS re-renders (server is the single source of truth).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Outline reorder endpoint.
 */
class PPPD_Reorder_Controller {

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
			'/reports/(?P<id>\d+)/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'id'        => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'section'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'direction' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'up', 'down', 'indent', 'outdent' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Permission check: report editors only, real report, section belongs to it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check( $request ) {
		if ( ! current_user_can( 'edit_pppd_reports' ) ) {
			return new WP_Error(
				'pppd_forbidden',
				__( 'You are not allowed to reorder report sections.', 'pretty-professional-process-docs' ),
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

		$section = get_post( absint( $request['section'] ) );

		if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
			return new WP_Error(
				'pppd_section_not_found',
				__( 'Section not found.', 'pretty-professional-process-docs' ),
				array( 'status' => 404 )
			);
		}

		// Reorders must never move a section across reports.
		if ( absint( get_post_meta( $section->ID, '_pppd_report_id', true ) ) !== absint( $request['id'] ) ) {
			return new WP_Error(
				'pppd_wrong_report',
				__( 'That section does not belong to this report.', 'pretty-professional-process-docs' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Perform the move and respond with the fresh outline.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder( $request ) {
		$report_id  = absint( $request['id'] );
		$section_id = absint( $request['section'] );
		$direction  = (string) $request['direction'];

		$posts   = pppd_get_report_section_posts( $report_id, pppd_authoring_statuses() );
		$grouped = pppd_group_sections_by_parent( $posts );
		$ids     = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );

		$section = get_post( $section_id );
		$parent  = in_array( (int) $section->post_parent, $ids, true ) ? (int) $section->post_parent : 0;
		$index   = $this->sibling_index( $grouped, $parent, $section_id );

		switch ( $direction ) {
			case 'up':
			case 'down':
				$result = $this->move_among_siblings( $grouped, $parent, $index, 'up' === $direction ? -1 : 1 );
				break;
			case 'indent':
				$result = $this->indent( $grouped, $parent, $index );
				break;
			default:
				$result = $this->outdent( $grouped, $ids, $parent, $index );
				break;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message'  => $result,
				'sections' => pppd_get_authoring_outline( $report_id ),
			)
		);
	}

	/**
	 * Find a section's index within its sibling group.
	 *
	 * @param array<int, WP_Post[]> $grouped    Sections grouped by parent.
	 * @param int                   $parent     Effective parent ID.
	 * @param int                   $section_id Section ID.
	 * @return int
	 */
	protected function sibling_index( $grouped, $parent, $section_id ) {
		foreach ( isset( $grouped[ $parent ] ) ? $grouped[ $parent ] : array() as $i => $sibling ) {
			if ( (int) $sibling->ID === $section_id ) {
				return (int) $i;
			}
		}

		return 0;
	}

	/**
	 * Rewrite menu_order for a sibling group (gaps of 10, stable order).
	 *
	 * @param WP_Post[] $siblings Ordered siblings.
	 * @return void
	 */
	protected function renumber( $siblings ) {
		foreach ( array_values( $siblings ) as $i => $sibling ) {
			$order = ( $i + 1 ) * 10;

			if ( (int) $sibling->menu_order !== $order ) {
				wp_update_post(
					array(
						'ID'         => $sibling->ID,
						'menu_order' => $order,
					)
				);
			}
		}
	}

	/**
	 * Swap a section with its previous/next sibling.
	 *
	 * @param array<int, WP_Post[]> $grouped Sections grouped by parent.
	 * @param int                   $parent  Effective parent ID.
	 * @param int                   $index   Section's index among siblings.
	 * @param int                   $delta   -1 for up, +1 for down.
	 * @return string|WP_Error Announcement message.
	 */
	protected function move_among_siblings( $grouped, $parent, $index, $delta ) {
		$siblings = $grouped[ $parent ];
		$target   = $index + $delta;

		if ( $target < 0 || $target >= count( $siblings ) ) {
			return new WP_Error(
				'pppd_cannot_move',
				__( 'The section is already at that edge of its group.', 'pretty-professional-process-docs' ),
				array( 'status' => 409 )
			);
		}

		$moved                = $siblings[ $index ];
		$siblings[ $index ]   = $siblings[ $target ];
		$siblings[ $target ]  = $moved;

		$this->renumber( $siblings );

		return sprintf(
			/* translators: 1: section title, 2: new position, 3: group size. */
			__( 'Moved “%1$s” to position %2$d of %3$d.', 'pretty-professional-process-docs' ),
			wp_specialchars_decode( get_the_title( $moved ), ENT_QUOTES ),
			$target + 1,
			count( $siblings )
		);
	}

	/**
	 * Make a section the last child of its previous sibling.
	 *
	 * @param array<int, WP_Post[]> $grouped Sections grouped by parent.
	 * @param int                   $parent  Effective parent ID.
	 * @param int                   $index   Section's index among siblings.
	 * @return string|WP_Error Announcement message.
	 */
	protected function indent( $grouped, $parent, $index ) {
		if ( 0 === $index ) {
			return new WP_Error(
				'pppd_cannot_move',
				__( 'The first section of a group cannot be indented — it has no preceding sibling to nest under.', 'pretty-professional-process-docs' ),
				array( 'status' => 409 )
			);
		}

		$siblings   = $grouped[ $parent ];
		$moved      = $siblings[ $index ];
		$new_parent = $siblings[ $index - 1 ];

		wp_update_post(
			array(
				'ID'          => $moved->ID,
				'post_parent' => $new_parent->ID,
			)
		);

		// Append to the new parent's children, then renumber both groups.
		$children   = isset( $grouped[ (int) $new_parent->ID ] ) ? $grouped[ (int) $new_parent->ID ] : array();
		$children[] = $moved;
		$this->renumber( $children );

		unset( $siblings[ $index ] );
		$this->renumber( array_values( $siblings ) );

		return sprintf(
			/* translators: 1: section title, 2: new parent section title. */
			__( 'Indented “%1$s” under “%2$s”.', 'pretty-professional-process-docs' ),
			wp_specialchars_decode( get_the_title( $moved ), ENT_QUOTES ),
			wp_specialchars_decode( get_the_title( $new_parent ), ENT_QUOTES )
		);
	}

	/**
	 * Move a section to its parent's level, directly after the parent.
	 *
	 * @param array<int, WP_Post[]> $grouped Sections grouped by parent.
	 * @param int[]                 $ids     All section IDs in the report.
	 * @param int                   $parent  Effective parent ID.
	 * @param int                   $index   Section's index among siblings.
	 * @return string|WP_Error Announcement message.
	 */
	protected function outdent( $grouped, $ids, $parent, $index ) {
		if ( 0 === $parent ) {
			return new WP_Error(
				'pppd_cannot_move',
				__( 'Top-level sections cannot be outdented.', 'pretty-professional-process-docs' ),
				array( 'status' => 409 )
			);
		}

		$siblings    = $grouped[ $parent ];
		$moved       = $siblings[ $index ];
		$parent_post = get_post( $parent );
		$grandparent = in_array( (int) $parent_post->post_parent, $ids, true ) ? (int) $parent_post->post_parent : 0;

		wp_update_post(
			array(
				'ID'          => $moved->ID,
				'post_parent' => $grandparent,
			)
		);

		// Insert right after the old parent in the grandparent's group.
		$group = isset( $grouped[ $grandparent ] ) ? $grouped[ $grandparent ] : array();
		$slot  = count( $group );

		foreach ( $group as $i => $candidate ) {
			if ( (int) $candidate->ID === $parent ) {
				$slot = $i + 1;
				break;
			}
		}

		array_splice( $group, $slot, 0, array( $moved ) );
		$this->renumber( $group );

		unset( $siblings[ $index ] );
		$this->renumber( array_values( $siblings ) );

		return sprintf(
			/* translators: 1: section title, 2: former parent section title. */
			__( 'Moved “%1$s” out to the level of “%2$s”.', 'pretty-professional-process-docs' ),
			wp_specialchars_decode( get_the_title( $moved ), ENT_QUOTES ),
			wp_specialchars_decode( get_the_title( $parent_post ), ENT_QUOTES )
		);
	}
}
