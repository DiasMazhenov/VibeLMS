<?php
/**
 * Advanced Quiz notifications
 *
 * @package LifterLMS_Advanced_Quizzes/Classes/Notifications
 *
 * @since 1.0.8
 * @version 1.0.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * AQ notifications
 *
 * @since 1.0.8
 */
class LLMS_AQ_Notifications {

	/**
	 * Constructor
	 *
	 * @since 1.0.8
	 *
	 * @return void
	 */
	public function __construct() {

		// Only load if we have the minimum LifterLMS version installed & activated.
		if ( function_exists( 'LLMS' ) && version_compare( '3.24.0', LLMS()->version, '<=' ) ) {
			add_action( 'llms_notifications_loaded', array( $this, 'load' ) );
		}

	}

	/**
	 * Load custom notifications
	 *
	 * @since 1.0.8
	 *
	 * @param obj $manager Instance of LLMS()->notifications.
	 * @return void
	 */
	public function load( $manager ) {

		$manager->load_controller( 'quiz_awaiting_review', LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/notifications/class-llms-aq-notification-controller-quiz-awaiting-review.php' );
		$manager->load_view( 'quiz_awaiting_review', LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/notifications/class-llms-aq-notification-view-quiz-awaiting-review.php', 'LLMS_AQ' );

	}

}

return new LLMS_AQ_Notifications();
