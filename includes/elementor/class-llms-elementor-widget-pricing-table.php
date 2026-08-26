<?php

class LLMS_Elementor_Widget_Pricing_Table extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'llms_pricing_table_widget';
	}

	public function get_title() {
		return __( 'Тарифы курса', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Тарифы курса', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_course_selector_control();

		$this->add_control(
			'description',
			array(
				'label'     => esc_html__( 'Показывает доступные тарифы текущего курса.', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_footer_promo_control();

		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		echo $this->render_course_shortcode( 'lifterlms_pricing_table', array(), 'product' );
	}
}
