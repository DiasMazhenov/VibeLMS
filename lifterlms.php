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
 * Version: 0.0.08
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
 * Reporting a Security Vulnerability      *
 *                                         *
 * Please disclose any security issues or  *
 * vulnerabilities to team@lifterlms.com   *
 *                                         *
 * See our full Security Policy at         *
 * https://lifterlms.com/security-policy   *
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
	define( 'VIBELMS_VERSION', '0.0.08' );
}

// Autoloader.
require_once LLMS_PLUGIN_DIR . 'vendor/autoload.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-loader.php';
require_once LLMS_PLUGIN_DIR . 'includes/functions/llms-functions-vibelms-roles.php';

if ( ! class_exists( 'LifterLMS' ) ) {
	require_once LLMS_PLUGIN_DIR . 'class-lifterlms.php';
}

register_activation_hook( __FILE__, array( 'LLMS_Install', 'install' ) );
register_activation_hook( __FILE__, 'llms_vibelms_roles_install' );

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
return llms();
