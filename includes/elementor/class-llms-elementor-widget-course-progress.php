<?php

class LLMS_Elementor_Widget_Course_Progress extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'llms_course_progress_widget';
	}

	public function get_title() {
		return __( 'Прогресс курса', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Прогресс курса', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_course_selector_control();

		$this->add_control(
			'description',
			array(
				'label'     => esc_html__( 'Показывает ученику прогресс текущего курса.', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_footer_promo_control();

		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$course_id = $this->get_context_course_id();
		if ( ! $course_id ) {
			echo $this->render_course_context_notice();
			return;
		}

		if ( ! is_user_logged_in() || ! llms_is_user_enrolled( get_current_user_id(), $course_id ) ) {
			return;
		}

		$course = new LLMS_Course( $course_id );
		echo lifterlms_course_progress_bar( $course->get_percent_complete(), false, false, false );
	}
}
