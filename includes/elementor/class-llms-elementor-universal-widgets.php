<?php

defined( 'ABSPATH' ) || exit;

/**
 * Reusable Elementor widgets which do not depend on project-specific content.
 */

class LLMS_Elementor_Widget_Course_Card extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_course_card';
	}

	public function get_title() {
		return __( 'Карточка курса', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Карточка курса', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_course_selector_control();
		$this->add_control(
			'show_excerpt',
			array(
				'label'        => __( 'Показывать описание', 'lifterlms' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Да', 'lifterlms' ),
				'label_off'    => __( 'Нет', 'lifterlms' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$course_id = $this->get_context_course_id();
		if ( ! $course_id ) {
			echo $this->render_course_context_notice();
			return;
		}

		$course  = get_post( $course_id );
		$settings = $this->get_settings_for_display();
		if ( ! $course || 'course' !== $course->post_type ) {
			return;
		}

		echo '<article class="vibelms-course-card">';
		if ( has_post_thumbnail( $course ) ) {
			echo '<a class="vibelms-course-card__image" href="' . esc_url( get_permalink( $course ) ) . '">';
			echo get_the_post_thumbnail( $course, 'large', array( 'alt' => esc_attr( get_the_title( $course ) ) ) );
			echo '</a>';
		}
		echo '<div class="vibelms-course-card__content">';
		echo '<h3 class="vibelms-course-card__title"><a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( get_the_title( $course ) ) . '</a></h3>';
		if ( 'yes' === ( isset( $settings['show_excerpt'] ) ? $settings['show_excerpt'] : 'yes' ) ) {
			echo '<div class="vibelms-course-card__excerpt">' . wp_kses_post( wpautop( get_the_excerpt( $course ) ) ) . '</div>';
		}
		echo '<a class="llms-button-primary vibelms-course-card__link" href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html__( 'Открыть курс', 'lifterlms' ) . '</a>';
		echo '</div></article>';
	}
}

class LLMS_Elementor_Widget_Course_Catalog extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_course_catalog';
	}

	public function get_title() {
		return __( 'Каталог курсов', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Каталог курсов', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => __( 'Категория курса', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Укажите slug категории или оставьте поле пустым.', 'lifterlms' ),
			)
		);
		$this->add_control(
			'mine',
			array(
				'label'   => __( 'Показывать', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'no'       => __( 'Все опубликованные курсы', 'lifterlms' ),
					'enrolled' => __( 'Мои активные курсы', 'lifterlms' ),
					'any'      => __( 'Все мои курсы', 'lifterlms' ),
					'expired'  => __( 'Мои завершившиеся курсы', 'lifterlms' ),
					'cancelled' => __( 'Отменённые курсы', 'lifterlms' ),
				),
				'default' => 'no',
			)
		);
		$this->add_control(
			'posts_per_page',
			array(
				'label'   => __( 'Курсов на странице', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 12,
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo $this->render_shortcode(
			'lifterlms_courses',
			array(
				'category'       => isset( $settings['category'] ) ? sanitize_title( $settings['category'] ) : '',
				'mine'           => isset( $settings['mine'] ) ? sanitize_key( $settings['mine'] ) : 'no',
				'posts_per_page' => isset( $settings['posts_per_page'] ) ? min( 100, max( 1, absint( $settings['posts_per_page'] ) ) ) : 12,
			)
		);
	}
}

class LLMS_Elementor_Widget_Lesson_List extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_lesson_list';
	}

	public function get_title() {
		return __( 'Список уроков', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Список уроков', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_course_selector_control();
		$this->add_control(
			'limit',
			array(
				'label'       => __( 'Количество уроков', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 500,
				'default'     => 0,
				'description' => __( '0 — показать все уроки.', 'lifterlms' ),
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$course_id = $this->get_context_course_id();
		if ( ! $course_id ) {
			echo $this->render_course_context_notice();
			return;
		}

		$course   = new LLMS_Course( $course_id );
		$settings = $this->get_settings_for_display();
		$lessons  = $course->get_lessons( 'posts' );
		$limit    = isset( $settings['limit'] ) ? absint( $settings['limit'] ) : 0;
		if ( $limit ) {
			$lessons = array_slice( $lessons, 0, $limit );
		}

		echo '<div class="vibelms-lesson-list">';
		if ( empty( $lessons ) ) {
			echo '<p>' . esc_html__( 'В этом курсе пока нет уроков.', 'lifterlms' ) . '</p>';
		} else {
			echo '<ol class="vibelms-lesson-list__items">';
			foreach ( $lessons as $lesson ) {
				echo '<li><a href="' . esc_url( get_permalink( $lesson ) ) . '">' . esc_html( get_the_title( $lesson ) ) . '</a></li>';
			}
			echo '</ol>';
		}
		echo '</div>';
	}
}

class LLMS_Elementor_Widget_Quiz extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_quiz';
	}

	public function get_title() {
		return __( 'Тест', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Тест', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_course_selector_control();
		$this->add_quiz_selector_control();
		$this->add_control(
			'description',
			array(
				'label' => __( 'Выберите тест или оставьте «Текущий тест» для шаблона урока.', 'lifterlms' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		echo '<div class="vibelms-quiz-widget">' . $this->render_quiz( $this->get_context_quiz_id() ) . '</div>';
	}
}

class LLMS_Elementor_Widget_Quiz_Results extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_quiz_results';
	}

	public function get_title() {
		return __( 'Результаты тестов', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Результаты тестов', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Количество результатов', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 10,
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Войдите, чтобы увидеть результаты тестов.', 'lifterlms' ) . '</p>';
			return;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'vibelms_attempts';
		$limit   = absint( $this->get_settings_for_display()['limit'] ?? 10 );
		$limit   = min( 100, max( 1, $limit ) );
		$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$results = $exists ? $wpdb->get_results( $wpdb->prepare( "SELECT quiz_id, grade, status, completed_at FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", get_current_user_id(), $limit ) ) : array();

		echo '<div class="vibelms-quiz-results">';
		if ( empty( $results ) ) {
			echo '<p>' . esc_html__( 'Результатов тестов пока нет.', 'lifterlms' ) . '</p>';
		} else {
			echo '<table class="vibelms-quiz-results__table"><thead><tr><th>' . esc_html__( 'Тест', 'lifterlms' ) . '</th><th>' . esc_html__( 'Результат', 'lifterlms' ) . '</th><th>' . esc_html__( 'Статус', 'lifterlms' ) . '</th></tr></thead><tbody>';
			foreach ( $results as $result ) {
				$status = 'passed' === $result->status ? __( 'Сдано', 'lifterlms' ) : __( 'Не сдано', 'lifterlms' );
				echo '<tr><td>' . esc_html( get_the_title( $result->quiz_id ) ) . '</td><td>' . esc_html( number_format_i18n( (float) $result->grade, 2 ) . '%' ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}

class LLMS_Elementor_Widget_Certificates extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_certificates';
	}

	public function get_title() {
		return __( 'Сертификаты пользователя', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Сертификаты пользователя', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Количество сертификатов', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 6,
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Войдите, чтобы увидеть сертификаты.', 'lifterlms' ) . '</p>';
			return;
		}

		$student = llms_get_student();
		if ( ! $student || ! function_exists( 'lifterlms_template_certificates_loop' ) ) {
			return;
		}

		$limit = absint( $this->get_settings_for_display()['limit'] ?? 6 );
		echo '<div class="vibelms-certificates">';
		lifterlms_template_certificates_loop( $student, min( 100, max( 1, $limit ) ) );
		echo '</div>';
	}
}

class LLMS_Elementor_Widget_Student_Profile extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_student_profile';
	}

	public function get_title() {
		return __( 'Профиль сотрудника', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Профиль сотрудника', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'description',
			array(
				'label'     => __( 'Показывает сохранённые данные текущего пользователя.', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Войдите, чтобы увидеть профиль.', 'lifterlms' ) . '</p>';
			return;
		}

		$user   = wp_get_current_user();
		$fields = array(
			'company'       => __( 'Компания', 'lifterlms' ),
			'employee_name' => __( 'ФИО', 'lifterlms' ),
			'region'        => __( 'Регион', 'lifterlms' ),
			'station'       => __( 'Торговая точка', 'lifterlms' ),
		);

		echo '<dl class="vibelms-student-profile">';
		foreach ( $fields as $key => $label ) {
			echo '<div class="vibelms-student-profile__row"><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( get_user_meta( $user->ID, 'vibelms_' . $key, true ) ) . '</dd></div>';
		}
		echo '</dl>';
	}
}

class LLMS_Elementor_Widget_Student_Dashboard extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_student_dashboard';
	}

	public function get_title() {
		return __( 'Кабинет ученика', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Кабинет ученика', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'description',
			array(
				'label' => __( 'Полный кабинет с курсами, заказами и сертификатами.', 'lifterlms' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		echo $this->render_shortcode( 'lifterlms_my_account' );
	}
}

class LLMS_Elementor_Widget_Access_Groups extends LLMS_Elementor_Widget_Base {

	public function get_name() {
		return 'vibelms_access_groups';
	}

	public function get_title() {
		return __( 'Группы доступа', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Группы доступа', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => __( 'Категория группы', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Укажите slug категории или оставьте поле пустым.', 'lifterlms' ),
			)
		);
		$this->add_control(
			'posts_per_page',
			array(
				'label'   => __( 'Групп на странице', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 12,
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo $this->render_shortcode(
			'lifterlms_memberships',
			array(
				'category'       => isset( $settings['category'] ) ? sanitize_title( $settings['category'] ) : '',
				'posts_per_page' => isset( $settings['posts_per_page'] ) ? min( 100, max( 1, absint( $settings['posts_per_page'] ) ) ) : 12,
			)
		);
	}
}
