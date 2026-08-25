<?php
/**
 * LifterLMS Elementor Widgets
 *
 * @package LifterLMS/Classes
 *
 * @since 7.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Elementor_Widgets
 *
 * @since 7.7.0
 */
class LLMS_Elementor_Widgets {

	/**
	 * Constructor.
	 *
	 * @since 7.7.0
	 *
	 * @return void
	 */
	public function __construct() {
		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'init' ) );
		} else {
			add_action( 'elementor/widgets/widgets_registered', array( $this, 'init' ) );
		}
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_widget_categories' ) );
		add_filter( 'llms_render_block', array( $this, 'maybe_stop_rendering_block' ), 10, 2 );
	}

	/**
	 * Avoid rendering blocks on the front-end that are in an Elementor page (ie. a Text Editor widget when page/post first edited).
	 *
	 * @param $should_render bool Whether to render the block or not.
	 * @param $block WP_Block The block instance.
	 *
	 * @return false|mixed
	 */
	function maybe_stop_rendering_block( $should_render, $block ) {
		if ( ! class_exists( 'Elementor\Plugin' ) || ! method_exists( 'Elementor\Plugin', 'instance' ) ) {
			return $should_render;
		}

		$instance = Elementor\Plugin::instance();

		if ( ! $instance ) {
			return $should_render;
		}

		$documents = $instance->documents;

		if ( ! $documents || ! method_exists( $documents, 'get' ) ) {
			return $should_render;
		}

		$document = $documents->get( get_the_ID() );

		if ( ! $document || ! method_exists( $document, 'is_built_with_elementor' ) ) {
			return $should_render;
		}

		if ( $document->is_built_with_elementor() ) {
			$should_render = false;
		}

		return $should_render;
	}

	public function init( $widgets_manager = null ) {
		static $initialized = false;
		if ( $initialized ) {
			return;
		}
		$initialized    = true;
		$widgets_manager = $widgets_manager ? $widgets_manager : \Elementor\Plugin::instance()->widgets_manager;

		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-base.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-meta-info.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-instructors.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-pricing-table.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-progress.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-continue-button.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-syllabus.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-student-identity.php';

		$widgets_manager->register( new LLMS_Elementor_Widget_Course_Meta_Info() );
		$widgets_manager->register( new LLMS_Elementor_Widget_Course_Instructors() );
		$widgets_manager->register( new LLMS_Elementor_Widget_Pricing_Table() );
		$widgets_manager->register( new LLMS_Elementor_Widget_Course_Progress() );
		$widgets_manager->register( new LLMS_Elementor_Widget_Course_Continue_Button() );
		$widgets_manager->register( new LLMS_Elementor_Widget_Course_Syllabus() );
		$widgets_manager->register( new LLMS_Elementor_Widget_Student_Identity() );
	}

	public function add_widget_categories( $elements_manager ) {

		$elements_manager->add_category(
			'lifterlms',
			array(
				'title' => 'VibeLMS',
				'icon'  => 'dashicons-before dashicons-welcome-learn-more',
			)
		);
	}
}

return new LLMS_Elementor_Widgets();
