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
	 * Minimum Elementor version supported by the modern widgets manager API.
	 *
	 * @var string
	 */
	const MINIMUM_ELEMENTOR_VERSION = '3.5.0';

	/**
	 * Widget names registered during the current request.
	 *
	 * @var string[]
	 */
	private static $registered_widgets = array();

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
		add_filter( 'option_elementor_cpt_support', array( $this, 'add_supported_post_types' ) );
		add_filter( 'default_option_elementor_cpt_support', array( $this, 'add_supported_post_types' ) );
		add_action( 'init', array( $this, 'enable_post_type_support' ), 20 );
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_widget_categories' ) );
		add_filter( 'post_row_actions', array( $this, 'add_elementor_row_action' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'maybe_display_compatibility_notice' ) );
		add_filter( 'llms_render_block', array( $this, 'maybe_stop_rendering_block' ), 10, 2 );
	}

	/**
	 * Return the VibeLMS post types which can be edited with Elementor.
	 *
	 * @return string[]
	 */
	public static function get_elementor_post_types() {
		return array( 'course', 'lesson', 'llms_quiz' );
	}

	/**
	 * Keep Elementor's configured post types and add VibeLMS content types.
	 *
	 * Elementor reads this option before LifterLMS registers its post types, so
	 * explicit support is also added on init below.
	 *
	 * @param mixed $post_types Configured Elementor post types.
	 * @return string[]
	 */
	public function add_supported_post_types( $post_types ) {
		$post_types = is_array( $post_types ) ? $post_types : array();
		return array_values( array_unique( array_merge( $post_types, self::get_elementor_post_types() ) ) );
	}

	/**
	 * Add Elementor support after LifterLMS registers its custom post types.
	 *
	 * @return void
	 */
	public function enable_post_type_support() {
		if ( ! class_exists( 'Elementor\\Plugin' ) ) {
			return;
		}

		foreach ( self::get_elementor_post_types() as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				add_post_type_support( $post_type, 'elementor' );
			}
		}
	}

	/**
	 * Build an Elementor editor URL for a supported VibeLMS post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_elementor_edit_url( $post_id ) {
		$post_id = absint( $post_id );
		$post    = $post_id ? get_post( $post_id ) : false;
		if ( ! $post || ! class_exists( 'Elementor\\Plugin' ) || ! in_array( $post->post_type, self::get_elementor_post_types(), true ) || ! post_type_supports( $post->post_type, 'elementor' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		return add_query_arg(
			array(
				'post'   => $post_id,
				'action' => 'elementor',
			),
			admin_url( 'post.php' )
		);
	}

	/**
	 * Add a direct Elementor action when Elementor has not added one yet.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_elementor_row_action( $actions, $post ) {
		if ( isset( $actions['edit_with_elementor'] ) || ! $post instanceof WP_Post ) {
			return $actions;
		}

		$url = self::get_elementor_edit_url( $post->ID );
		if ( ! $url ) {
			return $actions;
		}

		$actions['edit_with_elementor'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Редактировать в Elementor', 'lifterlms' ) . '</a>';
		return $actions;
	}

	/**
	 * Return the current Elementor integration status for diagnostics.
	 *
	 * @return array
	 */
	public static function get_system_report() {
		$available = defined( 'ELEMENTOR_VERSION' );
		$version   = $available ? ELEMENTOR_VERSION : __( 'не установлен', 'lifterlms' );

		return array(
			'elementor'          => $version,
			'elementor_compatible' => $available && version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ? __( 'Да', 'lifterlms' ) : __( 'Нет', 'lifterlms' ),
			'vibelms_category'   => __( 'VibeLMS (технический идентификатор lifterlms)', 'lifterlms' ),
			'registered_widgets' => self::$registered_widgets ? implode( ', ', self::$registered_widgets ) : __( 'ещё не зарегистрированы', 'lifterlms' ),
		);
	}

	/**
	 * Explain missing or outdated Elementor on relevant admin screens.
	 *
	 * @return void
	 */
	public function maybe_display_compatibility_notice() {
		if ( ! current_user_can( 'manage_lifterlms' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! $this->is_relevant_screen( $screen ) ) {
			return;
		}

		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			$message = __( 'Elementor не активирован. Виджеты VibeLMS будут доступны после установки и активации Elementor.', 'lifterlms' );
			llms_vibelms_diagnostics_log( 'warning', 'Elementor is not active', self::get_system_report() );
		} elseif ( version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '<' ) ) {
			$message = sprintf( __( 'Установлена устаревшая версия Elementor %s. Для полной совместимости VibeLMS требуется версия %s или новее.', 'lifterlms' ), ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION );
			llms_vibelms_diagnostics_log( 'warning', 'Elementor version is not supported', self::get_system_report() );
		} else {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Determine whether an admin screen is relevant to Elementor integration.
	 *
	 * @param WP_Screen $screen Current screen.
	 * @return bool
	 */
	private function is_relevant_screen( $screen ) {
		if ( 0 === strpos( $screen->id, 'lifterlms' ) || 'toplevel_page_lifterlms' === $screen->id ) {
			return true;
		}

		return in_array( $screen->base, array( 'post', 'post-new' ), true ) && in_array( $screen->post_type, array( 'course', 'lesson', 'llms_quiz' ), true );
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
		if ( ! $widgets_manager && ! class_exists( 'Elementor\\Plugin' ) ) {
			llms_vibelms_diagnostics_log( 'warning', 'Elementor widgets manager is unavailable', self::get_system_report() );
			return;
		}

		$widgets_manager = $widgets_manager ? $widgets_manager : \Elementor\Plugin::instance()->widgets_manager;
		if ( ! $widgets_manager || ! method_exists( $widgets_manager, 'register' ) ) {
			llms_vibelms_diagnostics_log( 'critical', 'Elementor widgets manager cannot register widgets', self::get_system_report() );
			return;
		}

		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-base.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-meta-info.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-instructors.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-pricing-table.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-progress.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-continue-button.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-course-syllabus.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-widget-student-identity.php';
		require_once LLMS_PLUGIN_DIR . 'includes/elementor/class-llms-elementor-universal-widgets.php';

		$widgets = array(
			new LLMS_Elementor_Widget_Course_Meta_Info(),
			new LLMS_Elementor_Widget_Course_Instructors(),
			new LLMS_Elementor_Widget_Pricing_Table(),
			new LLMS_Elementor_Widget_Course_Progress(),
			new LLMS_Elementor_Widget_Course_Continue_Button(),
			new LLMS_Elementor_Widget_Course_Syllabus(),
			new LLMS_Elementor_Widget_Student_Identity(),
			new LLMS_Elementor_Widget_Course_Card(),
			new LLMS_Elementor_Widget_Course_Catalog(),
			new LLMS_Elementor_Widget_Lesson_List(),
			new LLMS_Elementor_Widget_Quiz(),
			new LLMS_Elementor_Widget_Quiz_Results(),
			new LLMS_Elementor_Widget_Certificates(),
			new LLMS_Elementor_Widget_Student_Profile(),
			new LLMS_Elementor_Widget_Student_Dashboard(),
			new LLMS_Elementor_Widget_Access_Groups(),
			new LLMS_Elementor_Widget_Materials(),
			new LLMS_Elementor_Widget_Site_Header(),
			new LLMS_Elementor_Widget_Site_Footer(),
		);

		self::$registered_widgets = array();
		foreach ( $widgets as $widget ) {
			$widgets_manager->register( $widget );
			self::$registered_widgets[] = $widget->get_name();
		}

		llms_vibelms_diagnostics_log( 'info', 'Elementor widgets registered', self::get_system_report() );
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
