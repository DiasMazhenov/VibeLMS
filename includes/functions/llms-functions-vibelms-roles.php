<?php
/**
 * VibeLMS roles for the training platform.
 *
 * @package VibeLMS/Functions
 * @since 0.1.0
 * @version 0.1.0
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

	update_option( 'vibelms_roles_version', '1', false );
}

/**
 * Install roles after an update of an already-active plugin.
 *
 * @return void
 */
function llms_vibelms_roles_maybe_install() {
	if ( '1' !== get_option( 'vibelms_roles_version' ) ) {
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
