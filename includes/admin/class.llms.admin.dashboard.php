<?php
/**
 * Admin Dashboard Screen
 *
 * @package LifterLMS/Admin/Classes
 *
 * @since 7.1.0
 * @version 7.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin Dashboard Screen class.
 *
 * @since 7.1.0
 */
class LLMS_Admin_Dashboard {

	/**
	 * Return the dashboard activity period from the current request.
	 *
	 * @return array{range:string,dates:array<string,string>,ranges:array<string,string>}
	 */
	public static function get_activity_period() {
		$ranges = array(
			'last-7-days'  => __( 'Последние 7 дней', 'lifterlms' ),
			'last-30-days' => __( 'Последние 30 дней', 'lifterlms' ),
			'last-90-days' => __( 'Последние 90 дней', 'lifterlms' ),
			'this-month'   => __( 'Текущий месяц', 'lifterlms' ),
			'custom'       => __( 'Произвольный период', 'lifterlms' ),
		);
		$range = LLMS_Admin_Reporting::get_current_range();

		if ( ! isset( $ranges[ $range ] ) ) {
			$range = 'last-7-days';
		}

		$dates = LLMS_Admin_Reporting::get_dates( $range );
		if ( 'custom' === $range && ( ! self::is_valid_date( $dates['start'] ) || ! self::is_valid_date( $dates['end'] ) || $dates['start'] > $dates['end'] ) ) {
			$range = 'last-7-days';
			$dates = LLMS_Admin_Reporting::get_dates( $range );
		}

		return array(
			'range'  => $range,
			'dates'  => $dates,
			'ranges' => $ranges,
		);
	}

	/**
	 * Format a dashboard date using the WordPress site locale and timezone.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return string
	 */
	public static function format_activity_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_object ? wp_date( get_option( 'date_format' ), $date_object->getTimestamp(), wp_timezone() ) : $date;
	}

	/**
	 * Validate an ISO date without accepting PHP's date normalization.
	 *
	 * @param string $date Date to validate.
	 * @return bool
	 */
	private static function is_valid_date( $date ) {
		$date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$errors      = DateTimeImmutable::getLastErrors();

		return $date_object && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date_object->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Retrieve an instance of the WP_Screen for the dashboard screen.
	 *
	 * @since 7.1.0
	 *
	 * @return WP_Screen|boolean Returns a `WP_Screen` object when on the dashboard screen, otherwise returns `false`.
	 */
	public static function get_screen() {

		$screen = get_current_screen();
		if ( $screen instanceof WP_Screen && 'lifterlms_page_llms-dashboard' === $screen->id ) {
			return $screen;
		}

		return false;
	}

	/**
	 * Register Dashboard's meta boxes.
	 *
	 * @since 7.1.0
	 *
	 * @return void
	 */
	public static function register_meta_boxes() {

		add_meta_box(
			'llms_dashboard_quick_links',
			__( 'Quick Links', 'lifterlms' ),
			array( __CLASS__, 'meta_box' ),
			'toplevel_page_llms-dashboard',
			'normal',
			'default',
			array( 'view' => 'quick-links' )
		);

		/**
		 * Fired after adding the meta boxes on the LifterLMS admin dashboard page.
		 *
		 * Third parties can hook here to remove LifterLMS core meta boxes.
		 *
		 * @since 7.1.0
		 */
		do_action( 'llms_dashboard_meta_boxes_added' );
	}

	/**
	 * Prints the dashboard's meta box html.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $data_object Often this is the object that's the focus of the current screen,
	 *                           for example a `WP_Post` or `WP_Comment` object.
	 * @param array $box         Meta Box configuration array.
	 * @return void
	 */
	public static function meta_box( $data_object, $box ) {

		if ( isset( $box['args']['view'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in template files.
			echo self::get_view( $box['args']['view'] );
		}
	}

	/**
	 * Handle HTML output on the screen.
	 *
	 * @since 7.1.0
	 *
	 * @return void
	 */
	public static function output() {
		include 'views/dashboard.php';
	}

	/**
	 * Retrieves the HTML of a view from the views/dashboard directory.
	 *
	 * @since 7.1.0
	 *
	 * @param string $file The file basename of the view to retrieve.
	 * @return string The HTML content of the view.
	 */
	private static function get_view( $file ) {

		ob_start();
		include 'views/dashboard/' . $file . '.php';
		return ob_get_clean();
	}
}
