<?php
/**
 * LifterLMS_Advanced_Quizzes class
 *
 * @package LifterLMS_Advanced_Quizzes/Main
 *
 * @since 1.0.0
 * @version 3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LifterLMS Advanced Quizzes Main Class
 *
 * @since 1.0.0
 */
final class LifterLMS_Advanced_Quizzes {

	/**
	 * Current version of the plugin
	 *
	 * @var string
	 */
	public $version = '3.3.0';

	/**
	 * Singleton instance of the class
	 *
	 * @var LifterLMS_Advanced_Quizzes
	 */
	private static $instance = null;

	/**
	 * Singleton Instance of the LifterLMS_Advanced_Quizzes class
	 *
	 * @since 1.0.0
	 *
	 * @return LifterLMS_Advanced_Quizzes
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Only load text domain if plugin requirements are met.
	 *
	 * @return void
	 */
	private function __construct() {

		// Define plugin constants.
		$this->define_constants();

		// Get started.
		add_action( 'plugins_loaded', array( $this, 'init' ) );

	}

	/**
	 * Determines if plugin requirements are met.
	 *
	 * @since 2.0.0
	 * @since 3.1.0 Raised minimum LifterLMS required version to 7.4.0-alpha.
	 * @since 3.2.0 Raised minimum LifterLMS required version to 7.4.1-alpha.
	 *
	 * @return boolean
	 */
	private function can_load() {
		return function_exists( 'llms' ) && version_compare( '7.4.1-alpha', llms()->version, '<=' );
	}

	/**
	 * Define all constants used by the plugin
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved plugin file and dir constants to main plugin file.
	 *
	 * @return void
	 */
	private function define_constants() {

		if ( ! defined( 'LLMS_ADVANCED_QUIZZES_VERSION' ) ) {
			define( 'LLMS_ADVANCED_QUIZZES_VERSION', $this->version );
		}

	}

	/**
	 * Include files and instantiate classes.
	 *
	 * @since 1.0.0
	 * @since 1.0.8 Require notification files.
	 * @since 2.0.0 Removed `includes/class-llms-aq-l10n.php`.
	 * @since 3.1.0 Added `includes/class-llms-aq-question-bank.php`.
	 *
	 * @return void
	 */
	private function includes() {

		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/llms-aq-functions.php';

		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/class-llms-aq-ajax.php';
		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/class-llms-aq-assets.php';
		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/class-llms-aq-install.php';
		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/class-llms-aq-question-types.php';
		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/class-llms-aq-question-bank.php';

		require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/notifications/class-llms-aq-notifications.php';

		if ( is_admin() ) {
			require_once LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/class-llms-aq-reporting-tables.php';
		}

	}

	/**
	 * Include all required files and classes
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Minimum LLMS core version increased to 3.29.0.
	 * @since 2.0.0 Use {@see LifterLMS_Advanced_Quizzes::can_load()` to determine if the plugin can be initialized.
	 *
	 * @return void
	 */
	public function init() {

		// Only load if we have the minimum LifterLMS version installed & activated.
		if ( $this->can_load() ) {
			add_action( 'init', array( $this, 'load_textdomain' ), 0 );
			$this->includes();
		}

	}

	/**
	 * Loads l10n files.
	 *
	 * Language files can be found in the following locations (The first loaded file takes priority):
	 *
	 *   1. wp-content/languages/lifterlms/lifterlms-advanced-quizzes-{LOCALE}.mo
	 *
	 *      This is recommended "safe" location where custom language files can be stored. A file
	 *      stored in this directory will never be automatically overwritten.
	 *
	 *   2. wp-content/languages/plugins/lifterlms-advanced-quizzes-{LOCALE}.mo
	 *
	 *      This is the default directory where WordPress will download language files from the
	 *      WordPress GlotPress server during updates. If you store a custom language file in this
	 *      directory it will be overwritten during updates.
	 *
	 *   3. wp-content/plugins/lifterlms/languages/lifterlms-advanced-quizzes-{LOCALE}.mo
	 *
	 *      This is the the LifterLMS plugin directory. A language file stored in this directory will
	 *      be removed from the server during a LifterLMS plugin update.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Use `llms_load_textdomain()`.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		llms_load_textdomain( 'lifterlms-advanced-quizzes', LLMS_ADVANCED_QUIZZES_PLUGIN_DIR, 'i18n' );
	}

}
