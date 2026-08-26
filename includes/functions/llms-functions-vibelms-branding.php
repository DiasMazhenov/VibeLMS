<?php
/**
 * VibeLMS branding filters.
 *
 * @package VibeLMS/Functions
 * @since 0.0.14
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace the upstream product name in translated UI strings only.
 * Internal classes, hooks, post types, shortcodes and the text domain stay
 * unchanged so existing content and Elementor documents remain compatible.
 *
 * @param string $translation Translated string.
 * @param string $text        Original string.
 * @param string $domain      Translation domain.
 * @return string
 */
function llms_vibelms_brand_translation( $translation, $text, $domain ) {
	if ( ! in_array( $domain, array( 'lifterlms', 'lifterlms-advanced-quizzes' ), true ) ) {
		return $translation;
	}

	return str_replace( array( 'LifterLMS', 'Lifterlms' ), 'VibeLMS', $translation );
}

add_filter( 'gettext', 'llms_vibelms_brand_translation', 20, 3 );

// Do not expose upstream paid add-on promotions in the reusable fork.
add_filter( 'llms_access_plan_dialog_show_gifts_addon_option', '__return_false' );
add_filter( 'llms_access_plan_dialog_show_group_addon_option', '__return_false' );
add_filter( 'lifterlms_disable_addons_screen', '__return_true' );
