<?php
/**
 * VibeLMS roles for the training platform.
 *
 * @package VibeLMS/Functions
 * @since 0.0.01
 * @version 0.0.38
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the VibeLMS role definitions.
 *
 * @return array<string,array{label:string,caps:array<string,bool>}>
 */
function llms_vibelms_role_definitions() {
	return array(
		'vibelms_student'  => array(
			'label' => __( 'VibeLMS Student', 'lifterlms' ),
			'caps'  => array(
				'read'                    => true,
				'vibelms_access_learning' => true,
			),
		),
		'vibelms_observer' => array(
			'label' => __( 'VibeLMS Observer', 'lifterlms' ),
			'caps'  => array(
				'read'                       => true,
				'vibelms_view_reports'       => true,
				'vibelms_export_reports'     => true,
				'vibelms_download_materials' => true,
			),
		),
	);
}

/**
 * Install or refresh the VibeLMS roles.
 *
 * @return void
 */
function llms_vibelms_roles_install() {
	if ( ! function_exists( 'add_role' ) || ! function_exists( 'get_role' ) ) {
		return;
	}

	// Push-to-Deploy updates an active plugin without running its activation hook.
	// Refresh the core post-type capabilities so existing administrators can
	// create access groups after an update.
	if ( class_exists( 'LLMS_Roles' ) ) {
		LLMS_Roles::install();
	}

	foreach ( llms_vibelms_role_definitions() as $role_name => $definition ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			$role = add_role( $role_name, $definition['label'], $definition['caps'] );
		}
		if ( $role ) {
			foreach ( $definition['caps'] as $capability => $grant ) {
				if ( $grant ) {
					$role->add_cap( $capability );
				}
			}
		}
	}

	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'vibelms_view_reports' );
		$administrator->add_cap( 'vibelms_export_reports' );
		// Keep the compatible LifterLMS report links usable for administrators.
		$administrator->add_cap( 'view_lifterlms_reports' );
		$administrator->add_cap( 'view_others_lifterlms_reports' );
	}

	update_option( 'vibelms_roles_version', '4', false );
}

/**
 * Install roles after an update of an already-active plugin.
 *
 * @return void
 */
function llms_vibelms_roles_maybe_install() {
	if ( '4' !== get_option( 'vibelms_roles_version' ) ) {
		llms_vibelms_roles_install();
	}
}

/**
 * Remove VibeLMS roles during a full uninstall.
 *
 * @return void
 */
function llms_vibelms_roles_remove() {
	if ( function_exists( 'remove_role' ) ) {
		foreach ( array_keys( llms_vibelms_role_definitions() ) as $role_name ) {
			remove_role( $role_name );
		}
	}
	delete_option( 'vibelms_roles_version' );
}

add_action( 'admin_init', 'llms_vibelms_roles_maybe_install', 1 );
