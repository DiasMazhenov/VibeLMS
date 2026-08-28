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
		$languages = function_exists( 'llms_vibelms_content' ) ? llms_vibelms_content()->get_supported_languages() : array( 'ru' => __( 'Русский', 'lifterlms' ), 'kz' => __( 'Казахский', 'lifterlms' ) );
		$overrides = new \Elementor\Repeater();
		$overrides->add_control(
			'language',
			array(
				'label'   => __( 'Язык версии', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $languages,
				'default' => (string) key( $languages ),
			)
		);
		$overrides->add_control( 'title', array( 'label' => __( 'Название курса', 'lifterlms' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true ) );
		$overrides->add_control( 'excerpt', array( 'label' => __( 'Описание курса', 'lifterlms' ), 'type' => \Elementor\Controls_Manager::TEXTAREA, 'label_block' => true ) );
		$overrides->add_control( 'button', array( 'label' => __( 'Текст кнопки', 'lifterlms' ), 'type' => \Elementor\Controls_Manager::TEXT, 'label_block' => true ) );
		$this->add_control(
			'language_overrides',
			array(
				'label'       => __( 'Версии карточки по языкам', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $overrides->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ language }}}',
				'description' => __( 'Добавьте отдельную подпись и описание для каждого языка.', 'lifterlms' ),
			)
		);
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

		$title   = get_the_title( $course );
		$excerpt = get_the_excerpt( $course );
		$button  = __( 'Открыть курс', 'lifterlms' );
		$current_language = function_exists( 'llms_vibelms_content' ) ? llms_vibelms_content()->get_current_language() : 'ru';
		foreach ( (array) ( $settings['language_overrides'] ?? array() ) as $override ) {
			if ( $current_language !== sanitize_key( $override['language'] ?? '' ) ) {
				continue;
			}
			$title   = trim( (string) ( $override['title'] ?? '' ) ) ?: $title;
			$excerpt = trim( (string) ( $override['excerpt'] ?? '' ) ) ?: $excerpt;
			$button  = trim( (string) ( $override['button'] ?? '' ) ) ?: $button;
			break;
		}
		if ( function_exists( 'llms_vibelms_localize_frontend_text' ) ) {
			$button = llms_vibelms_localize_frontend_text( $button );
		}

		echo '<article class="vibelms-course-card">';
		if ( has_post_thumbnail( $course ) ) {
			echo '<a class="vibelms-course-card__image" href="' . esc_url( get_permalink( $course ) ) . '">';
			echo get_the_post_thumbnail( $course, 'large', array( 'alt' => esc_attr( $title ) ) );
			echo '</a>';
		}
		echo '<div class="vibelms-course-card__content">';
		echo '<h3 class="vibelms-course-card__title"><a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( $title ) . '</a></h3>';
		if ( 'yes' === ( isset( $settings['show_excerpt'] ) ? $settings['show_excerpt'] : 'yes' ) ) {
			echo '<div class="vibelms-course-card__excerpt">' . wp_kses_post( wpautop( $excerpt ) ) . '</div>';
		}
		echo '<a class="llms-button-primary vibelms-course-card__link" href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( $button ) . '</a>';
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
			'display_mode',
			array(
				'label'   => __( 'Вид результатов', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'all'    => __( 'Мои результаты всех тестов', 'lifterlms' ),
					'single' => __( 'Страница результата выбранного теста', 'lifterlms' ),
				),
				'default' => 'all',
			)
		);
		$this->add_course_selector_control();
		$this->add_quiz_selector_control();
		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Количество результатов', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 10,
				'condition' => array( 'display_mode' => 'all' ),
			)
		);
		$this->add_control(
			'preview_note',
			array(
				'label'     => __( 'Для страницы результата выберите тест выше. В предпросмотре отобразится результат текущего пользователя.', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'condition' => array( 'display_mode' => 'single' ),
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( 'single' === ( $settings['display_mode'] ?? 'all' ) ) {
			echo '<div class="vibelms-quiz-result-page">' . $this->render_current_quiz_results() . '</div>';
			return;
		}

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

	/**
	 * Render the standard result page for the selected quiz in Elementor.
	 *
	 * @return string
	 */
	private function render_current_quiz_results() {
		$quiz_id = $this->get_context_quiz_id();
		if ( ! $quiz_id ) {
			return '<div class="vibelms-elementor-context-notice">' . esc_html__( 'Выберите тест в настройках виджета для предпросмотра результатов.', 'lifterlms' ) . '</div>';
		}

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Войдите, чтобы увидеть результат теста.', 'lifterlms' ) . '</p>';
		}

		$quiz_post = get_post( $quiz_id );
		if ( ! $quiz_post || 'llms_quiz' !== $quiz_post->post_type || ! function_exists( 'lifterlms_template_quiz_results' ) ) {
			return '';
		}

		global $post;
		$original_post = $post;
		$post          = $quiz_post;
		setup_postdata( $post );
		ob_start();
		lifterlms_template_quiz_results();
		$content = ob_get_clean();
		wp_reset_postdata();
		$post = $original_post;

		if ( ! $content && $this->is_elementor_preview() ) {
			$content = '<div class="vibelms-elementor-context-notice">' . esc_html__( 'Здесь будут показаны результаты выбранного теста после его прохождения.', 'lifterlms' ) . '</div>';
		}

		return $content;
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

class LLMS_Elementor_Widget_Materials extends LLMS_Elementor_Widget_Base {

	public function get_name() { return 'vibelms_materials'; }

	public function get_title() { return __( 'Учебные материалы', 'lifterlms' ); }

	protected function _register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Учебные материалы', 'lifterlms' ), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'material_type', array( 'label' => __( 'Показывать', 'lifterlms' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'all' => __( 'Все материалы', 'lifterlms' ), 'slide' => __( 'Слайды', 'lifterlms' ), 'video' => __( 'Видео', 'lifterlms' ), 'document' => __( 'Документы', 'lifterlms' ) ), 'default' => 'all' ) );
		$this->add_control( 'material_language', array( 'label' => __( 'Язык', 'lifterlms' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'current' => __( 'Текущий язык', 'lifterlms' ), 'ru' => __( 'Русский', 'lifterlms' ), 'kz' => __( 'Казахский', 'lifterlms' ) ), 'default' => 'current' ) );
		$this->add_control( 'limit', array( 'label' => __( 'Количество материалов', 'lifterlms' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 0, 'max' => 100, 'default' => 0 ) );
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo $this->render_shortcode( 'vibelms_materials', array( 'type' => sanitize_key( $settings['material_type'] ?? 'all' ), 'language' => sanitize_key( $settings['material_language'] ?? 'current' ), 'limit' => absint( $settings['limit'] ?? 0 ) ) );
	}
}

class LLMS_Elementor_Widget_Language_Content extends LLMS_Elementor_Widget_Base {

	public function get_name() { return 'vibelms_language_content'; }

	public function get_title() { return __( 'Контент по языку', 'lifterlms' ); }

	protected function _register_controls() {
		$languages = function_exists( 'llms_vibelms_content' )
			? llms_vibelms_content()->get_supported_languages()
			: array( 'ru' => __( 'Русский', 'lifterlms' ), 'kz' => __( 'Казахский', 'lifterlms' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'language',
			array(
				'label'   => __( 'Язык', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $languages,
				'default' => (string) key( $languages ),
			)
		);
		$repeater->add_control(
			'content',
			array(
				'label'   => __( 'Содержимое', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::WYSIWYG,
				'default' => '',
			)
		);

		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Контент по языку', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'language_content',
			array(
				'label'       => __( 'Языковые версии', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ language }}}',
				'description' => __( 'Добавьте отдельную версию блока для каждого языка. Текущий язык берётся из переключателя VibeLMS.', 'lifterlms' ),
			)
		);
		$this->add_control(
			'fallback_language',
			array(
				'label'   => __( 'Запасной язык', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array( '' => __( 'Не показывать', 'lifterlms' ) ) + $languages,
				'default' => '',
				'description' => __( 'Используется, если для выбранного языка содержимое не задано.', 'lifterlms' ),
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$current  = function_exists( 'llms_vibelms_content' ) ? llms_vibelms_content()->get_current_language() : 'ru';
		$versions = isset( $settings['language_content'] ) && is_array( $settings['language_content'] ) ? $settings['language_content'] : array();
		$content  = '';
		foreach ( $versions as $version ) {
			if ( isset( $version['language'], $version['content'] ) && $current === sanitize_key( $version['language'] ) ) {
				$content = (string) $version['content'];
				break;
			}
		}
		if ( ! trim( $content ) && ! empty( $settings['fallback_language'] ) ) {
			$fallback = sanitize_key( $settings['fallback_language'] );
			foreach ( $versions as $version ) {
				if ( isset( $version['language'], $version['content'] ) && $fallback === sanitize_key( $version['language'] ) ) {
					$content = (string) $version['content'];
					break;
				}
			}
		}
		if ( ! trim( $content ) ) {
			if ( $this->is_elementor_preview() ) {
				echo '<div class="vibelms-elementor-context-notice">' . esc_html__( 'Добавьте языковые версии содержимого в настройках виджета.', 'lifterlms' ) . '</div>';
			}
			return;
		}

		echo '<div class="vibelms-language-content" data-vibelms-language="' . esc_attr( $current ) . '">' . do_shortcode( $this->sanitize_content( $content ) ) . '</div>';
	}

	/**
	 * Keep normal post HTML and allow only YouTube embeds in localized blocks.
	 *
	 * @param string $content Editor content.
	 * @return string
	 */
	private function sanitize_content( $content ) {
		$content = preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			function ( $match ) {
				if ( ! preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/i', $match[0], $src_match ) ) {
					return '';
				}

				$src    = esc_url_raw( html_entity_decode( $src_match[2] ) );
				$scheme = strtolower( (string) wp_parse_url( $src, PHP_URL_SCHEME ) );
				$host   = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
				$hosts  = array( 'youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com' );

				return 'https' === $scheme && in_array( $host, $hosts, true ) ? $match[0] : '';
			},
			(string) $content
		);

		$allowed          = wp_kses_allowed_html( 'post' );
		$allowed['iframe'] = array(
			'src'             => true,
			'title'           => true,
			'width'           => true,
			'height'          => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'frameborder'     => true,
			'loading'         => true,
			'referrerpolicy'  => true,
		);

		return wp_kses( (string) $content, $allowed );
	}
}

class LLMS_Elementor_Widget_Site_Header extends LLMS_Elementor_Widget_Base {
	public function get_name() {
		return 'vibelms_site_header';
	}

	public function get_title() {
		return __( 'Шапка VibeLMS', 'lifterlms' );
	}

	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Шапка VibeLMS', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$account_id  = absint( get_option( 'lifterlms_myaccount_page_id', 0 ) );
		$account_url = $account_id ? get_permalink( $account_id ) : home_url( '/' );

		$this->add_control(
			'logo_mode',
			array(
				'label'   => __( 'Логотип', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'site'  => __( 'Логотип сайта', 'lifterlms' ),
					'image' => __( 'Изображение из медиатеки', 'lifterlms' ),
					'text'  => __( 'Текстовый бренд', 'lifterlms' ),
				),
				'default' => 'site',
			)
		);

		$this->add_control(
			'logo_image',
			array(
				'label'     => __( 'Изображение логотипа', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'logo_mode' => 'image' ),
			)
		);

		$this->add_control(
			'brand_text',
			array(
				'label'     => __( 'Название бренда', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'VibeLMS',
				'condition' => array( 'logo_mode' => 'text' ),
			)
		);

		$this->add_control(
			'logo_link',
			array(
				'label'       => __( 'Ссылка логотипа', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => home_url( '/' ),
				'default'     => array( 'url' => home_url( '/' ) ),
			)
		);

		$menu = new \Elementor\Repeater();
		$menu->add_control(
			'label',
			array(
				'label'       => __( 'Название ссылки', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Курсы', 'lifterlms' ),
				'label_block' => true,
			)
		);
		$menu->add_control(
			'url',
			array(
				'label'       => __( 'Ссылка', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://',
				'label_block' => true,
			)
		);
		$menu->add_control(
			'new_tab',
			array(
				'label'        => __( 'Открывать в новой вкладке', 'lifterlms' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Да', 'lifterlms' ),
				'label_off'    => __( 'Нет', 'lifterlms' ),
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'menu_items',
			array(
				'label'       => __( 'Пункты меню', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $menu->get_controls(),
				'default'     => array(
					array( 'label' => __( 'Курсы', 'lifterlms' ), 'url' => array( 'url' => get_post_type_archive_link( 'course' ) ?: home_url( '/' ) ) ),
					array( 'label' => __( 'Мой кабинет', 'lifterlms' ), 'url' => array( 'url' => $account_url ) ),
				),
				'title_field' => '{{{ label }}}',
			)
		);

		$this->add_control(
			'show_language',
			array(
				'label'        => __( 'Показывать переключатель языка', 'lifterlms' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Да', 'lifterlms' ),
				'label_off'    => __( 'Нет', 'lifterlms' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_profile',
			array(
				'label'        => __( 'Показывать профиль', 'lifterlms' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Да', 'lifterlms' ),
				'label_off'    => __( 'Нет', 'lifterlms' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$buttons = new \Elementor\Repeater();
		$buttons->add_control(
			'label',
			array(
				'label'       => __( 'Текст кнопки', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Начать обучение', 'lifterlms' ),
				'label_block' => true,
			)
		);
		$buttons->add_control(
			'url',
			array(
				'label'       => __( 'Ссылка кнопки', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://',
				'label_block' => true,
			)
		);
		$buttons->add_control(
			'variant',
			array(
				'label'   => __( 'Вид кнопки', 'lifterlms' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'primary' => __( 'Основная', 'lifterlms' ),
					'outline' => __( 'Контурная', 'lifterlms' ),
					'link'    => __( 'Ссылка', 'lifterlms' ),
				),
				'default' => 'primary',
			)
		);
		$buttons->add_control(
			'new_tab',
			array(
				'label'        => __( 'Открывать в новой вкладке', 'lifterlms' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'buttons',
			array(
				'label'       => __( 'Кнопки действий', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $buttons->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ label }}}',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'header_style_section',
			array(
				'label' => __( 'Стиль шапки', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'header_background',
				'label'    => __( 'Фон шапки', 'lifterlms' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .vibelms-site-header',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'header_border',
				'label'    => __( 'Рамка шапки', 'lifterlms' ),
				'selector' => '{{WRAPPER}} .vibelms-site-header',
			)
		);
		$this->add_responsive_control(
			'header_padding',
			array(
				'label'      => __( 'Отступы шапки', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .vibelms-site-header__inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'container_width',
			array(
				'label'      => __( 'Максимальная ширина', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 320, 'max' => 1800 ) ),
				'selectors'  => array( '{{WRAPPER}} .vibelms-site-header__inner' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'logo_width',
			array(
				'label'      => __( 'Ширина логотипа', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 320 ) ),
				'selectors'  => array( '{{WRAPPER}} .vibelms-site-header__brand img, {{WRAPPER}} .vibelms-site-header__brand .custom-logo' => 'max-width: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'nav_gap',
			array(
				'label'      => __( 'Расстояние между ссылками', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .vibelms-site-header__nav, {{WRAPPER}} .vibelms-site-header__actions' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'header_elements_style_section',
			array(
				'label' => __( 'Стиль элементов', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'brand_typography',
				'label'    => __( 'Типографика бренда', 'lifterlms' ),
				'selector' => '{{WRAPPER}} .vibelms-site-header__brand',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'nav_typography',
				'label'    => __( 'Типографика ссылок', 'lifterlms' ),
				'selector' => '{{WRAPPER}} .vibelms-site-header__nav a, {{WRAPPER}} .vibelms-site-header__actions a',
			)
		);
		$this->add_control(
			'nav_color',
			array(
				'label'     => __( 'Цвет ссылок', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vibelms-site-header a' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'nav_hover_color',
			array(
				'label'     => __( 'Цвет ссылок при наведении', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vibelms-site-header a:hover' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => __( 'Типографика кнопок', 'lifterlms' ),
				'selector' => '{{WRAPPER}} .vibelms-site-header__button',
			)
		);
		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Цвет текста кнопок', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vibelms-site-header__button' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_background',
			array(
				'label'     => __( 'Фон основных кнопок', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vibelms-site-header__button--primary' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_hover_background',
			array(
				'label'     => __( 'Фон кнопок при наведении', 'lifterlms' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .vibelms-site-header__button--primary:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'button_radius',
			array(
				'label'      => __( 'Скругление кнопок', 'lifterlms' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .vibelms-site-header__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$logo     = $this->render_logo( $settings );
		$items    = isset( $settings['menu_items'] ) && is_array( $settings['menu_items'] ) ? $settings['menu_items'] : array();
		$buttons  = isset( $settings['buttons'] ) && is_array( $settings['buttons'] ) ? $settings['buttons'] : array();

		echo '<header class="vibelms-site-header vibelms-site-header--custom"><div class="vibelms-site-header__inner">' . $logo;
		echo '<nav class="vibelms-site-header__nav" aria-label="' . esc_attr( llms_vibelms_localize_frontend_text( __( 'Основная навигация', 'lifterlms' ) ) ) . '">';
		foreach ( $items as $item ) {
			$url = isset( $item['url']['url'] ) ? $item['url']['url'] : '';
			if ( ! $url || empty( $item['label'] ) ) {
				continue;
			}
			$target = ! empty( $item['new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
			echo '<a href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( llms_vibelms_localize_frontend_text( $item['label'] ) ) . '</a>';
		}
		echo '</nav><div class="vibelms-site-header__actions">';
		if ( 'yes' === ( $settings['show_language'] ?? 'yes' ) ) {
			echo do_shortcode( '[vibelms_language_switcher]' );
		}
		foreach ( $buttons as $button ) {
			$url = isset( $button['url']['url'] ) ? $button['url']['url'] : '';
			if ( ! $url || empty( $button['label'] ) ) {
				continue;
			}
			$variant = isset( $button['variant'] ) && in_array( $button['variant'], array( 'primary', 'outline', 'link' ), true ) ? $button['variant'] : 'primary';
			$target  = ! empty( $button['new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
			echo '<a class="vibelms-site-header__button vibelms-site-header__button--' . esc_attr( $variant ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( llms_vibelms_localize_frontend_text( $button['label'] ) ) . '</a>';
		}
		if ( 'yes' === ( $settings['show_profile'] ?? 'yes' ) ) {
			$this->render_profile();
		}
		echo '</div></div></header>';
	}

	private function render_logo( $settings ) {
		$mode = isset( $settings['logo_mode'] ) ? $settings['logo_mode'] : 'site';
		$url  = isset( $settings['logo_link']['url'] ) && $settings['logo_link']['url'] ? $settings['logo_link']['url'] : home_url( '/' );
		if ( 'image' === $mode && ! empty( $settings['logo_image']['url'] ) ) {
			$alt = ! empty( $settings['logo_image']['alt'] ) ? $settings['logo_image']['alt'] : get_bloginfo( 'name' );
			$body = '<img src="' . esc_url( $settings['logo_image']['url'] ) . '" alt="' . esc_attr( $alt ) . '">';
		} elseif ( 'text' === $mode ) {
			$body = '<span class="vibelms-site-header__mark" aria-hidden="true">V</span><span>' . esc_html( $settings['brand_text'] ?? 'VibeLMS' ) . '</span>';
		} else {
			$logo_id = absint( get_theme_mod( 'custom_logo' ) );
			if ( $logo_id ) {
				$alt  = get_post_meta( $logo_id, '_wp_attachment_image_alt', true ) ?: get_bloginfo( 'name' );
				$body = wp_get_attachment_image( $logo_id, 'full', false, array( 'alt' => $alt ) );
			}
			if ( empty( $body ) ) {
				$body = '<span class="vibelms-site-header__mark" aria-hidden="true">V</span><span>VibeLMS</span>';
			}
		}
		return '<a class="vibelms-site-header__brand" href="' . esc_url( $url ) . '">' . $body . '</a>';
	}

	private function render_profile() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			echo '<span class="vibelms-site-header__email">' . esc_html( $user->user_email ) . '</span><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html( llms_vibelms_localize_frontend_text( __( 'Выйти', 'lifterlms' ) ) ) . '</a>';
			return;
		}
		echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html( llms_vibelms_localize_frontend_text( __( 'Войти', 'lifterlms' ) ) ) . '</a>';
	}
}

class LLMS_Elementor_Widget_Site_Footer extends LLMS_Elementor_Widget_Base {
	public function get_name() { return 'vibelms_site_footer'; }
	public function get_title() { return __( 'Подвал VibeLMS', 'lifterlms' ); }
	protected function _register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Подвал VibeLMS', 'lifterlms' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'footer_text',
			array(
				'label'       => __( 'Текст копирайта', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '© {year} ТОО «UNIQ TRADE». Все права защищены',
				'label_block' => true,
				'description' => __( 'Можно использовать {year} и {site}.', 'lifterlms' ),
			)
		);
		$this->add_control(
			'support_url',
			array(
				'label'       => __( 'Ссылка поддержки', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://',
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'support_label',
			array(
				'label'       => __( 'Название ссылки поддержки', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Поддержка', 'lifterlms' ),
				'label_block' => true,
			)
		);
		$this->add_control(
			'developer_text',
			array(
				'label'       => __( 'Текст ссылки разработчика', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Разработано в веб-студии Mazhenov Design', 'lifterlms' ),
				'label_block' => true,
			)
		);
		$this->add_control(
			'developer_url',
			array(
				'label'       => __( 'Ссылка разработчика', 'lifterlms' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://',
				'default'     => array( 'url' => 'https://mazhenov.kz' ),
			)
		);
		$this->end_controls_section();
		$this->add_common_style_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$text     = isset( $settings['footer_text'] ) ? (string) $settings['footer_text'] : '';
		$text     = str_replace( array( '{year}', '{site}' ), array( wp_date( 'Y' ), get_bloginfo( 'name' ) ), $text );
		$support  = isset( $settings['support_url']['url'] ) ? $settings['support_url']['url'] : '';
		$developer = isset( $settings['developer_url']['url'] ) ? $settings['developer_url']['url'] : 'https://mazhenov.kz';

		echo '<footer class="vibelms-site-footer vibelms-site-footer--custom"><div class="vibelms-site-footer__inner">';
		echo '<span>' . esc_html( llms_vibelms_localize_frontend_text( $text ) ) . '</span>';
		if ( $support && ! empty( $settings['support_label'] ) ) {
			echo '<a href="' . esc_url( $support ) . '">' . esc_html( llms_vibelms_localize_frontend_text( $settings['support_label'] ) ) . '</a>';
		}
		if ( $developer && ! empty( $settings['developer_text'] ) ) {
			echo '<a href="' . esc_url( $developer ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( llms_vibelms_localize_frontend_text( $settings['developer_text'] ) ) . '</a>';
		}
		echo '</div></footer>';
	}
}
