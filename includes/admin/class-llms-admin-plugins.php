<?php
/**
 * LLMS_Admin_Plugins class
 *
 * @package LifterLMS/Admin/Classes
 *
 * @since 7.5.1
 * @version 7.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modifications to the plugins page.
 *
 * @since 7.5.1
 */
class LLMS_Admin_Plugins {

	/**
	 * Constructor
	 *
	 * @since 7.5.1
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( LLMS_PLUGIN_DIR . '/lifterlms.php' ), array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add links to the plugins page.
	 */
	public function plugin_action_links( $links ) {
		$new_links = array(
			'dashboard' => '<a href="' . esc_url( admin_url( 'admin.php?page=llms-dashboard' ) ) . '">' . __( 'Dashboard', 'lifterlms' ) . '</a>',
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=llms-settings' ) ) . '">' . __( 'Settings', 'lifterlms' ) . '</a>',
		);

		$links = array_merge( $new_links, $links );
		return $links;
	}

	/**
	 * Add links to plugin description.
	 */
	public function plugin_row_meta( $links, $file ) {
		return $links;
	}
}

return new LLMS_Admin_Plugins();
