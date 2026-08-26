<?php

defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget for the reusable VibeLMS employee identity form.
 */
class LLMS_Elementor_Widget_Student_Identity extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_student_identity_widget';
	}

	public function get_title() {
		return __( 'Идентификация сотрудника', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Идентификация сотрудника', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'description',
			array(
				'label'     => __( 'Форма сохраняет компанию, ФИО, регион и номер торговой точки для журнала тестирования.', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		echo do_shortcode( '[vibelms_student_identity]' );
	}
}
