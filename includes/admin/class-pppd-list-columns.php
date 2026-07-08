<?php
/**
 * Admin list-table columns for sections, changes, and drift runs.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds Report / Req ID / Target Section / Status columns.
 *
 * Section type and status taxonomy columns come free via show_admin_column.
 */
class PPPD_List_Columns {

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_filter( 'manage_pppd_section_posts_columns', array( $this, 'section_columns' ) );
		add_action( 'manage_pppd_section_posts_custom_column', array( $this, 'section_column_content' ), 10, 2 );

		add_filter( 'manage_pppd_change_posts_columns', array( $this, 'change_columns' ) );
		add_action( 'manage_pppd_change_posts_custom_column', array( $this, 'change_column_content' ), 10, 2 );

		add_filter( 'manage_pppd_drift_posts_columns', array( $this, 'drift_columns' ) );
		add_action( 'manage_pppd_drift_posts_custom_column', array( $this, 'drift_column_content' ), 10, 2 );
	}

	/**
	 * Print a linked report title for a `_pppd_report_id` style meta value.
	 *
	 * @param int $report_id Report post ID.
	 * @return void
	 */
	protected function print_report_link( $report_id ) {
		$report = ( $report_id > 0 ) ? get_post( $report_id ) : null;

		if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
			echo '&#8212;';

			return;
		}

		$edit_link = get_edit_post_link( $report->ID );

		if ( is_string( $edit_link ) && '' !== $edit_link ) {
			printf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $edit_link ),
				esc_html( get_the_title( $report ) )
			);
		} else {
			echo esc_html( get_the_title( $report ) );
		}
	}

	/**
	 * Section columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function section_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['pppd_report'] = __( 'Report', 'pretty-professional-process-docs' );
				$new['pppd_req_id'] = __( 'Req ID', 'pretty-professional-process-docs' );
			}

			$new[ $key ] = $label;
		}

		return $new;
	}

	/**
	 * Section column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function section_column_content( $column, $post_id ) {
		if ( 'pppd_report' === $column ) {
			$this->print_report_link( absint( get_post_meta( $post_id, '_pppd_report_id', true ) ) );
		} elseif ( 'pppd_req_id' === $column ) {
			$req_id = (string) get_post_meta( $post_id, '_pppd_req_id', true );
			echo ( '' !== $req_id ) ? '<code>' . esc_html( $req_id ) . '</code>' : '&#8212;';
		}
	}

	/**
	 * Change columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function change_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['pppd_target']        = __( 'Target Section', 'pretty-professional-process-docs' );
				$new['pppd_change_status'] = __( 'Status', 'pretty-professional-process-docs' );
			}

			$new[ $key ] = $label;
		}

		return $new;
	}

	/**
	 * Change column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function change_column_content( $column, $post_id ) {
		if ( 'pppd_target' === $column ) {
			$section_id = absint( get_post_meta( $post_id, '_pppd_target_section', true ) );
			$section    = ( $section_id > 0 ) ? get_post( $section_id ) : null;

			if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
				echo '&#8212;';

				return;
			}

			$edit_link = get_edit_post_link( $section->ID );

			if ( is_string( $edit_link ) && '' !== $edit_link ) {
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $edit_link ),
					esc_html( get_the_title( $section ) )
				);
			} else {
				echo esc_html( get_the_title( $section ) );
			}
		} elseif ( 'pppd_change_status' === $column ) {
			$status = get_post_status( $post_id );
			$labels = array(
				'pending'       => __( 'Pending review', 'pretty-professional-process-docs' ),
				'publish'       => __( 'Approved', 'pretty-professional-process-docs' ),
				'pppd_rejected' => __( 'Rejected', 'pretty-professional-process-docs' ),
			);

			echo isset( $labels[ $status ] ) ? esc_html( $labels[ $status ] ) : esc_html( (string) $status );
		}
	}

	/**
	 * Drift columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function drift_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['pppd_report'] = __( 'Report', 'pretty-professional-process-docs' );
			}

			$new[ $key ] = $label;
		}

		return $new;
	}

	/**
	 * Drift column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function drift_column_content( $column, $post_id ) {
		if ( 'pppd_report' === $column ) {
			$this->print_report_link( absint( get_post_meta( $post_id, '_pppd_report_id', true ) ) );
		}
	}
}
