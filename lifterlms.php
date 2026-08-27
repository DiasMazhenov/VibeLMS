<?php
/**
 * Main VibeLMS plugin file
 *
 * @package LifterLMS/Main
 *
 * @since 1.0.0
 * @version 5.3.0
 *
 * Plugin Name: VibeLMS
 * Plugin URI: https://mazhenov.kz
 * Description: VibeLMS learning platform foundation for a modern training portal.
 * Version: 0.0.44
 * Author: Mazhenov Design
 * Author URI: https://mazhenov.kz
 * Text Domain: lifterlms
 * Domain Path: /languages
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.9
 * Tested up to: 7.1
 * Requires PHP: 7.4
 *
 * * * * * * * * * * * * * * * * * * * * * *
 *                                         *
 * Project support                          *
 *                                         *
 * Please report issues through the project *
 * website: https://mazhenov.kz             *
 *                                         *
 * VibeLMS is an independent reusable LMS.  *
 *                                         *
 * * * * * * * * * * * * * * * * * * * * * *
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'LLMS_PLUGIN_FILE' ) ) {
	define( 'LLMS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'LLMS_PLUGIN_DIR' ) ) {
	define( 'LLMS_PLUGIN_DIR', __DIR__ . '/' );
}

if ( ! defined( 'VIBELMS_VERSION' ) ) {
	define( 'VIBELMS_VERSION', '0.0.44' );
}

// Autoloader.
require_once LLMS_PLUGIN_DIR . 'vendor/autoload.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-loader.php';
require_once LLMS_PLUGIN_DIR . 'includes/functions/llms-functions-vibelms-branding.php';
require_once LLMS_PLUGIN_DIR . 'includes/functions/llms-functions-vibelms-admin-ux.php';
require_once LLMS_PLUGIN_DIR . 'includes/functions/llms-functions-vibelms-roles.php';
require_once LLMS_PLUGIN_DIR . 'includes/class.llms.vibelms.platform.php';
require_once LLMS_PLUGIN_DIR . 'includes/class.llms.vibelms.transfer.php';
require_once LLMS_PLUGIN_DIR . 'includes/class.llms.vibelms.content.php';

if ( ! class_exists( 'LifterLMS' ) ) {
	require_once LLMS_PLUGIN_DIR . 'class-lifterlms.php';
}

// Bundle Advanced Quizzes into VibeLMS so it does not need a separate plugin.
if ( ! function_exists( 'llms_aq' ) ) {
	if ( ! defined( 'LLMS_ADVANCED_QUIZZES_PLUGIN_FILE' ) ) {
		define( 'LLMS_ADVANCED_QUIZZES_PLUGIN_FILE', LLMS_PLUGIN_FILE );
	}
	if ( ! defined( 'LLMS_ADVANCED_QUIZZES_PLUGIN_DIR' ) ) {
		define( 'LLMS_ADVANCED_QUIZZES_PLUGIN_DIR', LLMS_PLUGIN_DIR );
	}
	if ( ! defined( 'LLMS_ADVANCED_QUIZZES_PLUGIN_URL' ) ) {
		define( 'LLMS_ADVANCED_QUIZZES_PLUGIN_URL', trailingslashit( plugin_dir_url( LLMS_PLUGIN_FILE ) ) );
	}
	require_once LLMS_PLUGIN_DIR . 'includes/class-lifterlms-advanced-quizzes.php';
}

if ( function_exists( 'llms_aq' ) ) {
	llms_aq();
}

register_activation_hook( __FILE__, array( 'LLMS_Install', 'install' ) );
register_activation_hook( __FILE__, 'llms_vibelms_roles_install' );
register_activation_hook( __FILE__, 'llms_vibelms_platform_install' );

require_once LLMS_PLUGIN_DIR . 'includes/llms-notifications.php';

/**
 * Returns the main instance of LifterLMS
 *
 * @since 4.0.0
 *
 * @return LifterLMS
 */
function llms() {
	return LifterLMS::instance();
}

llms_vibelms_platform();
llms_vibelms_transfer();
llms_vibelms_content();

return llms();
