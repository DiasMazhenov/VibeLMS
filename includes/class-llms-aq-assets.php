<?php
/**
 * LLMS_AQ_Assets class file.
 *
 * @package LifterLMS_Advanced_Quizzes/Classes
 *
 * @since 1.0.0
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defines, registers, and enqueues static assets.
 *
 * @since 1.0.0
 * @since 2.0.0 Removed method `LLMS_Assets::init()`.
 */
class LLMS_AQ_Assets {

	/**
	 * Instance of `LLMS_Assets`
	 *
	 * @var LLMS_Assets
	 */
	public $assets = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 1.0.5 Unknown.
	 * @since 2.0.0 Use the `LLMS_Assets` class.
	 *
	 * @return void
	 */
	public function __construct() {

		// Load an instance of the LLMS_Assets class.
		$this->assets = new LLMS_Assets(
			'llms-aq',
			array(
				// Base defaults shared by all asset types.
				'base'   => array(
					'base_file' => LLMS_ADVANCED_QUIZZES_PLUGIN_FILE,
					'base_url'  => LLMS_ADVANCED_QUIZZES_PLUGIN_URL,
					'version'   => LLMS_ADVANCED_QUIZZES_VERSION,
					'suffix'    => '', // Only minified files are distributed.
				),
				// Script specific defaults.
				'script' => array(
					'translate'  => true,
					'asset_file' => true,
				),
			)
		);

		$this->define();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

	}

	/**
	 * Defines static assets.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function define() {
		$this->assets->define( 'scripts', $this->get_script_definitions() );
		$this->assets->define( 'styles', $this->get_style_definitions() );
	}

	/**
	 * Enqueues frontend scripts.
	 *
	 * @since 1.0.0
	 * @since 1.0.5 Unknown.
	 * @since 1.0.10 Enqueue jQuery Touch Punch to facilitate mobile quizzes.
	 * @since 2.0.0 Use the `LLMS_Assets` class.
	 *              Remove registration of `llms-aq-countable` dependency.
	 *
	 * @return void
	 */
	public function enqueue() {

		if ( 'llms_quiz' !== get_post_type() ) {
			return;
		}

		LLMS_Admin_Assets::register_quill();
		$this->assets->enqueue_script( 'llms-aq' );
		$this->assets->enqueue_style( 'llms-aq' );

	}

	/**
	 * Enqueues admin scripts.
	 *
	 * @since 1.0.0
	 * @since 1.0.5 Unknown.
	 * @since 2.0.0 Use the `LLMS_Assets` class.
	 *
	 * @return void
	 */
	public function enqueue_admin() {

		$screen = get_current_screen();

		if ( ! $screen || 'admin_page_llms-course-builder' !== $screen->id ) {
			return;
		}

		$this->assets->enqueue_script( 'llms-aq-builder' );

	}

	/**
	 * Retrieves the shared script/style config for the codemirror vendor assets.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_codemirror_definition() {
		return array(
			'file_name' => 'codemirror',
			'path'      => 'assets/vendor/codemirror',
			'version'   => '5.34.0',
		);
	}

	/**
	 * Retrieves the script definition array.
	 *
	 * @since 2.0.0
	 *
	 * @return array[]
	 */
	private function get_script_definitions() {
		return array(
			// Frontend.
			'llms-aq'         => array(
				'dependencies' => array(
					'jquery',
					'jquery-touch-punch',
					'jquery-ui-sortable',
					'llms',
					'llms-quiz',
					'llms-quill',
					'llms-codemirror',
				),
			),

			// Admin / Builder.
			'llms-aq-builder' => array(
				'dependencies' => array( 'llms-builder' ),
			),

			// Vendor.
			'llms-codemirror' => $this->get_codemirror_definition(),
		);

	}

	/**
	 * Retrieves the style definition array.
	 *
	 * @since 2.0.0
	 *
	 * @return array[]
	 */
	private function get_style_definitions() {
		return array(
			// Fontend.
			'llms-aq'               => array(
				'dependencies' => array( 'llms-codemirror', 'llms-quill-snow-theme' ),
			),

			// Vendor.
			'llms-codemirror'       => $this->get_codemirror_definition(),
			'llms-quill-snow-theme' => array(
				'file_name' => 'quill.snow',
				'path'      => 'assets/vendor/quill',
				'version'   => '1.3.5',
			),
		);
	}

}
return new LLMS_AQ_Assets();
