<?php
/**
 * Admin Dashboard Widget
 *
 * @package LifterLMS/Admin/Classes
 *
 * @since 7.2.0
 * @version 7.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin Dashboard Widget class.
 *
 * @since 7.2.0
 */
class LLMS_Admin_Dashboard_Widget {

	/**
	 * Constructor.
	 *
	 * @since 7.2.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Add the dashboard widget.
	 *
	 * @since 7.2.0
	 * @since 7.3.0 Add dashboard widget only if the current user can `manage_lifterlms`.
	 *
	 * @return void
	 */
	public function add_dashboard_widget() {

		if ( ! current_user_can( 'manage_lifterlms' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'llms_dashboard_widget',
			'VibeLMS ' . __( 'Quick Links', 'lifterlms' ),
			array( $this, 'output' )
		);
	}

	/**
	 * Output the dashboard widget.
	 *
	 * @since 7.2.0
	 *
	 * @return void
	 */
	public function output() {
		?>
		<div class="llms-dashboard-widget-wrap">
			<h3><?php esc_html_e( 'Activity this week:', 'lifterlms' ); ?></h3>
			<a class="llms-button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=course' ) ); ?>">
				<i class="fa fa-graduation-cap" aria-hidden="true"></i>
				<?php esc_html_e( 'Create a New Course', 'lifterlms' ); ?>
			</a>
		</div>
		<div class="activity-block">
			<?php echo $this->get_widgets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in template file. ?>
		</div>
		<?php
	}

	/**
	 * Get the widget HTML.
	 *
	 * @since 7.2.0
	 *
	 * @return string
	 */
	private function get_widgets(): string {
		return llms_get_template(
			'admin/reporting/tabs/widgets.php',
			array(
				'json'        => wp_json_encode(
					array(
						'current_tab'         => 'settings',
						'current_range'       => 'last-7-days',
						'current_students'    => array(),
						'current_courses'     => array(),
						'current_memberships' => array(),
						'dates'               => array(
							'start' => date( 'Y-m-d', strtotime( '-1 week' ) ),
							'end'   => current_time( 'Y-m-d' ),
						),
					)
				),
				'widget_data' => array( self::get_dashboard_widget_data() ),
			)
		) ?? '';
	}

	/**
	 * Get dashboard widget data.
	 *
	 * @since 7.3.0
	 *
	 * @return array $widget_data Array of data that will feed the dashboard widget.
	 */
	public static function get_dashboard_widget_data() {
		$widget_data = apply_filters(
			/**
			 * Filters the dashboard widget data.
			 *
			 * @since 7.3.0
			 *
			 * @param array $widget_data Array of data that will feed the dashboard widget.
			 */
			'llms_dashboard_widget_data',
			array(
				'enrollments'       => array(
					'title'   => __( 'Enrollments', 'lifterlms' ),
					'cols'    => '1-4',
					'content' => __( 'loading...', 'lifterlms' ),
					'info'    => __( 'Number of total enrollments during the selected period', 'lifterlms' ),
					'link'    => admin_url( 'admin.php?page=llms-reporting&tab=enrollments' ),
				),
				'registrations'     => array(
					'title'   => __( 'Registrations', 'lifterlms' ),
					'cols'    => '1-4',
					'content' => __( 'loading...', 'lifterlms' ),
					'info'    => __( 'Number of total user registrations during the selected period', 'lifterlms' ),
					'link'    => admin_url( 'admin.php?page=llms-reporting&tab=students' ),
				),
				'sold'              => array(
					'title'   => __( 'Net Sales', 'lifterlms' ),
					'cols'    => '1-4',
					'content' => __( 'loading...', 'lifterlms' ),
					'info'    => __( 'Total of all successful transactions during this period', 'lifterlms' ),
					'link'    => admin_url( 'admin.php?page=llms-reporting&tab=sales' ),
				),
				'lessoncompletions' => array(
					'title'   => __( 'Lessons Completed', 'lifterlms' ),
					'cols'    => '1-4',
					'content' => __( 'loading...', 'lifterlms' ),
					'info'    => __( 'Number of total lessons completed during the selected period', 'lifterlms' ),
					'link'    => admin_url( 'admin.php?page=llms-reporting&tab=courses' ),
				),
			)
		);
		$visibility = get_option( LLMS_VibeLMS_Platform::DASHBOARD_METRICS_OPTION, array() );

		if ( ! is_array( $visibility ) || empty( $visibility ) ) {
			return $widget_data;
		}

		$visible = array_filter(
			$widget_data,
			function ( $widget, $id ) use ( $visibility ) {
				return ! array_key_exists( $id, $visibility ) || 'yes' === $visibility[ $id ];
			},
			ARRAY_FILTER_USE_BOTH
		);

		// Keep the dashboard useful if an administrator unchecks every built-in metric.
		return $visible ? $visible : $widget_data;
	}
}
return new LLMS_Admin_Dashboard_Widget();
