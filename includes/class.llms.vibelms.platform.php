<?php
/**
 * VibeLMS project-neutral assessment and reporting layer.
 *
 * @package VibeLMS/Classes
 * @since 0.0.14
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds configurable assessment identity data and a protected attempt journal.
 *
 * @since 0.0.14
 */
class LLMS_VibeLMS_Platform {

	const DB_VERSION = '1';

	const REQUIRED_QUESTIONS_OPTION = 'vibelms_required_quiz_questions';

	const PASSING_PERCENT_OPTION = 'vibelms_passing_score_percent';

	const REQUIRE_IDENTITY_OPTION = 'vibelms_require_identity';

	const CERTIFICATE_TEMPLATE_OPTION = 'vibelms_certificate_template_id';

	/**
	 * Identity user-meta keys.
	 *
	 * @var array<string,string>
	 */
	private $identity_fields = array(
		'company'       => 'vibelms_company',
		'employee_name' => 'vibelms_employee_name',
		'region'        => 'vibelms_region',
		'station'       => 'vibelms_station',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'lifterlms_general_settings', array( $this, 'add_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_install' ), 2 );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ), 100 );
		add_action( 'admin_post_vibelms_export_attempts', array( $this, 'export_attempts' ) );
		add_action( 'lifterlms_quiz_completed', array( $this, 'record_attempt' ), 10, 3 );
		add_action( 'lifterlms_quiz_passed', array( $this, 'award_certificate' ), 10, 3 );
		add_filter( 'llms_quiz_is_open', array( $this, 'maybe_require_identity' ), 10, 5 );
		add_action( 'lifterlms_single_quiz_before_summary', array( $this, 'render_quiz_identity_prompt' ), 20 );
		add_shortcode( 'vibelms_student_identity', array( $this, 'render_identity_form' ) );
	}

	/**
	 * Create or update the VibeLMS attempt table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table            = $wpdb->prefix . 'vibelms_attempts';
		$charset_collate  = $wpdb->get_charset_collate();
		$sql              = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			quiz_id bigint(20) unsigned NOT NULL,
			attempt_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL DEFAULT '',
			company varchar(255) NOT NULL DEFAULT '',
			employee_name varchar(255) NOT NULL DEFAULT '',
			region varchar(190) NOT NULL DEFAULT '',
			station varchar(190) NOT NULL DEFAULT '',
			language varchar(20) NOT NULL DEFAULT '',
			attempt_number int(11) unsigned NOT NULL DEFAULT 0,
			question_count int(11) unsigned NOT NULL DEFAULT 0,
			correct_count int(11) unsigned NOT NULL DEFAULT 0,
			grade decimal(6,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT '',
			certificate_id bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_id (attempt_id),
			KEY user_id (user_id),
			KEY quiz_id (quiz_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'vibelms_platform_db_version', self::DB_VERSION, false );
	}

	/**
	 * Install the table after a Push-to-Deploy update.
	 *
	 * @return void
	 */
	public function maybe_install() {
		if ( self::DB_VERSION !== get_option( 'vibelms_platform_db_version' ) ) {
			self::install();
		}
	}

	/**
	 * Add project-neutral assessment settings to the existing General tab.
	 *
	 * @param array $settings Existing settings.
	 * @return array
	 */
	public function add_settings( $settings ) {
		$settings[] = array(
			'id'   => 'vibelms_assessment_settings',
			'type' => 'sectionstart',
		);
		$settings[] = array(
			'id'    => 'vibelms_assessment_settings_title',
			'title' => __( 'VibeLMS: настройки аттестации', 'lifterlms' ),
			'type'  => 'title',
		);
		$settings[] = array(
			'desc'              => __( 'Целевое количество вопросов. Ограничения на добавление вопросов нет.', 'lifterlms' ),
			'default'           => 15,
			'id'                => self::REQUIRED_QUESTIONS_OPTION,
			'title'             => __( 'Вопросов для аттестации', 'lifterlms' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'min' => '1',
				'max' => '1000',
			),
		);
		$settings[] = array(
			'desc'              => __( 'Минимальный процент для статуса «Сдано» и выдачи сертификата.', 'lifterlms' ),
			'default'           => 100,
			'id'                => self::PASSING_PERCENT_OPTION,
			'title'             => __( 'Проходной балл, %', 'lifterlms' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'min' => '1',
				'max' => '100',
			),
		);
		$settings[] = array(
			'desc'    => __( 'Если включено, сотрудник должен заполнить идентификационную форму перед тестированием.', 'lifterlms' ),
			'default' => 'no',
			'id'      => self::REQUIRE_IDENTITY_OPTION,
			'title'   => __( 'Требовать идентификацию перед тестом', 'lifterlms' ),
			'type'    => 'checkbox',
		);
		$settings[] = array(
			'class'             => 'llms-select2-post',
			'custom_attributes' => array(
				'data-allow-clear' => true,
				'data-post-type'   => 'llms_certificate',
				'data-placeholder' => __( 'Выберите шаблон сертификата', 'lifterlms' ),
			),
			'desc'              => __( 'После успешной аттестации VibeLMS выдаст этот сертификат. Оставьте пустым, чтобы использовать стандартную механику сертификатов.', 'lifterlms' ),
			'id'                => self::CERTIFICATE_TEMPLATE_OPTION,
			'options'           => function_exists( 'llms_make_select2_post_array' ) ? llms_make_select2_post_array( get_option( self::CERTIFICATE_TEMPLATE_OPTION, '' ) ) : array(),
			'title'             => __( 'Шаблон сертификата VibeLMS', 'lifterlms' ),
			'type'              => 'select',
		);
		$settings[] = array(
			'id'   => 'vibelms_assessment_settings',
			'type' => 'sectionend',
		);

		return $settings;
	}

	/**
	 * Register the protected journal screen.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_submenu_page(
			'lifterlms',
			__( 'Журнал тестирования VibeLMS', 'lifterlms' ),
			__( 'Журнал тестирования', 'lifterlms' ),
			'vibelms_view_reports',
			'vibelms-attempts',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the attempt journal.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'vibelms_view_reports' ) ) {
			wp_die( esc_html__( 'У вас нет доступа к этому разделу.', 'lifterlms' ) );
		}

		global $wpdb;
		$table      = $this->get_table_name();
		$per_page   = 50;
		$page       = max( 1, absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
		$offset     = ( $page - 1 ) * $per_page;
		$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=vibelms_export_attempts' ), 'vibelms_export_attempts' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Журнал тестирования', 'lifterlms' ); ?></h1>
			<p><?php esc_html_e( 'Здесь хранятся результаты завершённых тестов. Доступ имеют администратор и наблюдатель.', 'lifterlms' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Экспортировать CSV', 'lifterlms' ); ?></a></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Дата', 'lifterlms' ); ?></th>
					<th><?php esc_html_e( 'Сотрудник', 'lifterlms' ); ?></th>
					<th><?php esc_html_e( 'Компания', 'lifterlms' ); ?></th>
					<th><?php esc_html_e( 'Регион', 'lifterlms' ); ?></th>
					<th><?php esc_html_e( 'АЗС / точка', 'lifterlms' ); ?></th>
					<th><?php esc_html_e( 'Результат', 'lifterlms' ); ?></th>
					<th><?php esc_html_e( 'Статус', 'lifterlms' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Попыток пока нет.', 'lifterlms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->completed_at ? $row->completed_at : $row->started_at ); ?></td>
							<td><?php echo esc_html( $row->employee_name ? $row->employee_name : $row->email ); ?></td>
							<td><?php echo esc_html( $row->company ); ?></td>
							<td><?php echo esc_html( $row->region ); ?></td>
							<td><?php echo esc_html( $row->station ); ?></td>
							<td><?php echo esc_html( sprintf( '%d/%d (%.2f%%)', $row->correct_count, $row->question_count, $row->grade ) ); ?></td>
							<td><?php echo esc_html( 'passed' === $row->status ? __( 'Сдано', 'lifterlms' ) : __( 'Не сдано', 'lifterlms' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<?php
			$pages = (int) ceil( $total / $per_page );
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $page, 'total' => $pages, 'format' => '', 'prev_text' => __( '« Назад', 'lifterlms' ), 'next_text' => __( 'Вперёд »', 'lifterlms' ) ) ) ) . '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Export the journal as a UTF-8 CSV file.
	 *
	 * @return void
	 */
	public function export_attempts() {
		if ( ! current_user_can( 'vibelms_export_reports' ) ) {
			wp_die( esc_html__( 'У вас нет права экспортировать отчёты.', 'lifterlms' ) );
		}

		check_admin_referer( 'vibelms_export_attempts' );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . $this->get_table_name() . ' ORDER BY id DESC', ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=vibelms-attempts-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo "\xEF\xBB\xBF";

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Дата', 'Email', 'ФИО', 'Компания', 'Регион', 'АЗС / точка', 'Язык', 'Попытка', 'Вопросов', 'Правильных', 'Балл, %', 'Статус', 'Сертификат' ), ';' );
		foreach ( $rows as $row ) {
			fputcsv( $output, array( $row['completed_at'] ? $row['completed_at'] : $row['started_at'], $row['email'], $row['employee_name'], $row['company'], $row['region'], $row['station'], $row['language'], $row['attempt_number'], $row['question_count'], $row['correct_count'], $row['grade'], 'passed' === $row['status'] ? __( 'Сдано', 'lifterlms' ) : __( 'Не сдано', 'lifterlms' ), $row['certificate_id'] ), ';' );
		}
		fclose( $output );
		exit;
	}

	/**
	 * Record a completed quiz attempt.
	 *
	 * @param int                $student_id Student ID.
	 * @param int                $quiz_id    Quiz ID.
	 * @param LLMS_Quiz_Attempt  $attempt    Quiz attempt.
	 * @return void
	 */
	public function record_attempt( $student_id, $quiz_id, $attempt ) {
		if ( ! $attempt instanceof LLMS_Quiz_Attempt ) {
			return;
		}

		$user = get_userdata( $student_id );
		if ( ! $user ) {
			return;
		}

		$question_count = $attempt->get_count( 'questions' );
		$correct_count  = $attempt->get_count( 'correct_answers' );
		$grade          = (float) $attempt->get( 'grade' );
		$status         = $this->is_vibelms_pass( $attempt ) ? 'passed' : 'failed';
		$identity       = $this->get_identity( $student_id );

		global $wpdb;
		$wpdb->replace(
			$this->get_table_name(),
			array( 'user_id' => absint( $student_id ), 'quiz_id' => absint( $quiz_id ), 'attempt_id' => absint( $attempt->get_id() ), 'email' => $user->user_email, 'company' => $identity['company'], 'employee_name' => $identity['employee_name'], 'region' => $identity['region'], 'station' => $identity['station'], 'language' => get_user_meta( $student_id, 'vibelms_language', true ) ?: get_locale(), 'attempt_number' => absint( $attempt->get( 'attempt' ) ), 'question_count' => $question_count, 'correct_count' => $correct_count, 'grade' => $grade, 'status' => $status, 'started_at' => $attempt->get( 'start_date' ), 'completed_at' => $attempt->get( 'end_date' ) ),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%s' )
		);

		if ( function_exists( 'llms_vibelms_diagnostics_log' ) ) {
			llms_vibelms_diagnostics_log( 'info', 'Quiz attempt recorded', array( 'attempt_id' => $attempt->get_id(), 'quiz_id' => $quiz_id, 'status' => $status ) );
		}
	}

	/**
	 * Award a configured certificate after a VibeLMS-passing attempt.
	 *
	 * @param int                $student_id Student ID.
	 * @param int                $quiz_id    Quiz ID.
	 * @param LLMS_Quiz_Attempt  $attempt    Quiz attempt.
	 * @return void
	 */
	public function award_certificate( $student_id, $quiz_id, $attempt ) {
		if ( ! $attempt instanceof LLMS_Quiz_Attempt || ! $this->is_vibelms_pass( $attempt ) ) {
			return;
		}

		$template_id = absint( get_option( self::CERTIFICATE_TEMPLATE_OPTION, 0 ) );
		if ( ! $template_id || ! class_exists( 'LLMS_Engagement_Handler' ) ) {
			return;
		}

		$result = LLMS_Engagement_Handler::handle_certificate( array( $student_id, $template_id, $quiz_id, 0 ) );
		if ( ! $result instanceof LLMS_User_Certificate ) {
			return;
		}

		global $wpdb;
		$wpdb->update( $this->get_table_name(), array( 'certificate_id' => absint( $result->get_id() ) ), array( 'attempt_id' => absint( $attempt->get_id() ) ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Require identity data when the project enables the option.
	 *
	 * @param bool          $is_open Whether the quiz is open.
	 * @param int           $user_id User ID.
	 * @param int           $quiz_id Quiz ID.
	 * @param LLMS_Quiz     $quiz Quiz model.
	 * @param LLMS_Student  $student Student model.
	 * @return bool
	 */
	public function maybe_require_identity( $is_open, $user_id, $quiz_id, $quiz, $student ) {
		if ( ! $is_open || 'yes' !== get_option( self::REQUIRE_IDENTITY_OPTION, 'no' ) || ! $user_id || $this->identity_is_complete( $user_id ) ) {
			return $is_open;
		}

		if ( function_exists( 'llms_add_notice' ) ) {
			llms_add_notice( __( 'Перед тестированием заполните идентификационную форму сотрудника.', 'lifterlms' ), 'error' );
		}
		return false;
	}

	/**
	 * Show the identity form on a quiz page when required and incomplete.
	 *
	 * @return void
	 */
	public function render_quiz_identity_prompt() {
		if ( 'yes' === get_option( self::REQUIRE_IDENTITY_OPTION, 'no' ) && is_user_logged_in() && ! $this->identity_is_complete( get_current_user_id() ) ) {
			echo do_shortcode( '[vibelms_student_identity]' );
		}
	}

	/**
	 * Render and save the reusable identity form.
	 *
	 * @return string
	 */
	public function render_identity_form() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Войдите в аккаунт, чтобы заполнить данные сотрудника.', 'lifterlms' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$message = '';
		if ( isset( $_POST['vibelms_identity_form'] ) ) {
			$nonce = isset( $_POST['vibelms_identity_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vibelms_identity_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'vibelms_save_identity' ) ) {
				$message = '<p class="llms-error">' . esc_html__( 'Срок действия формы истёк. Обновите страницу и попробуйте снова.', 'lifterlms' ) . '</p>';
			} else {
				$raw = isset( $_POST['vibelms_identity'] ) && is_array( $_POST['vibelms_identity'] ) ? wp_unslash( $_POST['vibelms_identity'] ) : array();
				$data = $this->sanitize_identity( $raw );
				foreach ( $this->identity_fields as $field => $meta_key ) {
					update_user_meta( $user_id, $meta_key, $data[ $field ] );
				}
				$message = '<p class="llms-success">' . esc_html__( 'Данные сохранены.', 'lifterlms' ) . '</p>';
			}
		}

		$identity = $this->get_identity( $user_id );
		$output   = $message . '<form class="vibelms-identity-form" method="post">';
		$output  .= '<h3>' . esc_html__( 'Идентификация сотрудника', 'lifterlms' ) . '</h3>';
		$output  .= '<p><label>' . esc_html__( 'Компания', 'lifterlms' ) . '<br><input type="text" name="vibelms_identity[company]" value="' . esc_attr( $identity['company'] ) . '" required></label></p>';
		$output  .= '<p><label>' . esc_html__( 'ФИО сотрудника', 'lifterlms' ) . '<br><input type="text" name="vibelms_identity[employee_name]" value="' . esc_attr( $identity['employee_name'] ) . '" required></label></p>';
		$output  .= '<p><label>' . esc_html__( 'Регион / город', 'lifterlms' ) . '<br><input type="text" name="vibelms_identity[region]" value="' . esc_attr( $identity['region'] ) . '" required></label></p>';
		$output  .= '<p><label>' . esc_html__( 'Номер АЗС / торговой точки', 'lifterlms' ) . '<br><input type="text" name="vibelms_identity[station]" value="' . esc_attr( $identity['station'] ) . '" required></label></p>';
		$output  .= wp_nonce_field( 'vibelms_save_identity', 'vibelms_identity_nonce', true, false );
		$output  .= '<input type="hidden" name="vibelms_identity_form" value="1">';
		$output  .= '<p><button type="submit" class="button">' . esc_html__( 'Сохранить данные', 'lifterlms' ) . '</button></p></form>';

		return $output;
	}

	/**
	 * Determine whether an attempt satisfies the configured assessment rule.
	 *
	 * @param LLMS_Quiz_Attempt $attempt Quiz attempt.
	 * @return bool
	 */
	private function is_vibelms_pass( $attempt ) {
		$required_questions = max( 1, absint( get_option( self::REQUIRED_QUESTIONS_OPTION, 15 ) ) );
		$passing_percent    = min( 100, max( 1, (float) get_option( self::PASSING_PERCENT_OPTION, 100 ) ) );
		return $attempt->get_count( 'questions' ) === $required_questions && $attempt->get_count( 'correct_answers' ) === $required_questions && (float) $attempt->get( 'grade' ) >= $passing_percent;
	}

	/**
	 * Get the attempt table name.
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'vibelms_attempts';
	}

	/**
	 * Read identity metadata.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,string>
	 */
	private function get_identity( $user_id ) {
		$data = array();
		foreach ( $this->identity_fields as $field => $meta_key ) {
			$data[ $field ] = (string) get_user_meta( $user_id, $meta_key, true );
		}
		return $data;
	}

	/**
	 * Check that all required identity fields are present.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function identity_is_complete( $user_id ) {
		return count( array_filter( $this->get_identity( $user_id ) ) ) === count( $this->identity_fields );
	}

	/**
	 * Sanitize submitted identity values.
	 *
	 * @param array $raw Submitted values.
	 * @return array<string,string>
	 */
	private function sanitize_identity( $raw ) {
		$data = array();
		foreach ( $this->identity_fields as $field => $meta_key ) {
			$value          = isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ? $raw[ $field ] : '';
			$data[ $field ] = sanitize_text_field( (string) $value );
		}
		return $data;
	}
}

/**
 * Install the VibeLMS platform table.
 *
 * @return void
 */
function llms_vibelms_platform_install() {
	LLMS_VibeLMS_Platform::install();
}

/**
 * Return the VibeLMS platform singleton.
 *
 * @return LLMS_VibeLMS_Platform
 */
function llms_vibelms_platform() {
	static $instance;
	if ( ! $instance ) {
		$instance = new LLMS_VibeLMS_Platform();
	}
	return $instance;
}
