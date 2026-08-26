<?php
/**
 * VibeLMS standalone quiz builder.
 *
 * @package VibeLMS/Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * A small standalone editor for reusable quizzes.
 *
 * The full course builder remains available for advanced question types. This
 * screen covers the common choice-question workflow without duplicating the
 * underlying LLMS quiz and question models.
 */
class LLMS_Admin_Quiz_Builder {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_vibelms_save_quiz', array( $this, 'save_quiz' ) );
		add_action( 'admin_post_vibelms_save_question', array( $this, 'save_question' ) );
		add_action( 'admin_post_vibelms_delete_quiz_question', array( $this, 'delete_question' ) );
	}

	/**
	 * Render the standalone builder page.
	 *
	 * @return void
	 */
	public function output() {
		if ( ! current_user_can( 'edit_courses' ) ) {
			wp_die( esc_html__( 'У вас нет доступа к конструктору тестов.', 'lifterlms' ) );
		}

		$quiz_id = isset( $_GET['quiz_id'] ) ? absint( $_GET['quiz_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$quiz    = $quiz_id ? llms_get_post( $quiz_id ) : false;

		if ( $quiz_id && ( ! $quiz || ! is_a( $quiz, 'LLMS_Quiz' ) || ! current_user_can( 'edit_post', $quiz_id ) ) ) {
			wp_die( esc_html__( 'Тест не найден или недоступен.', 'lifterlms' ) );
		}

		$this->print_styles();
		echo '<div class="wrap vibelms-quiz-builder">';
		echo '<h1>' . esc_html__( 'Конструктор тестов VibeLMS', 'lifterlms' ) . '</h1>';
		$this->print_notice();

		if ( $quiz ) {
			$this->render_editor( $quiz );
		} else {
			$this->render_library();
		}

		echo '</div>';
	}

	/**
	 * Handle quiz settings and create actions.
	 *
	 * @return void
	 */
	public function save_quiz() {
		$this->authorize();
		check_admin_referer( 'vibelms_save_quiz' );

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;
		$title   = isset( $_POST['quiz_title'] ) && is_scalar( $_POST['quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) ) : '';
		$title   = $title ? $title : __( 'Новый тест', 'lifterlms' );

		if ( $quiz_id ) {
			$quiz = llms_get_post( $quiz_id );
			if ( ! $quiz || ! is_a( $quiz, 'LLMS_Quiz' ) || ! current_user_can( 'edit_post', $quiz_id ) ) {
				$this->redirect_with_notice( 0, __( 'Не удалось открыть тест для редактирования.', 'lifterlms' ), 'error' );
			}
		} else {
			$quiz = new LLMS_Quiz(
				'new',
				array(
					'post_title'  => $title,
					'post_status' => 'draft',
				)
			);
			if ( ! $quiz->get( 'id' ) ) {
				$this->log_error( 'Quiz creation failed', array( 'title' => $title ) );
				$this->redirect_with_notice( 0, __( 'Не удалось создать тест. Подробности записаны в журнал VibeLMS.', 'lifterlms' ), 'error' );
			}
			$quiz_id = absint( $quiz->get( 'id' ) );
		}

		$update = wp_update_post(
			array(
				'ID'          => $quiz_id,
				'post_title'  => $title,
				'post_status' => $this->posted_status(),
			),
			true
		);
		if ( is_wp_error( $update ) ) {
			$this->log_error( 'Quiz post update failed', array( 'quiz_id' => $quiz_id, 'error' => $update->get_error_message() ) );
			$this->redirect_with_notice( $quiz_id, __( 'Не удалось сохранить тест. Подробности записаны в журнал VibeLMS.', 'lifterlms' ), 'error' );
		}

		$quiz->set(
			array(
				'passing_percent'     => $this->posted_percent( 'passing_percent', 80 ),
				'limit_attempts'      => $this->posted_yesno( 'limit_attempts' ),
				'allowed_attempts'    => $this->posted_positive_int( 'allowed_attempts' ),
				'limit_time'          => $this->posted_yesno( 'limit_time' ),
				'time_limit'          => $this->posted_positive_int( 'time_limit' ),
				'show_correct_answer' => $this->posted_yesno( 'show_correct_answer', 'yes' ),
				'can_be_resumed'      => $this->posted_yesno( 'can_be_resumed' ),
			)
		);
		$this->sync_lesson( $quiz, isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0 );

		$this->redirect_with_notice( $quiz_id, __( 'Тест сохранён.', 'lifterlms' ), 'success' );
	}

	/**
	 * Handle a choice question create/update action.
	 *
	 * @return void
	 */
	public function save_question() {
		$this->authorize();
		check_admin_referer( 'vibelms_save_question' );

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;
		$quiz    = $quiz_id ? llms_get_post( $quiz_id ) : false;
		if ( ! $quiz || ! is_a( $quiz, 'LLMS_Quiz' ) || ! current_user_can( 'edit_post', $quiz_id ) ) {
			$this->redirect_with_notice( 0, __( 'Тест недоступен.', 'lifterlms' ), 'error' );
		}

		$title = isset( $_POST['question_title'] ) && is_scalar( $_POST['question_title'] ) ? wp_kses_post( wp_unslash( $_POST['question_title'] ) ) : '';
		$raw   = isset( $_POST['choices'] ) && is_array( $_POST['choices'] ) ? wp_unslash( $_POST['choices'] ) : array();
		$choices = array();
		foreach ( array_slice( $raw, 0, 8 ) as $choice ) {
			if ( ! is_scalar( $choice ) ) {
				continue;
			}
			$choice = wp_kses_post( $choice );
			if ( '' !== trim( wp_strip_all_tags( $choice ) ) ) {
				$choices[] = $choice;
			}
		}

		$correct_raw     = isset( $_POST['correct_choice'] ) && is_scalar( $_POST['correct_choice'] ) ? absint( $_POST['correct_choice'] ) : -1;
		$correct         = -1;
		$normalized_index = 0;
		foreach ( array_slice( $raw, 0, 8, true ) as $raw_index => $choice ) {
			if ( is_scalar( $choice ) && '' !== trim( wp_strip_all_tags( (string) $choice ) ) ) {
				if ( (int) $raw_index === $correct_raw ) {
					$correct = $normalized_index;
				}
				$normalized_index++;
			}
		}
		if ( '' === trim( wp_strip_all_tags( $title ) ) || count( $choices ) < 2 || $correct < 0 || ! isset( $choices[ $correct ] ) ) {
			$this->redirect_with_notice( $quiz_id, __( 'Укажите вопрос, минимум два варианта и правильный ответ.', 'lifterlms' ), 'error' );
		}

		$manager    = $quiz->questions();
		$question_id = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;
		$question   = $question_id ? $manager->get_question( $question_id ) : false;
		if ( ! $question ) {
			$question_id = $manager->create_question(
				array(
					'post_status'   => 'publish',
					'post_author'   => get_current_user_id(),
					'question_type' => 'choice',
					'multi_choices' => 'no',
					'points'        => 1,
				)
			);
			$question = $question_id ? $manager->get_question( $question_id ) : false;
		}

		if ( ! $question ) {
			$this->log_error( 'Question creation failed', array( 'quiz_id' => $quiz_id ) );
			$this->redirect_with_notice( $quiz_id, __( 'Не удалось создать вопрос. Подробности записаны в журнал VibeLMS.', 'lifterlms' ), 'error' );
		}

		$question->set(
			array(
				'title'         => $title,
				'question_type' => 'choice',
				'multi_choices' => 'no',
				'points'        => 1,
			)
		);
		foreach ( $question->get_choices( 'choices' ) as $old_choice ) {
			$question->delete_choice( $old_choice->get( 'id' ) );
		}
		foreach ( $choices as $index => $choice ) {
			$question->create_choice(
				array(
					'choice' => $choice,
					'correct' => $index === $correct,
				)
			);
		}

		$this->redirect_with_notice( $quiz_id, __( 'Вопрос сохранён.', 'lifterlms' ), 'success' );
	}

	/**
	 * Delete a question from a quiz.
	 *
	 * @return void
	 */
	public function delete_question() {
		$this->authorize();
		$quiz_id     = isset( $_GET['quiz_id'] ) ? absint( $_GET['quiz_id'] ) : 0;
		$question_id = isset( $_GET['question_id'] ) ? absint( $_GET['question_id'] ) : 0;
		check_admin_referer( 'vibelms_delete_question_' . $question_id );

		$quiz = $quiz_id ? llms_get_post( $quiz_id ) : false;
		if ( ! $quiz || ! is_a( $quiz, 'LLMS_Quiz' ) || ! current_user_can( 'edit_post', $quiz_id ) || ! $quiz->questions()->get_question( $question_id ) ) {
			$this->redirect_with_notice( $quiz_id, __( 'Не удалось удалить вопрос.', 'lifterlms' ), 'error' );
		}

		$quiz->questions()->delete_question( $question_id );
		$this->redirect_with_notice( $quiz_id, __( 'Вопрос удалён.', 'lifterlms' ), 'success' );
	}

	/**
	 * Render the quiz library.
	 *
	 * @return void
	 */
	private function render_library() {
		echo '<div class="vibelms-quiz-builder__intro"><p>' . esc_html__( 'Создавайте отдельные тесты и вставляйте их на страницы через виджет Elementor «Тест».', 'lifterlms' ) . '</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="vibelms-quiz-builder__create">';
		echo '<input type="hidden" name="action" value="vibelms_save_quiz">';
		wp_nonce_field( 'vibelms_save_quiz' );
		echo '<label for="vibelms-new-quiz-title"><strong>' . esc_html__( 'Название нового теста', 'lifterlms' ) . '</strong></label> ';
		echo '<input id="vibelms-new-quiz-title" name="quiz_title" type="text" class="regular-text" required placeholder="' . esc_attr__( 'Например: Проверка знаний', 'lifterlms' ) . '"> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Создать тест', 'lifterlms' ) . '</button>';
		echo '</form>';

	$quizzes = get_posts(
		array(
			'post_type'      => 'llms_quiz',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	if ( empty( $quizzes ) ) {
		echo '<p>' . esc_html__( 'Тестов пока нет.', 'lifterlms' ) . '</p>';
		return;
	}

	echo '<h2>' . esc_html__( 'Мои тесты', 'lifterlms' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Название', 'lifterlms' ) . '</th><th>' . esc_html__( 'Вопросы', 'lifterlms' ) . '</th><th>' . esc_html__( 'Статус', 'lifterlms' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $quizzes as $quiz_post ) {
		$quiz = new LLMS_Quiz( $quiz_post );
		echo '<tr><td><a href="' . esc_url( $this->builder_url( $quiz->get( 'id' ) ) ) . '">' . esc_html( get_the_title( $quiz->get( 'id' ) ) ) . '</a></td><td>' . esc_html( $quiz->get_questions_count() ) . '</td><td>' . esc_html( 'publish' === $quiz_post->post_status ? __( 'Опубликован', 'lifterlms' ) : __( 'Черновик', 'lifterlms' ) ) . '</td><td><a class="button" href="' . esc_url( $this->builder_url( $quiz->get( 'id' ) ) ) . '">' . esc_html__( 'Открыть', 'lifterlms' ) . '</a></td></tr>';
	}
	echo '</tbody></table>';
	}

	/**
	 * Render one quiz editor.
	 *
	 * @param LLMS_Quiz $quiz Quiz model.
	 * @return void
	 */
	private function render_editor( $quiz ) {
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=llms-quiz-builder' ) ) . '">← ' . esc_html__( 'К списку тестов', 'lifterlms' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="vibelms-quiz-builder__settings">';
		echo '<input type="hidden" name="action" value="vibelms_save_quiz"><input type="hidden" name="quiz_id" value="' . esc_attr( $quiz->get( 'id' ) ) . '">';
		wp_nonce_field( 'vibelms_save_quiz' );
		echo '<h2>' . esc_html__( 'Настройки теста', 'lifterlms' ) . '</h2><table class="form-table"><tbody>';
		echo '<tr><th><label for="vibelms-quiz-title">' . esc_html__( 'Название', 'lifterlms' ) . '</label></th><td><input id="vibelms-quiz-title" class="regular-text" name="quiz_title" type="text" value="' . esc_attr( get_the_title( $quiz->get( 'id' ) ) ) . '" required></td></tr>';
		echo '<tr><th><label for="vibelms-quiz-status">' . esc_html__( 'Статус', 'lifterlms' ) . '</label></th><td><select id="vibelms-quiz-status" name="status"><option value="draft"' . selected( get_post_status( $quiz->get( 'id' ) ), 'draft', false ) . '>' . esc_html__( 'Черновик', 'lifterlms' ) . '</option><option value="publish"' . selected( get_post_status( $quiz->get( 'id' ) ), 'publish', false ) . '>' . esc_html__( 'Опубликован', 'lifterlms' ) . '</option></select></td></tr>';
		echo '<tr><th><label for="vibelms-passing-percent">' . esc_html__( 'Проходной балл, %', 'lifterlms' ) . '</label></th><td><input id="vibelms-passing-percent" name="passing_percent" type="number" min="0" max="100" step="0.01" value="' . esc_attr( $quiz->get( 'passing_percent' ) ?: 80 ) . '"></td></tr>';
		echo '<tr><th><label for="vibelms-lesson-id">' . esc_html__( 'Урок', 'lifterlms' ) . '</label></th><td><select id="vibelms-lesson-id" name="lesson_id"><option value="0">' . esc_html__( 'Не привязывать к уроку', 'lifterlms' ) . '</option>';
		foreach ( $this->get_editable_lessons() as $lesson ) {
			echo '<option value="' . esc_attr( $lesson->ID ) . '"' . selected( $quiz->get( 'lesson_id' ), $lesson->ID, false ) . '>' . esc_html( $lesson->post_title ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Для стандартного запуска теста привяжите его к уроку. В виджете Elementor тест можно выбрать отдельно.', 'lifterlms' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Ограничение попыток', 'lifterlms' ) . '</th><td><label><input type="checkbox" name="limit_attempts" value="yes"' . checked( $quiz->get( 'limit_attempts' ), 'yes', false ) . '> ' . esc_html__( 'Включить', 'lifterlms' ) . '</label> <input name="allowed_attempts" type="number" min="1" value="' . esc_attr( $quiz->get( 'allowed_attempts' ) ?: 1 ) . '" aria-label="' . esc_attr__( 'Количество попыток', 'lifterlms' ) . '"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Ограничение времени', 'lifterlms' ) . '</th><td><label><input type="checkbox" name="limit_time" value="yes"' . checked( $quiz->get( 'limit_time' ), 'yes', false ) . '> ' . esc_html__( 'Включить', 'lifterlms' ) . '</label> <input name="time_limit" type="number" min="1" value="' . esc_attr( $quiz->get( 'time_limit' ) ?: 30 ) . '" aria-label="' . esc_attr__( 'Минуты', 'lifterlms' ) . '"> ' . esc_html__( 'минут', 'lifterlms' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Результаты', 'lifterlms' ) . '</th><td><label><input type="checkbox" name="show_correct_answer" value="yes"' . checked( $quiz->get( 'show_correct_answer' ), 'yes', false ) . '> ' . esc_html__( 'Показывать правильные ответы', 'lifterlms' ) . '</label><br><label><input type="checkbox" name="can_be_resumed" value="yes"' . checked( $quiz->get( 'can_be_resumed' ), 'yes', false ) . '> ' . esc_html__( 'Разрешить продолжить незавершённую попытку', 'lifterlms' ) . '</label></td></tr>';
		echo '</tbody></table><p><button type="submit" class="button button-primary">' . esc_html__( 'Сохранить настройки теста', 'lifterlms' ) . '</button></p></form>';

		echo '<hr><h2>' . esc_html__( 'Вопросы', 'lifterlms' ) . '</h2>';
		$questions = $quiz->get_questions();
		if ( empty( $questions ) ) {
			echo '<p>' . esc_html__( 'Добавьте первый вопрос ниже.', 'lifterlms' ) . '</p>';
		}
		foreach ( $questions as $question ) {
			$this->render_question_form( $quiz, $question );
		}
		$this->render_question_form( $quiz );
	}

	/**
	 * Render a question form.
	 *
	 * @param LLMS_Quiz         $quiz     Quiz model.
	 * @param LLMS_Question|null $question Question model.
	 * @return void
	 */
	private function render_question_form( $quiz, $question = null ) {
		$is_new   = ! $question;
		if ( ! $is_new && 'choice' !== $question->get( 'question_type' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Этот вопрос использует расширенный тип. Откройте полный конструктор курса для его редактирования.', 'lifterlms' ) . '</p></div>';
			return;
		}
		$choices  = $question ? $question->get_choices( 'choices' ) : array();
		$values   = array();
		$correct  = 0;
		foreach ( $choices as $index => $choice ) {
			$value    = $choice->get_choice();
			$values[] = is_scalar( $value ) ? $value : '';
			if ( $choice->is_correct() ) {
				$correct = $index;
			}
		}
		if ( count( $values ) < 4 ) {
			$values = array_pad( $values, 4, '' );
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="vibelms-quiz-builder__question">';
		echo '<input type="hidden" name="action" value="vibelms_save_question"><input type="hidden" name="quiz_id" value="' . esc_attr( $quiz->get( 'id' ) ) . '">';
		if ( ! $is_new ) {
			echo '<input type="hidden" name="question_id" value="' . esc_attr( $question->get( 'id' ) ) . '">';
		}
		wp_nonce_field( 'vibelms_save_question' );
		echo '<h3>' . esc_html( $is_new ? __( 'Новый вопрос', 'lifterlms' ) : sprintf( __( 'Вопрос #%d', 'lifterlms' ), $question->get( 'id' ) ) ) . '</h3>';
		echo '<p><label><strong>' . esc_html__( 'Текст вопроса', 'lifterlms' ) . '</strong><br><textarea name="question_title" rows="2" class="large-text" required>' . esc_textarea( $question ? $question->get( 'title' ) : '' ) . '</textarea></label></p>';
		echo '<fieldset><legend><strong>' . esc_html__( 'Варианты ответа', 'lifterlms' ) . '</strong></legend>';
		foreach ( $values as $index => $value ) {
			echo '<p><label><input type="radio" name="correct_choice" value="' . esc_attr( $index ) . '"' . checked( $correct, $index, false ) . '> ' . esc_html__( 'Правильный', 'lifterlms' ) . '</label> <input name="choices[]" type="text" class="regular-text" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( sprintf( __( 'Вариант %d', 'lifterlms' ), $index + 1 ) ) . '"></p>';
		}
		$button_label = $is_new ? __( 'Добавить вопрос', 'lifterlms' ) : __( 'Сохранить вопрос', 'lifterlms' );
		echo '</fieldset><p><button type="submit" class="button button-primary">' . esc_html( $button_label ) . '</button>';
		if ( ! $is_new ) {
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=vibelms_delete_quiz_question&quiz_id=' . $quiz->get( 'id' ) . '&question_id=' . $question->get( 'id' ) ), 'vibelms_delete_question_' . $question->get( 'id' ) );
			echo ' <a class="button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Удалить этот вопрос?', 'lifterlms' ) ) . '\');">' . esc_html__( 'Удалить вопрос', 'lifterlms' ) . '</a>';
		}
		echo '</p></form>';
	}

	/**
	 * Sync optional lesson relation.
	 *
	 * @param LLMS_Quiz $quiz     Quiz model.
	 * @param int       $lesson_id Lesson ID.
	 * @return void
	 */
	private function sync_lesson( $quiz, $lesson_id ) {
		$quiz_id    = absint( $quiz->get( 'id' ) );
		$old_lesson = absint( $quiz->get( 'lesson_id' ) );
		$lesson     = $lesson_id ? llms_get_post( $lesson_id ) : false;
		if ( $lesson_id && ( ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) || ! current_user_can( 'edit_post', $lesson_id ) ) ) {
			return;
		}
		if ( $lesson && $lesson->get( 'quiz' ) && absint( $lesson->get( 'quiz' ) ) !== $quiz_id ) {
			$this->log_error( 'Quiz lesson assignment skipped because lesson already has a quiz', array( 'quiz_id' => $quiz_id, 'lesson_id' => $lesson_id ) );
			return;
		}
		if ( $old_lesson && $old_lesson !== $lesson_id ) {
			$old = llms_get_post( $old_lesson );
			if ( $old && is_a( $old, 'LLMS_Lesson' ) && absint( $old->get( 'quiz' ) ) === $quiz_id ) {
				$old->set( array( 'quiz' => 0, 'quiz_enabled' => 'no' ) );
			}
		}
		$quiz->set( 'lesson_id', $lesson_id );
		if ( $lesson ) {
			$lesson->set( array( 'quiz' => $quiz_id, 'quiz_enabled' => 'yes' ) );
		}
	}

	/**
	 * Return editable lessons for the lesson relation dropdown.
	 *
	 * @return WP_Post[]
	 */
	private function get_editable_lessons() {
		$lessons = get_posts(
			array(
				'post_type'      => 'lesson',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		return array_filter( $lessons, static function ( $lesson ) {
			return current_user_can( 'edit_post', $lesson->ID );
		} );
	}

	/**
	 * Render admin notices passed through the redirect.
	 *
	 * @return void
	 */
	private function print_notice() {
		if ( empty( $_GET['vibelms_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$type_value = isset( $_GET['vibelms_notice_type'] ) && is_scalar( $_GET['vibelms_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['vibelms_notice_type'] ) ) : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type       = 'error' === $type_value ? 'error' : 'success';
		$message    = is_scalar( $_GET['vibelms_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['vibelms_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $message ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Validate admin request capability.
	 *
	 * @return void
	 */
	private function authorize() {
		if ( ! current_user_can( 'edit_courses' ) ) {
			wp_die( esc_html__( 'У вас нет доступа к этому действию.', 'lifterlms' ) );
		}
	}

	/**
	 * Read and validate status.
	 *
	 * @return string
	 */
	private function posted_status() {
		$status = isset( $_POST['status'] ) && is_scalar( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
		return in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft';
	}

	/**
	 * Read a yes/no checkbox.
	 *
	 * @param string $key     Field name.
	 * @param string $default Default value.
	 * @return string
	 */
	private function posted_yesno( $key, $default = 'no' ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';
		return 'yes' === $value ? 'yes' : $default;
	}

	/**
	 * Read a bounded percentage.
	 *
	 * @param string $key     Field name.
	 * @param float  $default Default value.
	 * @return float
	 */
	private function posted_percent( $key, $default ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (float) wp_unslash( $_POST[ $key ] ) : $default;
		return min( 100, max( 0, $value ) );
	}

	/**
	 * Read a positive integer.
	 *
	 * @param string $key Field name.
	 * @return int
	 */
	private function posted_positive_int( $key ) {
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? $_POST[ $key ] : 0;
		return max( 0, absint( $value ) );
	}

	/**
	 * Redirect back to the builder.
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $message Message.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function redirect_with_notice( $quiz_id, $message, $type = 'success' ) {
		$url = $this->builder_url( $quiz_id );
		$url = add_query_arg( array( 'vibelms_notice' => sanitize_text_field( $message ), 'vibelms_notice_type' => 'error' === $type ? 'error' : 'success' ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Build the builder URL.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string
	 */
	private function builder_url( $quiz_id = 0 ) {
		$url = admin_url( 'admin.php?page=llms-quiz-builder' );
		return $quiz_id ? add_query_arg( 'quiz_id', absint( $quiz_id ), $url ) : $url;
	}

	/**
	 * Log an actionable failure.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context without credentials.
	 * @return void
	 */
	private function log_error( $message, $context = array() ) {
		if ( function_exists( 'llms_vibelms_diagnostics_log' ) ) {
			llms_vibelms_diagnostics_log( 'error', $message, $context );
		}
	}

	/**
	 * Add small layout helpers for the standalone editor.
	 *
	 * @return void
	 */
	private function print_styles() {
		if ( empty( $_GET['page'] ) || 'llms-quiz-builder' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<style>.vibelms-quiz-builder__create,.vibelms-quiz-builder__settings,.vibelms-quiz-builder__question{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px;margin:18px 0;max-width:980px}.vibelms-quiz-builder__question{max-width:760px}.vibelms-quiz-builder__question fieldset{border:1px solid #dcdcde;padding:12px}.vibelms-quiz-builder__question input[type=text]{min-width:420px}.vibelms-quiz-builder h2{margin-top:28px}</style>';
	}
}
