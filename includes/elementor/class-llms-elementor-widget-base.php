<?php

abstract class LLMS_Elementor_Widget_Base extends \Elementor\Widget_Base {

	private static $course_options;
	private static $quiz_options;

	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );
	}

	public function get_icon() {
		return 'dashicons-before dashicons-welcome-learn-more';
	}

	public function get_categories() {
		return array( 'lifterlms' );
	}

	/**
	 * Add a reusable course context selector to course widgets.
	 *
	 * An empty value inherits the course from the current course, lesson, or
	 * quiz page. A selected course makes the widget reusable in global layouts.
	 *
	 * @return void
	 */
	protected function add_course_selector_control() {
		if ( null === self::$course_options ) {
			$courses = array(
				'' => __( 'Текущий курс', 'lifterlms' ),
			);

			foreach ( get_posts(
				array(
					'post_type'      => 'course',
					'post_status'    => 'publish',
					'posts_per_page' => 500,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			) as $course ) {
				$courses[ $course->ID ] = $course->post_title;
			}
			self::$course_options = $courses;
		}

		$this->add_control(
			'course_id',
			array(
				'label'       => __( 'Курс', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => self::$course_options,
				'default'     => '',
				'description' => __( 'Оставьте «Текущий курс» для шаблона курса, урока или теста.', 'lifterlms' ),
			)
		);
	}

	/**
	 * Add a reusable quiz selector to quiz widgets.
	 *
	 * An empty value inherits the quiz from the current quiz or lesson page.
	 *
	 * @return void
	 */
	protected function add_quiz_selector_control() {
		if ( null === self::$quiz_options ) {
			$quizzes = array(
				'' => __( 'Текущий тест', 'lifterlms' ),
			);

			foreach ( get_posts(
				array(
					'post_type'      => 'llms_quiz',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => 500,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			) as $quiz ) {
				$quizzes[ $quiz->ID ] = $quiz->post_title;
			}
			self::$quiz_options = $quizzes;
		}

		$this->add_control(
			'quiz_id',
			array(
				'label'       => __( 'Тест', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => self::$quiz_options,
				'default'     => '',
				'description' => __( 'Оставьте «Текущий тест» для шаблона урока или теста.', 'lifterlms' ),
			)
		);
	}

	/**
	 * Get the course selected in the widget or inherited from the current page.
	 *
	 * @return int
	 */
	protected function get_context_course_id() {
		$settings   = $this->get_settings_for_display();
		$selected   = isset( $settings['course_id'] ) ? absint( $settings['course_id'] ) : 0;
		$current_id = absint( get_the_ID() );

		if ( $selected && 'course' === get_post_type( $selected ) ) {
			return $selected;
		}

		if ( $current_id && 'course' === get_post_type( $current_id ) ) {
			return $current_id;
		}

		if ( $current_id && function_exists( 'llms_get_post_parent_course' ) ) {
			$parent = llms_get_post_parent_course( $current_id );
			if ( $parent && method_exists( $parent, 'get' ) ) {
				return absint( $parent->get( 'id' ) );
			}
		}

		return 0;
	}

	/**
	 * Get the quiz selected in the widget or inherited from the current page.
	 *
	 * @return int
	 */
	protected function get_context_quiz_id() {
		$settings   = $this->get_settings_for_display();
		$selected   = isset( $settings['quiz_id'] ) ? absint( $settings['quiz_id'] ) : 0;
		$current_id = absint( get_the_ID() );

		if ( $selected && 'llms_quiz' === get_post_type( $selected ) ) {
			return $selected;
		}

		if ( $current_id && 'llms_quiz' === get_post_type( $current_id ) ) {
			return $current_id;
		}

		if ( $current_id && 'lesson' === get_post_type( $current_id ) ) {
			$lesson = llms_get_post( $current_id );
			if ( $lesson && method_exists( $lesson, 'get_quiz' ) ) {
				$quiz = $lesson->get_quiz();
				if ( $quiz && method_exists( $quiz, 'get' ) ) {
					return absint( $quiz->get( 'id' ) );
				}
			}
		}

		$course_id = $this->get_context_course_id();
		if ( $course_id && class_exists( 'LLMS_Course' ) ) {
			$quizzes = ( new LLMS_Course( $course_id ) )->get_quizzes();
			if ( ! empty( $quizzes ) ) {
				return absint( reset( $quizzes ) );
			}
		}

		return 0;
	}

	/**
	 * Render an existing LMS shortcode with a selected course context.
	 *
	 * @param string $tag       Shortcode tag.
	 * @param array  $attributes Shortcode attributes.
	 * @param string $course_key Attribute name used by the shortcode.
	 * @return string
	 */
	protected function render_course_shortcode( $tag, $attributes = array(), $course_key = 'course_id' ) {
		$course_id = $this->get_context_course_id();

		if ( ! $course_id ) {
			return $this->render_course_context_notice();
		}

		$attributes[ $course_key ] = $course_id;
		return $this->render_shortcode( $tag, $attributes );
	}

	/**
	 * Render an arbitrary shortcode using sanitized scalar attributes.
	 *
	 * @param string $tag        Shortcode tag.
	 * @param array  $attributes Shortcode attributes.
	 * @return string
	 */
	protected function render_shortcode( $tag, $attributes = array() ) {
		$shortcode = '[' . sanitize_key( $tag );
		foreach ( $attributes as $key => $value ) {
			if ( '' === $value || null === $value || ! is_scalar( $value ) ) {
				continue;
			}
			$shortcode .= ' ' . sanitize_key( $key ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return do_shortcode( $shortcode . ']' );
	}

	/**
	 * Render a quiz through the existing LifterLMS content pipeline.
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @return string
	 */
	protected function render_quiz( $quiz_id ) {
		$quiz_post = get_post( absint( $quiz_id ) );
		if ( ! $quiz_post || 'llms_quiz' !== $quiz_post->post_type || ! function_exists( 'llms_get_post_content' ) ) {
			return $this->is_elementor_preview() ? '<div class="vibelms-elementor-context-notice">' . esc_html__( 'Выберите тест в настройках виджета.', 'lifterlms' ) . '</div>' : '';
		}

		global $post;
		$original_post = $post;
		$post          = $quiz_post;
		setup_postdata( $post );
		$content = has_filter( 'the_content', 'llms_get_post_content' )
			? apply_filters( 'the_content', $quiz_post->post_content )
			: llms_get_post_content( $quiz_post->post_content );
		wp_reset_postdata();
		$post = $original_post;

		return $content;
	}

	/**
	 * Add common Elementor style controls to every VibeLMS widget.
	 *
	 * @return void
	 */
	protected function add_common_style_controls() {
		$selector         = '{{WRAPPER}}';
		$heading_selector = $selector . ' :is(h1, h2, h3, h4, h5, h6)';
		$heading_link_selector = $heading_selector . ', ' . $heading_selector . ' a';

		$this->start_controls_section(
			'vibelms_style_section',
			array(
				'label' => __( 'Стили VibeLMS', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'vibelms_typography',
				'label'    => __( 'Типографика', 'lifterlms' ),
				'selector' => $selector . ', ' . $heading_selector,
			)
		);

		$this->add_control(
			'vibelms_text_color',
			array(
				'label'     => __( 'Цвет текста', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $selector => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'vibelms_heading_color',
			array(
				'label'     => __( 'Цвет заголовков', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $heading_link_selector => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'vibelms_background',
				'label'    => __( 'Фон', 'lifterlms' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'vibelms_padding',
			array(
				'label'      => __( 'Внутренние отступы', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'vibelms_margin',
			array(
				'label'      => __( 'Внешние отступы', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( $selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'vibelms_border',
				'label'    => __( 'Рамка', 'lifterlms' ),
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'vibelms_border_radius',
			array(
				'label'      => __( 'Скругление', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'vibelms_box_shadow',
				'label'    => __( 'Тень', 'lifterlms' ),
				'selector' => $selector,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render a useful editor state when a global layout has no course context.
	 *
	 * @return string
	 */
	protected function render_course_context_notice() {
		if ( ! $this->is_elementor_preview() ) {
			return '';
		}

		return '<div class="vibelms-elementor-context-notice">' . esc_html__( 'Выберите курс в настройках виджета для предпросмотра.', 'lifterlms' ) . '</div>';
	}

	/**
	 * Determine whether the widget is being rendered inside Elementor editor.
	 *
	 * @return bool
	 */
	protected function is_elementor_preview() {
		if ( ! class_exists( 'Elementor\\Plugin' ) ) {
			return false;
		}

		$elementor = \Elementor\Plugin::instance();
		return $elementor && isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode();
	}

	protected function add_footer_promo_control() {

		$this->add_control(
			'llms_footer_promo',
			array(
				'label'           => '',
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<hr><p style="margin-top: 20px;">' .
					esc_html__( 'Виджет VibeLMS работает с текущим курсом и доступен в категории VibeLMS.', 'lifterlms' ) .
					'</p>',
				'content_classes' => 'lifterlms-notice',
			)
		);
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		echo do_shortcode( '[lifterlms_course_continue_button]' );
	}

	protected function _content_template() {
		// Define your template variables here
	}
}
