<?php
/**
 * Opt-in VibeLMS diagnostics.
 *
 * @package VibeLMS/Diagnostics
 * @since 0.0.01
 * @version 0.0.01
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine whether verbose VibeLMS diagnostics are enabled.
 *
 * @return bool
 */
function llms_vibelms_diagnostics_enabled() {
	return defined( 'VIBELMS_DEBUG' ) && VIBELMS_DEBUG;
}

/**
 * Recursively redact sensitive values before writing diagnostics.
 *
 * @param mixed $value Value to sanitize.
 * @param int   $depth Current recursion depth.
 * @return mixed
 */
function llms_vibelms_diagnostics_sanitize_value( $value, $depth = 0 ) {
	if ( $depth > 4 ) {
		return '[depth limit]';
	}

	if ( is_string( $value ) ) {
		return strlen( $value ) > 2000 ? substr( $value, 0, 2000 ) . '...[truncated]' : $value;
	}

	if ( is_array( $value ) ) {
		$sanitized = array();
		foreach ( array_slice( $value, 0, 50, true ) as $key => $item ) {
			$key_string        = strtolower( (string) $key );
			$sanitized[ $key ] = preg_match( '/authorization|cookie|key|nonce|pass(word)?|secret|token/', $key_string )
				? '[redacted]'
				: llms_vibelms_diagnostics_sanitize_value( $item, $depth + 1 );
		}
		return $sanitized;
	}

	if ( is_object( $value ) ) {
		return '[object ' . get_class( $value ) . ']';
	}

	return $value;
}

/**
 * Write one structured diagnostic event to the existing LifterLMS logger.
 *
 * @param string $level   Event severity.
 * @param string $message Event message.
 * @param array  $context Event context.
 * @return void
 */
function llms_vibelms_diagnostics_log( $level, $message, $context = array() ) {
	static $logging = false;
	if ( $logging || ! llms_vibelms_diagnostics_enabled() || ! function_exists( 'llms_log' ) ) {
		return;
	}

	$logging = true;
	llms_log(
		wp_json_encode(
			array(
				'level'   => sanitize_key( $level ),
				'message' => sanitize_text_field( $message ),
				'context' => llms_vibelms_diagnostics_sanitize_value( $context ),
			)
		),
		'vibelms-diagnostics'
	);
	$logging = false;
}

/**
 * Convert a PHP error number into a readable severity.
 *
 * @param int $severity PHP error number.
 * @return string
 */
function llms_vibelms_diagnostics_error_level( $severity ) {
	if ( in_array( $severity, array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
		return 'critical';
	}
	if ( in_array( $severity, array( E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING ), true ) ) {
		return 'warning';
	}
	return 'notice';
}

/**
 * Log a PHP error and preserve normal PHP error handling.
 *
 * @param int    $severity Error severity.
 * @param string $message   Error message.
 * @param string $file     Source file.
 * @param int    $line     Source line.
 * @return bool
 */
function llms_vibelms_diagnostics_handle_error( $severity, $message, $file, $line ) {
	if ( ! ( error_reporting() & $severity ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting,WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Respect the active PHP error mask.
		return false;
	}

	llms_vibelms_diagnostics_log(
		llms_vibelms_diagnostics_error_level( $severity ),
		$message,
		array(
			'type' => $severity,
			'file' => $file,
			'line' => $line,
		)
	);

	return false;
}

/**
 * Log uncaught throwables.
 *
 * @param Throwable $throwable Throwable instance.
 * @return void
 */
function llms_vibelms_diagnostics_handle_exception( $throwable ) {
	llms_vibelms_diagnostics_log(
		'critical',
		'Uncaught throwable',
		array(
			'class' => get_class( $throwable ),
			'file'  => $throwable->getFile(),
			'line'  => $throwable->getLine(),
			'trace' => $throwable->getTraceAsString(),
		)
	);
}

/**
 * Log fatal shutdown errors.
 *
 * @return void
 */
function llms_vibelms_diagnostics_capture_shutdown() {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		llms_vibelms_diagnostics_log( 'critical', 'Fatal shutdown error', $error );
	}
}

/**
 * Install diagnostics handlers when explicitly enabled.
 *
 * @return void
 */
function llms_vibelms_diagnostics_init() {
	if ( ! llms_vibelms_diagnostics_enabled() ) {
		return;
	}

	set_error_handler( 'llms_vibelms_diagnostics_handle_error' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Explicit opt-in diagnostics.
	set_exception_handler( 'llms_vibelms_diagnostics_handle_exception' );
	register_shutdown_function( 'llms_vibelms_diagnostics_capture_shutdown' );
	llms_vibelms_diagnostics_log(
		'info',
		'Request started',
		array(
			'method'  => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
			'uri'     => isset( $_SERVER['REQUEST_URI'] ) ? strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' ) : '',
			'user_id' => get_current_user_id(),
			'ajax'    => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax(),
			'cron'    => function_exists( 'wp_doing_cron' ) && wp_doing_cron(),
			'rest'    => defined( 'REST_REQUEST' ) && REST_REQUEST,
		)
	);
}

add_action( 'plugins_loaded', 'llms_vibelms_diagnostics_init', 1 );
