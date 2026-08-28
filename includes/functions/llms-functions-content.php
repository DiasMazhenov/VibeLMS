<?php
/**
 * (Post) Content functions
 *
 * @package LifterLMS/Functions
 *
 * @since 3.25.1
 * @version 4.17.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'llms_get_post_content' ) ) {

	/**
	 * Replace the saved post content with the Elementor document when available.
	 *
	 * Course templates render their full description directly and therefore do
	 * not always pass through the WordPress `the_content` filter. Keeping this
	 * resolver separate lets both paths use the same protected renderer.
	 *
	 * @param string  $content  Fallback post content.
	 * @param integer $post_id  Optional post ID.
	 * @return string
	 */
	function llms_get_elementor_post_content( $content, $post_id = 0 ) {

		global $post;
		$post_id = $post_id ? absint( $post_id ) : ( $post instanceof WP_Post ? $post->ID : 0 );

		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'course', 'lesson', 'llms_quiz', 'llms_membership' ), true ) ) {
			return $content;
		}

		$restrictions = llms_page_restricted( $post_id );
		if ( $restrictions['is_restricted'] ||
			! function_exists( 'llms_is_elementor_post' ) ||
			! llms_is_elementor_post( $post_id ) ||
			! class_exists( 'Elementor\\Plugin' ) ) {
			return $content;
		}

		$elementor = \Elementor\Plugin::instance();
		$frontend  = $elementor ? $elementor->frontend : false;
		static $rendering_elementor_posts = array();

		if ( ! $frontend ||
			! method_exists( $frontend, 'get_builder_content_for_display' ) ||
			! empty( $rendering_elementor_posts[ $post_id ] ) ) {
			return $content;
		}

		$rendering_elementor_posts[ $post_id ] = true;
		try {
			$elementor_content = $frontend->get_builder_content_for_display( $post_id, true );
			if ( is_string( $elementor_content ) && '' !== trim( $elementor_content ) ) {
				$content = $elementor_content;
			}
		} catch ( Throwable $error ) {
			if ( function_exists( 'llms_vibelms_diagnostics_log' ) ) {
				llms_vibelms_diagnostics_log(
					'error',
					'Elementor content rendering failed',
					array(
						'post_id' => $post_id,
						'error'   => $error->getMessage(),
					)
				);
			}
		} finally {
			unset( $rendering_elementor_posts[ $post_id ] );
		}

		return $content;
	}

	/**
	 * Resolve Elementor content for templates that render full descriptions directly.
	 *
	 * @param string $content Fallback full description.
	 * @return string
	 */
	function llms_get_elementor_full_description( $content ) {
		return llms_get_elementor_post_content( $content );
	}

	add_filter( 'lifterlms_full_description', 'llms_get_elementor_full_description', 10, 1 );

	/**
	 * Post Template Include
	 *
	 * Adds LifterLMS template content before and after the post's default content.
	 *
	 * @since 1.0.0
	 * @since 3.25.2 Unknown.
	 * @since 4.17.0 Refactored.
	 *
	 * @param string $content WP_Post post_content.
	 * @return string
	 */
	function llms_get_post_content( $content ) {

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		$restrictions = llms_page_restricted( $post->ID );

		// Elementor stores the visual content in post meta, not post_content.
		// Render it through the shared content pipeline so LMS templates keep
		// their access checks and before/after hooks.
		$content = llms_get_elementor_post_content( $content, $post->ID );

		if ( in_array( $post->post_type, array( 'course', 'llms_membership', 'lesson', 'llms_quiz' ), true ) ) {

			$post_type       = str_replace( 'llms_', '', $post->post_type );
			$template_before = 'single-' . $post_type . '-before';
			$template_after  = 'single-' . $post_type . '-after';

			if ( $restrictions['is_restricted'] ) {
				$content = llms_get_post_sales_page_content( $post, $content );
				if ( in_array( $post->post_type, array( 'lesson', 'llms_quiz' ), true ) ) {
					$content         = '';
					$template_before = 'no-access-before';
					$template_after  = 'no-access-after';
				}
			}

			ob_start();
			load_template( llms_get_template_part_contents( 'content', $template_before ), false );
			$before = ob_get_clean();

			ob_start();
			load_template( llms_get_template_part_contents( 'content', $template_after ), false );
			$after = ob_get_clean();

			$content = do_shortcode( $before . $content . $after );

		}

		/**
		 * Filter the post_content of a LifterLMS post type.
		 *
		 * @since Unknown
		 *
		 * @param string  $content      Post content.
		 * @param WP_Post $post         Post object.
		 * @param array   $restrictions Result from `llms_page_restricted()` for the current post.
		 */
		return apply_filters( 'llms_get_post_content', $content, $post, $restrictions );

	}
}

/**
 * Retrieve the sales page content for a course or membership
 *
 * By default only courses and memberships support sales pages, the meta property
 * must be set to `content` or an empty string, and the post must have a `post_excerpt`
 * property value.
 *
 * @since 4.17.0
 *
 * @param WP_Post $post    The post object.
 * @param string  $default Optional. Default content to use when no override content can be found.
 * @return string
 */
function llms_get_post_sales_page_content( $post, $default = '' ) {

	$content = $default;

	if ( post_type_supports( $post->post_type, 'llms-sales-page' ) ) {
		$sales_page = get_post_meta( $post->ID, '_llms_sales_page_content_type', true );
		if ( $post->post_excerpt && ( '' === $sales_page || 'content' === $sales_page ) ) {
			add_filter( 'the_excerpt', array( $GLOBALS['wp_embed'], 'autoembed' ), 9 );
			$content = llms_get_excerpt( $post->ID );
		}
	}

	/**
	 * Filters the HTML content of a LifterLMS post type's sales page content
	 *
	 * @since 4.17.0
	 *
	 * @param string  $content HTML content of the sales page.
	 * @param WP_Post $content Post object.
	 * @param string  $default Default content used when no override content can be found.
	 */
	return apply_filters( 'llms_post_sales_page_content', $content, $post, $default );

}

/**
 * Initialize LifterLMS post type content filters
 *
 * This method is used to determine whether or `llms_get_post_content()` should automatically
 * be added as a filter callback for the WP core `the_content` filter.
 *
 * When working with posts on the admin panel (during course building, importing) we don't want
 * other plugins that may desire running `apply_filters( 'the_content', $content )` to apply our
 * plugin's filters.
 *
 * @since 4.17.0
 *
 * @param callable $callback Optional. Callback function to be added as a callback for the filter `the_content`. Default 'llms_get_post_content'.
 * @param integer  $priority Optional. Priority used when adding the filter. Default: 10.
 * @return boolean Returns `true` if content filters are added and `false` if not.
 */
function llms_post_content_init( $callback = 'llms_get_post_content', $priority = 10 ) {

	// Don't filter post content on the admin panel.
	$should_filter = ( false === is_admin() );

	/**
	 * Filters whether or not LifterLMS content filters should be applied.
	 *
	 * @since 4.17.0
	 *
	 * @param boolean  $should_filter Whether or not to filter the content.
	 * @param callable $callback      Callback function to be added as a callback for the filter `the_content`.
	 */
	if ( apply_filters( 'llms_should_filter_post_content', $should_filter, $callback ) ) {
		return add_filter( 'the_content', $callback, $priority );
	}

	return false;

}

llms_post_content_init();
