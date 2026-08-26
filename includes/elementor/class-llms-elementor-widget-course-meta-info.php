<?php

class LLMS_Elementor_Widget_Course_Meta_Info extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'llms_course_meta_information_widget';
	}

	public function get_title() {
		return __( 'Информация о курсе', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Информация о курсе', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_course_selector_control();

		$this->add_control(
			'description',
			array(
				'label'     => esc_html__( 'Показывает метаинформацию текущего курса.', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_footer_promo_control();

		$this->end_controls_section();
	}

	protected function render() {
		echo $this->render_course_shortcode( 'lifterlms_course_meta_info' );
	}
}
