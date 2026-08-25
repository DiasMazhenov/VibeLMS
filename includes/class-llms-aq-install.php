<?php
/**
 * Plugin installation
 *
 * @package LifterLMS_Advanced_Quizzes/Classes
 *
 * @since 1.0.6
 * @version 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin installation
 *
 * @since 1.0.6
 */
class LLMS_AQ_Install {

	/**
	 * Key name for database version option
	 *
	 * @var string
	 */
	private static $option_name = 'llms_aq_version';

	/**
	 * Database updates by version
	 *
	 * @var array[]
	 */
	private static $db_updates = array(
		'1.0.7' => array(
			array( __CLASS__, '_update_106_fix_reorder_choices' ),
		),
	);

	/**
	 * Initialize the install class
	 * Hooks all actions
	 *
	 * @since 1.0.6
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
	}

	/**
	 * Checks the current LLMS version and runs installer if required
	 *
	 * @since 1.0.6
	 * @since 1.1.0 Use `llms_aq()` in favor of deprecated `LLMS_Advanced_Quizzes()`.
	 *
	 * @return void
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && get_option( self::$option_name ) !== llms_aq()->version ) {
			self::install();
			do_action( 'llms_aq_updated' );
		}
	}

	/**
	 * Core install function
	 *
	 * @since 1.0.6
	 *
	 * @return void
	 */
	public static function install() {

		if ( ! is_blog_installed() ) {
			return;
		}

		/**
		 * Action run prior to the Advanced Quizzes database upgrade routines.
		 *
		 * @since 1.0.6
		 */
		do_action( 'llms_aq_before_install' );

		$current_db_version = get_option( self::$option_name );
		foreach ( self::$db_updates as $version => $callbacks ) {

			if ( version_compare( $current_db_version, $version, '<' ) ) {

				foreach ( $callbacks as $callback ) {
					call_user_func( $callback );
				}
			}
		}

		self::update_version();

		/**
		 * Action run after the Advanced Quizzes database upgrade routines.
		 *
		 * @since 1.0.6
		 */
		do_action( 'llms_aq_after_install' );

	}

	/**
	 * Update the LifterLMS version record to the latest version
	 *
	 * @since 1.0.6
	 * @since 1.1.0 Use `llms_aq()` in favor of deprecated `LLMS_Advanced_Quizzes()`.
	 *
	 * @param string $version Version number, if `null` uses current version as defined by the plugin.
	 * @return void
	 */
	public static function update_version( $version = null ) {
		delete_option( self::$option_name );
		add_option( self::$option_name, is_null( $version ) ? llms_aq()->version : $version );
	}

	/**
	 * Update the `multi_choices` property of all reorder & picture reorder questions to have `multi_choice` enabled
	 *
	 * Fixes issue causing reorders to always be graded incorrectly.
	 *
	 * @internal
	 *
	 * @since 1.0.6
	 * @since 1.0.7 Unknown.
	 *
	 * @return void
	 */
	public static function _update_106_fix_reorder_choices() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		global $wpdb;
		$posts = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT p.ID
			 FROM {$wpdb->posts} AS p
			 JOIN {$wpdb->postmeta} AS m ON p.ID = m.post_id AND m.meta_key = '_llms_question_type' AND ( m.meta_value = 'reorder' || m.meta_value = 'picture_reorder' )
			 WHERE p.post_type = 'llms_question'"
		);

		foreach ( $posts as $id ) {
			$question = llms_get_post( $id );
			$question->set( 'multi_choices', 'yes' );
			foreach ( $question->get_choices() as $choice ) {
				$choice->set( 'correct', true )->save();
			}
		}

	}

}

LLMS_AQ_Install::init();
