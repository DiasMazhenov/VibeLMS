<?php
/**
 * LifterLMS Advanced Quizzes functions
 *
 * @package LifterLMS_Advanced_Quizzes/Funcions
 *
 * @since 1.0.0
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieve a list of core supported codemirror modes
 *
 * @since 1.0.0
 *
 * @return array
 */
function llms_aq_get_cm_modes() {
	return apply_filters(
		'llms_aq_get_cm_modes',
		array(
			'clike'        => __( 'clike', 'lifterlms-advanced-quizzes' ),
			'coffeescript' => __( 'CoffeeScript', 'lifterlms-advanced-quizzes' ),
			'css'          => __( 'CSS', 'lifterlms-advanced-quizzes' ),
			'htmlmixed'    => __( 'HTML', 'lifterlms-advanced-quizzes' ),
			'javascript'   => __( 'Javascript', 'lifterlms-advanced-quizzes' ),
			'markdown'     => __( 'Markdown', 'lifterlms-advanced-quizzes' ),
			'nginx'        => __( 'NGINX', 'lifterlms-advanced-quizzes' ),
			'php'          => __( 'PHP', 'lifterlms-advanced-quizzes' ),
			'python'       => __( 'Python', 'lifterlms-advanced-quizzes' ),
			'ruby'         => __( 'Ruby', 'lifterlms-advanced-quizzes' ),
			'sass'         => __( 'SASS', 'lifterlms-advanced-quizzes' ),
			'sql'          => __( 'SQL', 'lifterlms-advanced-quizzes' ),
			'xml'          => __( 'XML', 'lifterlms-advanced-quizzes' ),
			'yaml'         => __( 'YAML', 'lifterlms-advanced-quizzes' ),
		)
	);
}

/**
 * Ensure mime types have a dot on the extension
 *
 * @since 1.0.0
 * @since 3.0.0 Fix issue encountered when passing in an extension that already starts with a leading dot.
 *
 * @param string $ext Extension suffix.
 * @return string
 */
function llms_aq_mime_ensure_dot( $ext ) {

	if ( 0 !== strpos( $ext, '.' ) ) {
		$ext = '.' . $ext;
	}

	return $ext;

}

/**
 * Convert WP mime types to an array of extensions
 *
 * @since 1.0.0
 *
 * @param array $types Mime types from WP core.
 * @return array
 */
function llms_aq_mimes_to_array( $types ) {

	$mimes = array();

	foreach ( $types as $type ) {
		$mimes = array_merge( $mimes, explode( '|', $type ) );
	}

	$mimes = array_map( 'llms_aq_mime_ensure_dot', $mimes );

	return $mimes;
}

/**
 * Convert an array of mime type extensions to a comma separated string
 *
 * @since 1.0.0
 *
 * @param array $types Mime types from WP core.
 * @return string
 */
function llms_aq_mimes_to_string( $types ) {
	return implode( ', ', llms_aq_mimes_to_array( $types ) );
}

/**
 * Add template directory to LifterLMS template overrides
 *
 * @since 1.0.0
 * @since 2.0.0 Push template directory onto the end of the templates array instead of the front.
 *
 * @param array $dirs Existing template directories.
 * @return array
 */
function llms_aq_template_overrides( $dirs ) {

	$dirs[] = LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'templates';
	return $dirs;

}
add_filter( 'lifterlms_theme_override_directories', 'llms_aq_template_overrides', 10, 1 );
