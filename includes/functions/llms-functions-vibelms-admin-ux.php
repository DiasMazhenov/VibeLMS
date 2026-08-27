<?php
/**
 * VibeLMS admin UX helpers.
 *
 * @package VibeLMS/Functions
 * @since 0.0.25
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine whether the simplified VibeLMS admin mode is enabled.
 *
 * The mode only changes navigation visibility. It does not remove content,
 * capabilities, database records or the existing course builder.
 *
 * @return bool
 */
function llms_vibelms_is_simplified_admin() {
	$mode = get_option( 'vibelms_admin_mode', 'simple' );
	$mode = apply_filters( 'vibelms_admin_mode', $mode );

	return 'advanced' !== sanitize_key( (string) $mode );
}

/**
 * Hide legacy and optional admin surfaces from the simplified navigation.
 *
 * Direct URLs continue to work for administrators, which makes the change
 * reversible and avoids breaking existing integrations.
 *
 * @return void
 */
function llms_vibelms_simplify_admin_menu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		remove_submenu_page( 'lifterlms', 'llms-status' );
	}

	if ( ! llms_vibelms_is_simplified_admin() || ! current_user_can( 'manage_lifterlms' ) ) {
		return;
	}

	foreach ( array(
		'edit.php?post_type=llms_engagement',
		'edit.php?post_type=llms_order',
		'edit.php?post_type=llms_coupon',
		'edit.php?post_type=llms_voucher',
		'edit.php?post_type=llms_certificate',
		'edit.php?post_type=llms_email',
	) as $menu_slug ) {
		remove_menu_page( $menu_slug );
	}

	foreach ( array(
		'edit.php?post_type=llms_form',
		'llms-reporting',
		'llms-resources',
		'llms-add-ons',
	) as $submenu_slug ) {
		remove_submenu_page( 'lifterlms', $submenu_slug );
	}

}

add_action( 'admin_menu', 'llms_vibelms_simplify_admin_menu', 999 );
