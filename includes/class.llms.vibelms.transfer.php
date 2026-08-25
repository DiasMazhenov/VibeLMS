<?php
/**
 * Portable VibeLMS export and import.
 *
 * @package VibeLMS/Classes
 * @since 0.0.15
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_VibeLMS_Transfer.
 *
 * Creates a self-contained ZIP archive with VibeLMS settings, LMS content,
 * safe user profiles, enrollments, quiz attempts, reports and media.
 */
class LLMS_VibeLMS_Transfer {

	const FORMAT = 'vibelms-transfer';
	const FORMAT_VERSION = 1;
	const CAPABILITY = 'manage_options';
	const TRANSIENT_PREFIX = 'vibelms_transfer_result_';
	const REPORT_PREFIX = 'vibelms_transfer_report_';
	const STAGED_PREFIX = 'vibelms_transfer_staged_';
	const JOB_PREFIX = 'vibelms_transfer_job_';
	const ACTIVE_JOB_PREFIX = 'vibelms_transfer_active_';
	const JOB_TTL = 2 * HOUR_IN_SECONDS;
	const BATCH_SIZE = 20;
	const SOURCE_SITE_META = '_vibelms_transfer_source_site';
	const SOURCE_ID_META = '_vibelms_transfer_source_id';

	/**
	 * VibeLMS options which may safely move between sites.
	 *
	 * Runtime versions are intentionally excluded because the destination
	 * plugin must run its own migrations.
	 *
	 * @var string[]
	 */
	private $option_names = array(
		'vibelms_required_quiz_questions',
		'vibelms_passing_score_percent',
		'vibelms_require_identity',
		'vibelms_certificate_template_id',
	);

	/**
	 * Source post ID => imported featured attachment ID.
	 *
	 * @var int[]
	 */
	private $featured_media = array();

	/**
	 * Current import source site.
	 *
	 * @var string
	 */
	private $source_site = '';

	/**
	 * Current duplicate handling mode.
	 *
	 * @var string
	 */
	private $duplicate_mode = 'create';

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_page' ), 110 );
			add_action( 'admin_post_vibelms_export_bundle', array( $this, 'export_bundle' ) );
			add_action( 'admin_post_vibelms_prepare_import', array( $this, 'prepare_import_bundle' ) );
			add_action( 'admin_post_vibelms_start_import', array( $this, 'start_import_bundle' ) );
			add_action( 'admin_post_vibelms_cancel_import', array( $this, 'cancel_import_bundle' ) );
			add_action( 'admin_post_vibelms_download_report', array( $this, 'download_import_report' ) );
			add_action( 'wp_ajax_vibelms_transfer_progress', array( $this, 'ajax_transfer_progress' ) );
		}
	}

	/**
	 * Register the transfer screen below the VibeLMS menu.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_submenu_page(
			'lifterlms',
			__( 'Перенос данных VibeLMS', 'lifterlms' ),
			__( 'Перенос данных', 'lifterlms' ),
			self::CAPABILITY,
			'vibelms-transfer',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render export/import controls.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'У вас нет доступа к переносу данных.', 'lifterlms' ) );
		}

		$user_id = get_current_user_id();
		$result  = get_transient( self::TRANSIENT_PREFIX . $user_id );
		if ( $result ) {
			delete_transient( self::TRANSIENT_PREFIX . $user_id );
		}
		$staged  = get_transient( self::STAGED_PREFIX . $user_id );
		$job_id  = get_transient( self::ACTIVE_JOB_PREFIX . $user_id );
		$job     = $job_id ? get_transient( self::JOB_PREFIX . $job_id ) : false;
		if ( ! is_array( $job ) ) {
			$job_id = '';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Перенос данных VibeLMS', 'lifterlms' ); ?></h1>
			<p><?php esc_html_e( 'Переносит настройки аттестации, курсы, уроки, тесты, вопросы, группы доступа, пользователей, прогресс, попытки, отчёты и связанные медиафайлы.', 'lifterlms' ); ?></p>

			<?php if ( is_array( $result ) ) : ?>
				<div class="notice <?php echo ! empty( $result['errors'] ) ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
					<p><strong><?php echo esc_html( isset( $result['message'] ) ? $result['message'] : __( 'Перенос завершён.', 'lifterlms' ) ); ?></strong></p>
					<?php if ( ! empty( $result['stats'] ) ) : ?>
						<p><?php echo esc_html( $this->format_stats( $result['stats'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $result['errors'] ) ) : ?>
						<ul><?php foreach ( $result['errors'] as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
					<?php endif; ?>
					<?php if ( ! empty( $result['report_token'] ) ) : ?>
						<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vibelms_download_report&token=' . rawurlencode( $result['report_token'] ) ), 'vibelms_download_report_' . $result['report_token'] ) ); ?>"><?php esc_html_e( 'Скачать подробный JSON-отчёт', 'lifterlms' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width:900px;padding:20px;">
				<h2><?php esc_html_e( 'Экспорт', 'lifterlms' ); ?></h2>
				<p><?php esc_html_e( 'Будет скачан один ZIP-файл, который можно загрузить на другой сайт с установленным VibeLMS.', 'lifterlms' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="vibelms_export_bundle">
					<?php wp_nonce_field( 'vibelms_export_bundle' ); ?>
					<?php submit_button( __( 'Скачать полный экспорт', 'lifterlms' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<div class="card" style="max-width:900px;padding:20px;">
				<h2><?php esc_html_e( 'Импорт', 'lifterlms' ); ?></h2>
				<p><strong><?php esc_html_e( 'Сначала VibeLMS проверит архив. Импорт начнётся только после подтверждения.', 'lifterlms' ); ?></strong></p>
				<p><?php esc_html_e( 'Импорт добавляет данные и не удаляет существующие курсы, пользователей или тесты. Пароли пользователей не переносятся.', 'lifterlms' ); ?></p>

				<?php if ( $job_id ) : ?>
					<?php $this->render_job_progress( $job_id, $job ); ?>
				<?php elseif ( is_array( $staged ) ) : ?>
					<?php $this->render_import_preview( $staged ); ?>
				<?php else : ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="vibelms_prepare_import">
						<?php wp_nonce_field( 'vibelms_prepare_import' ); ?>
						<p><input type="file" name="vibelms_bundle" accept=".zip,application/zip" required></p>
						<?php submit_button( __( 'Проверить ZIP', 'lifterlms' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a preflight report and confirmation controls.
	 *
	 * @param array $staged Staged archive data.
	 * @return void
	 */
	private function render_import_preview( $staged ) {
		$preview = isset( $staged['preview'] ) && is_array( $staged['preview'] ) ? $staged['preview'] : array();
		$counts  = isset( $preview['counts'] ) && is_array( $preview['counts'] ) ? $preview['counts'] : array();
		?>
		<div class="notice notice-info inline">
			<p><strong><?php esc_html_e( 'Проверка завершена. Перед импортом проверьте сводку.', 'lifterlms' ); ?></strong></p>
			<p><?php echo esc_html( sprintf( __( 'Источник: %1$s. Архив создан: %2$s.', 'lifterlms' ), isset( $preview['source_site'] ) ? $preview['source_site'] : '—', isset( $preview['created_at'] ) ? $preview['created_at'] : '—' ) ); ?></p>
		</div>
		<table class="widefat striped" style="max-width:700px;margin:16px 0;">
			<tbody>
				<?php foreach ( array( 'courses' => 'Курсы', 'memberships' => 'Группы доступа', 'certificates' => 'Сертификаты', 'users' => 'Пользователи', 'media' => 'Медиафайлы', 'quiz_attempts' => 'Попытки тестов' ) as $key => $label ) : ?>
					<tr><td><?php echo esc_html( $label ); ?></td><td><strong><?php echo esc_html( absint( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 ) ); ?></strong></td></tr>
				<?php endforeach; ?>
				<tr><td><?php esc_html_e( 'Пользователи будут переиспользованы', 'lifterlms' ); ?></td><td><strong><?php echo esc_html( absint( isset( $preview['users_reused'] ) ? $preview['users_reused'] : 0 ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Новые пользователи', 'lifterlms' ); ?></td><td><strong><?php echo esc_html( absint( isset( $preview['users_new'] ) ? $preview['users_new'] : 0 ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Ранее импортированные записи', 'lifterlms' ); ?></td><td><strong><?php echo esc_html( absint( isset( $preview['previous_records'] ) ? $preview['previous_records'] : 0 ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Размер архива', 'lifterlms' ); ?></td><td><strong><?php echo esc_html( size_format( isset( $preview['archive_size'] ) ? $preview['archive_size'] : 0 ) ); ?></strong></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $preview['warnings'] ) ) : ?>
			<div class="notice notice-warning inline"><ul><?php foreach ( $preview['warnings'] as $warning ) : ?><li><?php echo esc_html( $warning ); ?></li><?php endforeach; ?></ul></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;">
			<input type="hidden" name="action" value="vibelms_start_import">
			<input type="hidden" name="vibelms_transfer_token" value="<?php echo esc_attr( isset( $staged['token'] ) ? $staged['token'] : '' ); ?>">
			<?php wp_nonce_field( 'vibelms_start_import' ); ?>
			<label><span style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Поведение для ранее перенесённых записей', 'lifterlms' ); ?></span>
				<select name="duplicate_mode">
					<option value="create"><?php esc_html_e( 'Создавать копии', 'lifterlms' ); ?></option>
					<option value="skip"><?php esc_html_e( 'Пропустить ранее перенесённые', 'lifterlms' ); ?></option>
				</select>
			</label>
			<?php submit_button( __( 'Подтвердить и начать импорт', 'lifterlms' ), 'primary', 'submit', false ); ?>
		</form>
		<p><small><?php esc_html_e( 'Режим «Пропустить» работает по служебной отметке источника и не затрагивает данные, созданные вручную на целевом сайте.', 'lifterlms' ); ?></small></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="vibelms_cancel_import">
			<?php wp_nonce_field( 'vibelms_cancel_import' ); ?>
			<?php submit_button( __( 'Отменить проверку', 'lifterlms' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render the live import progress panel.
	 *
	 * @param string $job_id Job ID.
	 * @param array  $job    Job state.
	 * @return void
	 */
	private function render_job_progress( $job_id, $job ) {
		$progress = $this->job_progress( is_array( $job ) ? $job : array() );
		wp_enqueue_script( 'jquery' );
		?>
		<div id="vibelms-transfer-progress" data-job-id="<?php echo esc_attr( $job_id ); ?>">
			<p><strong><?php esc_html_e( 'Импорт выполняется. Не закрывайте эту страницу.', 'lifterlms' ); ?></strong></p>
			<progress id="vibelms-transfer-progress-bar" max="100" value="<?php echo esc_attr( $progress['percent'] ); ?>" style="width:100%;height:24px;"></progress>
			<p id="vibelms-transfer-progress-text" aria-live="polite"><?php echo esc_html( $progress['message'] ); ?></p>
			<p id="vibelms-transfer-progress-stats"><?php echo esc_html( $this->format_stats( isset( $job['stats'] ) ? $job['stats'] : array() ) ); ?></p>
		</div>
		<script>
		(function($) {
			var panel = $('#vibelms-transfer-progress');
			var endpoint = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'vibelms_transfer_progress' ) ); ?>;
			function poll() {
				$.post(endpoint, { action: 'vibelms_transfer_progress', nonce: nonce, job_id: panel.data('job-id') })
					.done(function(response) {
						if (!response || !response.success) {
							$('#vibelms-transfer-progress-text').text('<?php echo esc_js( __( 'Не удалось получить состояние импорта. Обновите страницу.', 'lifterlms' ) ); ?>');
							return;
						}
						var data = response.data || {};
						var progress = data.progress || {};
						$('#vibelms-transfer-progress-bar').val(parseInt(progress.percent || 0, 10));
						$('#vibelms-transfer-progress-text').text(progress.message || '');
						$('#vibelms-transfer-progress-stats').text(data.stats_text || '');
						if (data.finished) {
							window.location.reload();
							return;
						}
						window.setTimeout(poll, 700);
					})
					.fail(function() { window.setTimeout(poll, 1500); });
			}
			poll();
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Download a complete export archive.
	 *
	 * @return void
	 */
	public function export_bundle() {
		$this->authorize_request( 'vibelms_export_bundle' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'На сервере не включено расширение PHP ZipArchive.', 'lifterlms' ) );
		}

		$bundle = $this->build_export_bundle();
		if ( is_wp_error( $bundle ) ) {
			wp_die( esc_html( $bundle->get_error_message() ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$path = wp_tempnam( 'vibelms-export' );
		$zip  = new ZipArchive();
		if ( ! $path || true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Не удалось создать ZIP-архив экспорта.', 'lifterlms' ) );
		}

		foreach ( $bundle['files'] as $name => $contents ) {
			$zip->addFromString( $name, $contents );
		}
		foreach ( $bundle['media'] as $media ) {
			$zip->addFile( $media['path'], $media['entry'] );
		}
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="vibelms-transfer-' . gmdate( 'Y-m-d-His' ) . '.zip"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		exit;
	}

	/**
	 * Stage an uploaded archive and run the preflight check.
	 *
	 * @return void
	 */
	public function prepare_import_bundle() {
		$this->authorize_request( 'vibelms_prepare_import' );
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->redirect_with_result( __( 'Импорт невозможен: на сервере не включено расширение PHP ZipArchive.', 'lifterlms' ), array(), array() );
		}

		$file = isset( $_FILES['vibelms_bundle'] ) && is_array( $_FILES['vibelms_bundle'] ) ? $_FILES['vibelms_bundle'] : array();
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			$this->redirect_with_result( __( 'ZIP-файл не был загружен.', 'lifterlms' ), array(), array( __( 'Проверьте размер и формат файла.', 'lifterlms' ) ) );
		}

		$this->clear_staged_archive( get_current_user_id() );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$path = wp_tempnam( 'vibelms-import' );
		if ( ! $path || ! copy( $file['tmp_name'], $path ) ) {
			$this->redirect_with_result( __( 'Не удалось сохранить ZIP во временное хранилище.', 'lifterlms' ), array(), array() );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->redirect_with_result( __( 'Не удалось открыть ZIP-файл.', 'lifterlms' ), array(), array() );
		}
		$bundle = $this->read_bundle( $zip );
		$zip->close();
		if ( is_wp_error( $bundle ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->redirect_with_result( $bundle->get_error_message(), array(), array() );
		}

		set_transient(
			self::STAGED_PREFIX . get_current_user_id(),
			array(
				'token'     => wp_generate_password( 32, false, false ),
				'path'      => $path,
				'preview'   => $this->inspect_bundle( $bundle, filesize( $path ) ),
				'staged_at' => time(),
			),
			HOUR_IN_SECONDS
		);
		$this->redirect_with_result( __( 'Архив проверен. Проверьте сводку и подтвердите импорт.', 'lifterlms' ), array(), array() );
	}

	/**
	 * Start a staged import job.
	 *
	 * @return void
	 */
	public function start_import_bundle() {
		$this->authorize_request( 'vibelms_start_import' );
		$staged = get_transient( self::STAGED_PREFIX . get_current_user_id() );
		$token  = isset( $_POST['vibelms_transfer_token'] ) ? sanitize_text_field( wp_unslash( $_POST['vibelms_transfer_token'] ) ) : '';
		if ( ! is_array( $staged ) || empty( $staged['path'] ) || empty( $staged['token'] ) || ! hash_equals( $staged['token'], $token ) ) {
			$this->redirect_with_result( __( 'Проверенный архив устарел. Загрузите его ещё раз.', 'lifterlms' ), array(), array() );
		}

		$mode = isset( $_POST['duplicate_mode'] ) ? sanitize_key( wp_unslash( $_POST['duplicate_mode'] ) ) : 'create';
		$mode = in_array( $mode, array( 'create', 'skip' ), true ) ? $mode : 'create';
		$zip = new ZipArchive();
		if ( true !== $zip->open( $staged['path'] ) ) {
			$this->clear_staged_archive( get_current_user_id() );
			$this->redirect_with_result( __( 'Не удалось открыть проверенный архив.', 'lifterlms' ), array(), array() );
		}
		$bundle = $this->read_bundle( $zip );
		$zip->close();
		if ( is_wp_error( $bundle ) ) {
			$this->clear_staged_archive( get_current_user_id() );
			$this->redirect_with_result( $bundle->get_error_message(), array(), array() );
		}

		$source_site = esc_url_raw( isset( $bundle['manifest']['source_site'] ) ? $bundle['manifest']['source_site'] : '' );
		$maps = array( 'users' => array(), 'posts' => array(), 'attempts' => array(), 'media' => array() );
		if ( 'skip' === $mode ) {
			$this->hydrate_existing_maps( $bundle['data'], $source_site, $maps );
		}
		$job_id = wp_generate_uuid4();
		$job = array(
			'id'              => $job_id,
			'path'            => $staged['path'],
			'source_site'     => $source_site,
			'duplicate_mode'  => $mode,
			'manifest'        => $bundle['manifest'],
			'counts'          => isset( $staged['preview']['counts'] ) ? $staged['preview']['counts'] : array(),
			'stage'           => 'users',
			'offset'          => 0,
			'maps'            => $maps,
			'stats'           => array(),
			'errors'          => array(),
			'created_at'      => time(),
		);
		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
		set_transient( self::ACTIVE_JOB_PREFIX . get_current_user_id(), $job_id, self::JOB_TTL );
		delete_transient( self::STAGED_PREFIX . get_current_user_id() );
		$this->redirect_with_result( __( 'Импорт запущен. Статус будет обновляться автоматически.', 'lifterlms' ), array(), array() );
	}

	/**
	 * Cancel a staged archive.
	 *
	 * @return void
	 */
	public function cancel_import_bundle() {
		$this->authorize_request( 'vibelms_cancel_import' );
		$this->clear_staged_archive( get_current_user_id() );
		$this->redirect_with_result( __( 'Проверка импорта отменена.', 'lifterlms' ), array(), array() );
	}

	/**
	 * Download a completed import report as JSON.
	 *
	 * @return void
	 */
	public function download_import_report() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'У вас нет доступа к отчёту переноса.', 'lifterlms' ) );
		}
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		check_admin_referer( 'vibelms_download_report_' . $token );
		$report = $token ? get_transient( self::REPORT_PREFIX . get_current_user_id() . '_' . $token ) : false;
		if ( ! is_array( $report ) ) {
			wp_die( esc_html__( 'Отчёт устарел. Запустите перенос ещё раз.', 'lifterlms' ) );
		}
		delete_transient( self::REPORT_PREFIX . get_current_user_id() . '_' . $token );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="vibelms-transfer-report-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		echo wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Process one import batch through AJAX.
	 *
	 * @return void
	 */
	public function ajax_transfer_progress() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_ajax_referer( 'vibelms_transfer_progress', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав или истёк срок проверки.', 'lifterlms' ) ), 403 );
		}
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job = $job_id ? get_transient( self::JOB_PREFIX . $job_id ) : false;
		if ( ! is_array( $job ) || get_transient( self::ACTIVE_JOB_PREFIX . get_current_user_id() ) !== $job_id ) {
			wp_send_json_error( array( 'message' => __( 'Импорт не найден или уже завершён.', 'lifterlms' ) ), 404 );
		}

		$step = $this->process_import_job( $job );
		if ( is_wp_error( $step ) ) {
			$job['errors'][] = $step->get_error_message();
			$job['stage'] = 'failed';
			$this->finish_import_job( $job, __( 'Импорт остановлен с ошибкой.', 'lifterlms' ) );
			wp_send_json_success( array( 'finished' => true, 'stats_text' => $this->format_stats( $job['stats'] ), 'progress' => $this->job_progress( $job ) ) );
		}
		if ( ! empty( $step['finished'] ) ) {
			$this->finish_import_job( $job, empty( $job['errors'] ) ? __( 'Импорт VibeLMS завершён.', 'lifterlms' ) : __( 'Импорт завершён с предупреждениями.', 'lifterlms' ) );
			wp_send_json_success( array( 'finished' => true, 'stats_text' => $this->format_stats( $job['stats'] ), 'progress' => $this->job_progress( $job ) ) );
		}

		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
		wp_send_json_success( array( 'finished' => false, 'stats_text' => $this->format_stats( $job['stats'] ), 'progress' => $this->job_progress( $job ) ) );
	}

	/**
	 * Process one bounded import batch.
	 *
	 * @param array $job Job state by reference.
	 * @return array|WP_Error
	 */
	private function process_import_job( &$job ) {
		if ( empty( $job['path'] ) || ! is_readable( $job['path'] ) ) {
			return new WP_Error( 'vibelms_transfer_missing_staged_file', __( 'Временный файл импорта больше недоступен.', 'lifterlms' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $job['path'] ) ) {
			return new WP_Error( 'vibelms_transfer_open_staged_file', __( 'Не удалось открыть временный архив импорта.', 'lifterlms' ) );
		}
		$bundle = $this->read_bundle( $zip );
		if ( is_wp_error( $bundle ) ) {
			$zip->close();
			return $bundle;
		}

		$this->source_site    = isset( $job['source_site'] ) ? $job['source_site'] : '';
		$this->duplicate_mode = isset( $job['duplicate_mode'] ) ? $job['duplicate_mode'] : 'create';
		$data = $bundle['data'];
		$this->hydrate_media_records( $data['media'], $job['maps']['media'] );
		$batch_size = self::BATCH_SIZE;
		$offset     = absint( isset( $job['offset'] ) ? $job['offset'] : 0 );
		$stage      = isset( $job['stage'] ) ? $job['stage'] : 'users';

		switch ( $stage ) {
			case 'users':
				$batch = array_slice( $data['users'], $offset, $batch_size );
				$this->import_users( $batch, $job['maps']['users'], $job['stats'], $job['errors'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['users'] ) ) {
					$job['stage']  = 'media';
					$job['offset'] = 0;
				}
				break;

			case 'media':
				$this->import_media( $zip, $data['media'], $job['maps']['media'], $job['stats'], $job['errors'], $offset, $batch_size );
				$job['offset'] += min( $batch_size, max( 0, count( $data['media'] ) - $offset ) );
				if ( $job['offset'] >= count( $data['media'] ) ) {
					$job['stage']  = 'courses';
					$job['offset'] = 0;
				}
				break;

			case 'courses':
				$this->featured_media = array();
				$batch = array_slice( $data['courses'], $offset, 1 );
				$course_data = $this->prepare_course_data_for_import( $batch, $data['media'] );
				$this->import_courses( $course_data, $job['maps']['posts'], $job['stats'], $job['errors'] );
				$this->apply_featured_images( $job['maps']['posts'] );
				$this->mark_imported_records( $job['maps'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['courses'] ) ) {
					$job['stage']  = 'memberships';
					$job['offset'] = 0;
				}
				break;

			case 'memberships':
				$this->featured_media = array();
				$batch = array_slice( $data['memberships'], $offset, 1 );
				$membership_data = $this->prepare_course_data_for_import( $batch, $data['media'] );
				$this->import_memberships( $membership_data, $job['maps'], $job['stats'], $job['errors'] );
				$this->apply_featured_images( $job['maps']['posts'] );
				$this->mark_imported_records( $job['maps'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['memberships'] ) ) {
					$job['stage']  = 'certificates';
					$job['offset'] = 0;
				}
				break;

			case 'certificates':
				$batch = array_slice( $data['certificates'], $offset, $batch_size );
				$this->import_certificates( $batch, $job['maps'], $data['media'], $job['stats'], $job['errors'] );
				$this->mark_imported_records( $job['maps'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['certificates'] ) ) {
					$job['stage']  = 'settings';
					$job['offset'] = 0;
				}
				break;

			case 'settings':
				$this->import_settings( $data['settings'], $job['maps']['posts'], $job['stats'], $job['errors'] );
				$job['stage']  = 'enrollments';
				$job['offset'] = 0;
				break;

			case 'enrollments':
				$batch = array_slice( $data['enrollments'], $offset, $batch_size * 5 );
				$this->import_enrollments( $batch, $job['maps'], $job['stats'], $job['errors'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['enrollments'] ) ) {
					$job['stage']  = 'quiz_attempts';
					$job['offset'] = 0;
				}
				break;

			case 'quiz_attempts':
				$batch = array_slice( $data['quiz_attempts'], $offset, $batch_size * 5 );
				$this->import_quiz_attempts( $batch, $job['maps'], $job['stats'], $job['errors'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['quiz_attempts'] ) ) {
					$job['stage']  = 'vibelms_attempts';
					$job['offset'] = 0;
				}
				break;

			case 'vibelms_attempts':
				$batch = array_slice( $data['vibelms_attempts'], $offset, $batch_size * 5 );
				$this->import_vibelms_attempts( $batch, $job['maps'], $job['stats'], $job['errors'] );
				$job['offset'] += count( $batch );
				if ( $job['offset'] >= count( $data['vibelms_attempts'] ) ) {
					$job['stage']  = 'finalize';
					$job['offset'] = 0;
				}
				break;

			case 'finalize':
				$this->repair_media_parents( $data['media'], $job['maps']['media'], $job['maps']['posts'] );
				$this->mark_imported_records( $job['maps'] );
				$job['stage'] = 'done';
				break;
		}

		$zip->close();
		return array( 'finished' => 'done' === $job['stage'] );
	}

	/**
	 * Read and validate the complete transfer payload.
	 *
	 * @param ZipArchive $zip Archive.
	 * @return array|WP_Error
	 */
	private function read_bundle( $zip ) {
		$manifest = $this->read_archive_json( $zip, 'manifest.json' );
		if ( ! is_array( $manifest ) || empty( $manifest['format'] ) || self::FORMAT !== $manifest['format'] ) {
			return new WP_Error( 'vibelms_transfer_invalid_manifest', __( 'Файл не является экспортом VibeLMS.', 'lifterlms' ) );
		}
		if ( empty( $manifest['format_version'] ) || self::FORMAT_VERSION !== absint( $manifest['format_version'] ) ) {
			return new WP_Error( 'vibelms_transfer_unsupported_format', __( 'Версия формата архива не поддерживается этой версией VibeLMS.', 'lifterlms' ) );
		}
		$data = array();
		foreach ( array( 'settings', 'users', 'courses', 'memberships', 'certificates', 'enrollments', 'quiz_attempts', 'vibelms_attempts', 'media' ) as $name ) {
			$data[ $name ] = $this->read_archive_json( $zip, $name . '.json' );
			if ( is_wp_error( $data[ $name ] ) ) {
				return $data[ $name ];
			}
			if ( ! is_array( $data[ $name ] ) ) {
				return new WP_Error( 'vibelms_transfer_invalid_data', sprintf( __( 'Файл %s имеет неверную структуру.', 'lifterlms' ), $name . '.json' ) );
			}
		}
		return array( 'manifest' => $manifest, 'data' => $data );
	}

	/**
	 * Build the human-readable preflight summary.
	 *
	 * @param array    $bundle       Transfer payload.
	 * @param int|null $archive_size Archive size in bytes.
	 * @return array
	 */
	private function inspect_bundle( $bundle, $archive_size = null ) {
		$manifest = $bundle['manifest'];
		$data = $bundle['data'];
		$source_site = esc_url_raw( isset( $manifest['source_site'] ) ? $manifest['source_site'] : '' );
		$counts = array(
			'courses'          => count( $data['courses'] ),
			'memberships'      => count( $data['memberships'] ),
			'certificates'     => count( $data['certificates'] ),
			'users'            => count( $data['users'] ),
			'media'            => count( $data['media'] ),
			'quiz_attempts'    => count( $data['quiz_attempts'] ),
			'enrollments'      => count( $data['enrollments'] ),
			'vibelms_attempts' => count( $data['vibelms_attempts'] ),
			'settings'         => count( $data['settings'] ),
		);
		$users_reused = 0;
		foreach ( $data['users'] as $user ) {
			if ( ! empty( $user['user_email'] ) && get_user_by( 'email', sanitize_email( $user['user_email'] ) ) ) {
				$users_reused++;
			}
		}
		$warnings = array();
		if ( $source_site && untrailingslashit( $source_site ) === untrailingslashit( home_url( '/' ) ) ) {
			$warnings[] = __( 'Источник и целевой сайт совпадают. Для теста лучше использовать staging-копию.', 'lifterlms' );
		}
		if ( ! empty( $manifest['plugin_version'] ) && defined( 'VIBELMS_VERSION' ) && version_compare( $manifest['plugin_version'], VIBELMS_VERSION, '>' ) ) {
			$warnings[] = __( 'Архив создан более новой версией VibeLMS. Сначала обновите целевой сайт.', 'lifterlms' );
		}
		if ( empty( $data['courses'] ) ) {
			$warnings[] = __( 'В архиве нет курсов.', 'lifterlms' );
		}
		if ( $archive_size && function_exists( 'disk_free_space' ) ) {
			$free_space = disk_free_space( get_temp_dir() );
			if ( false !== $free_space && $archive_size > $free_space ) {
				$warnings[] = __( 'Свободного места во временной папке меньше размера архива.', 'lifterlms' );
			}
		}
		return array(
			'source_site'       => $source_site,
			'created_at'        => isset( $manifest['created_at'] ) ? $manifest['created_at'] : '',
			'counts'            => $counts,
			'users_reused'      => $users_reused,
			'users_new'         => max( 0, $counts['users'] - $users_reused ),
			'previous_records'  => $this->count_existing_transfer_records( $source_site ),
			'archive_size'      => absint( $archive_size ),
			'warnings'          => $warnings,
		);
	}

	/**
	 * Find records imported earlier from the same source.
	 *
	 * @param string $source_site Source site URL.
	 * @return int
	 */
	private function count_existing_transfer_records( $source_site ) {
		if ( ! $source_site ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => array( 'course', 'section', 'lesson', 'llms_quiz', 'llms_question', 'llms_membership', 'llms_certificate', 'attachment' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => self::SOURCE_SITE_META, 'value' => $source_site ) ),
			)
		);
		return count( $ids );
	}

	/**
	 * Hydrate source-to-destination maps for skip mode.
	 *
	 * @param array  $data        Transfer data.
	 * @param string $source_site Source site URL.
	 * @param array  $maps        Maps by reference.
	 * @return void
	 */
	private function hydrate_existing_maps( $data, $source_site, &$maps ) {
		if ( ! $source_site ) {
			return;
		}
		$post_ids = array();
		foreach ( isset( $data['courses'] ) && is_array( $data['courses'] ) ? $data['courses'] : array() as $course ) {
			$this->collect_course_ids( $course, $post_ids );
		}
		foreach ( isset( $data['memberships'] ) && is_array( $data['memberships'] ) ? $data['memberships'] : array() as $membership ) {
			$this->collect_numeric_ids( isset( $membership['id'] ) ? $membership['id'] : 0, $post_ids );
		}
		foreach ( isset( $data['certificates'] ) && is_array( $data['certificates'] ) ? $data['certificates'] : array() as $certificate ) {
			$this->collect_numeric_ids( isset( $certificate['id'] ) ? $certificate['id'] : 0, $post_ids );
		}
		$post_types = array( 'course', 'section', 'lesson', 'llms_quiz', 'llms_question', 'llms_membership', 'llms_certificate' );
		foreach ( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) as $source_id ) {
			$found = get_posts(
				array(
					'post_type'      => $post_types,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array( 'key' => self::SOURCE_SITE_META, 'value' => $source_site ),
						array( 'key' => self::SOURCE_ID_META, 'value' => $source_id ),
					),
				)
			);
			if ( ! empty( $found ) ) {
				$maps['posts'][ $source_id ] = absint( $found[0] );
			}
		}
		foreach ( isset( $data['media'] ) && is_array( $data['media'] ) ? $data['media'] : array() as $media ) {
			$source_id = absint( isset( $media['source_id'] ) ? $media['source_id'] : 0 );
			if ( ! $source_id ) {
				continue;
			}
			$found = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array( 'key' => self::SOURCE_SITE_META, 'value' => $source_site ),
						array( 'key' => self::SOURCE_ID_META, 'value' => $source_id ),
					),
				)
			);
			if ( ! empty( $found ) ) {
				$maps['media'][ $source_id ] = absint( $found[0] );
			}
		}
	}

	/**
	 * Add destination fields to media records from a persisted map.
	 *
	 * @param array[] $media Media records by reference.
	 * @param array   $map   Media map.
	 * @return void
	 */
	private function hydrate_media_records( &$media, $map ) {
		foreach ( is_array( $media ) ? $media : array() as &$item ) {
			$source_id = absint( isset( $item['source_id'] ) ? $item['source_id'] : 0 );
			if ( $source_id && isset( $map[ $source_id ] ) ) {
				$item['destination_id']  = absint( $map[ $source_id ] );
				$item['destination_url'] = wp_get_attachment_url( $map[ $source_id ] );
			}
		}
		unset( $item );
	}

	/**
	 * Mark imported records so future preflight checks can identify them.
	 *
	 * @param array $maps Import maps.
	 * @return void
	 */
	private function mark_imported_records( $maps ) {
		if ( ! $this->source_site ) {
			return;
		}
		foreach ( isset( $maps['posts'] ) && is_array( $maps['posts'] ) ? $maps['posts'] : array() as $source_id => $destination_id ) {
			if ( $destination_id ) {
				update_post_meta( $destination_id, self::SOURCE_SITE_META, $this->source_site );
				update_post_meta( $destination_id, self::SOURCE_ID_META, absint( $source_id ) );
			}
		}
		foreach ( isset( $maps['media'] ) && is_array( $maps['media'] ) ? $maps['media'] : array() as $source_id => $destination_id ) {
			if ( $destination_id ) {
				update_post_meta( $destination_id, self::SOURCE_SITE_META, $this->source_site );
				update_post_meta( $destination_id, self::SOURCE_ID_META, absint( $source_id ) );
			}
		}
	}

	/**
	 * Remove a staged temporary file.
	 *
	 * @param int $user_id Current user ID.
	 * @return void
	 */
	private function clear_staged_archive( $user_id ) {
		$staged = get_transient( self::STAGED_PREFIX . absint( $user_id ) );
		if ( is_array( $staged ) && ! empty( $staged['path'] ) && is_file( $staged['path'] ) ) {
			@unlink( $staged['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		delete_transient( self::STAGED_PREFIX . absint( $user_id ) );
	}

	/**
	 * Complete a job and expose its report on the admin screen.
	 *
	 * @param array  $job     Job state.
	 * @param string $message Completion message.
	 * @return void
	 */
	private function finish_import_job( $job, $message ) {
		$report_token = wp_generate_password( 24, false, false );
		$report = array(
			'format'       => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'created_at'   => current_time( 'mysql' ),
			'source_site'  => isset( $job['source_site'] ) ? $job['source_site'] : '',
			'duplicate_mode' => isset( $job['duplicate_mode'] ) ? $job['duplicate_mode'] : 'create',
			'stats'        => $job['stats'],
			'errors'       => $job['errors'],
		);
		set_transient( self::REPORT_PREFIX . get_current_user_id() . '_' . $report_token, $report, 600 );
		set_transient( self::TRANSIENT_PREFIX . get_current_user_id(), array( 'message' => $message, 'stats' => $job['stats'], 'errors' => $job['errors'], 'report_token' => $report_token ), 600 );
		$this->log_transfer( 'VibeLMS transfer import completed', array( 'stats' => $job['stats'], 'errors' => $job['errors'] ) );
		if ( ! empty( $job['path'] ) && is_file( $job['path'] ) ) {
			@unlink( $job['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		delete_transient( self::JOB_PREFIX . $job['id'] );
		delete_transient( self::ACTIVE_JOB_PREFIX . get_current_user_id() );
	}

	/**
	 * Calculate progress for the current import phase.
	 *
	 * @param array $job Job state.
	 * @return array
	 */
	private function job_progress( $job ) {
		$stages = array( 'users', 'media', 'courses', 'memberships', 'certificates', 'settings', 'enrollments', 'quiz_attempts', 'vibelms_attempts', 'finalize' );
		$labels = array(
			'users'            => __( 'Импорт пользователей', 'lifterlms' ),
			'media'            => __( 'Импорт медиафайлов', 'lifterlms' ),
			'courses'          => __( 'Импорт курсов, уроков и тестов', 'lifterlms' ),
			'memberships'      => __( 'Импорт групп доступа', 'lifterlms' ),
			'certificates'     => __( 'Импорт сертификатов', 'lifterlms' ),
			'settings'         => __( 'Импорт настроек', 'lifterlms' ),
			'enrollments'      => __( 'Импорт зачислений', 'lifterlms' ),
			'quiz_attempts'    => __( 'Импорт попыток тестов', 'lifterlms' ),
			'vibelms_attempts' => __( 'Импорт журнала VibeLMS', 'lifterlms' ),
			'finalize'         => __( 'Завершение переноса', 'lifterlms' ),
			'done'             => __( 'Импорт завершён', 'lifterlms' ),
			'failed'           => __( 'Импорт остановлен', 'lifterlms' ),
		);
		$counts = isset( $job['counts'] ) && is_array( $job['counts'] ) ? $job['counts'] : array();
		$stage = isset( $job['stage'] ) ? $job['stage'] : 'users';
		$index = array_search( $stage, $stages, true );
		if ( false === $index ) {
			$index = 'done' === $stage ? count( $stages ) : 0;
		}
		$count = isset( $counts[ $stage ] ) ? absint( $counts[ $stage ] ) : 1;
		$offset = absint( isset( $job['offset'] ) ? $job['offset'] : 0 );
		$fraction = $count ? min( 1, $offset / $count ) : 1;
		$percent = min( 100, (int) floor( ( ( $index + $fraction ) / count( $stages ) ) * 100 ) );
		return array( 'percent' => $percent, 'message' => isset( $labels[ $stage ] ) ? $labels[ $stage ] : __( 'Подготовка импорта', 'lifterlms' ) );
	}

	/**
	 * Build all export files and media references.
	 *
	 * @return array|WP_Error
	 */
	private function build_export_bundle() {
		if ( ! class_exists( 'LLMS_Course' ) || ! class_exists( 'LLMS_Generator_Courses' ) ) {
			return new WP_Error( 'vibelms_transfer_missing_lms', __( 'Ядро VibeLMS ещё не загрузило модели курсов.', 'lifterlms' ) );
		}

		$old_extra_filter = has_filter( 'llms_post_model_to_array_add_extras' );
		add_filter( 'llms_post_model_to_array_add_extras', '__return_true', 99 );
		$courses = array();
		foreach ( get_posts( array( 'post_type' => 'course', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
			$courses[] = ( new LLMS_Course( $post->ID ) )->toArray();
		}
		$memberships = array();
		if ( class_exists( 'LLMS_Membership' ) ) {
			foreach ( get_posts( array( 'post_type' => 'llms_membership', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
				$memberships[] = ( new LLMS_Membership( $post->ID ) )->toArray();
			}
		}
		if ( false === $old_extra_filter ) {
			remove_filter( 'llms_post_model_to_array_add_extras', '__return_true', 99 );
		}

		$content_ids = array();
		foreach ( $courses as $course ) {
			$this->collect_course_ids( $course, $content_ids );
		}
		foreach ( $memberships as $membership ) {
			$this->collect_numeric_ids( isset( $membership['id'] ) ? $membership['id'] : 0, $content_ids );
			if ( ! empty( $membership['auto_enroll'] ) ) {
				foreach ( (array) $membership['auto_enroll'] as $course_id ) {
					$this->collect_numeric_ids( $course_id, $content_ids );
				}
			}
		}

		$certificates = $this->export_certificate_templates();
		foreach ( $certificates as $certificate ) {
			$this->collect_numeric_ids( $certificate['id'], $content_ids );
		}
		$content_ids = array_values( array_unique( array_filter( array_map( 'absint', $content_ids ) ) ) );

		$user_ids = $this->collect_export_user_ids( array_merge( $courses, $certificates ), $memberships );
		$this->collect_database_user_ids( $content_ids, $user_ids );
		$users    = $this->export_users( $user_ids );
		$user_ids = array_values( array_unique( array_merge( $user_ids, wp_list_pluck( $users, 'id' ) ) ) );
		$media    = $this->export_media( $content_ids, array_merge( $courses, $memberships ) );

		$settings = array();
		foreach ( $this->option_names as $option_name ) {
			$value = get_option( $option_name, null );
			if ( null !== $value ) {
				$settings[ $option_name ] = $value;
			}
		}

		$manifest = array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => defined( 'VIBELMS_VERSION' ) ? VIBELMS_VERSION : '',
			'source_site'    => home_url( '/' ),
			'created_at'     => current_time( 'mysql' ),
			'counts'         => array(
				'courses'      => count( $courses ),
				'memberships'  => count( $memberships ),
				'certificates' => count( $certificates ),
				'users'        => count( $users ),
				'media'        => count( $media ),
			),
		);

		$files = array(
			'manifest.json'        => $this->json( $manifest ),
			'settings.json'        => $this->json( $settings ),
			'courses.json'         => $this->json( $courses ),
			'memberships.json'     => $this->json( $memberships ),
			'certificates.json'    => $this->json( $certificates ),
			'users.json'           => $this->json( $users ),
			'enrollments.json'     => $this->json( $this->export_enrollments( $user_ids, $content_ids ) ),
			'quiz_attempts.json'   => $this->json( $this->export_quiz_attempts( $user_ids, $content_ids ) ),
			'vibelms_attempts.json' => $this->json( $this->export_vibelms_attempts( $user_ids, $content_ids ) ),
			'media.json'           => $this->json( $this->export_media_records( $media ) ),
		);

		return array( 'files' => $files, 'media' => $media );
	}

	/**
	 * Export certificate templates as normal WordPress posts.
	 *
	 * @return array[]
	 */
	private function export_certificate_templates() {
		$items = array();
		foreach ( get_posts( array( 'post_type' => 'llms_certificate', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
			$items[] = array(
				'id'        => absint( $post->ID ),
				'post'      => $this->export_post_fields( $post ),
				'meta'      => $this->export_post_meta( $post->ID ),
				'taxonomies' => $this->export_post_terms( $post->ID ),
			);
		}
		return $items;
	}

	/**
	 * Get post fields that are safe to recreate on a different site.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function export_post_fields( $post ) {
		return array(
			'post_author'    => absint( $post->post_author ),
			'post_date'      => $post->post_date,
			'post_date_gmt'  => $post->post_date_gmt,
			'post_content'   => $post->post_content,
			'post_title'     => $post->post_title,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $post->post_status,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_password'  => '',
			'post_name'      => $post->post_name,
			'to_ping'        => '',
			'pinged'         => '',
			'post_modified'  => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_content_filtered' => '',
			'post_parent'    => absint( $post->post_parent ),
			'menu_order'     => absint( $post->menu_order ),
			'post_type'      => $post->post_type,
			'post_mime_type' => $post->post_mime_type,
		);
	}

	/**
	 * Export non-sensitive post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array[]
	 */
	private function export_post_meta( $post_id ) {
		$meta = array();
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug' ), true ) ) {
				continue;
			}
			$meta[ $key ] = array_map( array( $this, 'safe_meta_value' ), $values );
		}
		return $meta;
	}

	/**
	 * Export taxonomy names.
	 *
	 * @param int $post_id Post ID.
	 * @return array[]
	 */
	private function export_post_terms( $post_id ) {
		$terms = array();
		foreach ( get_object_taxonomies( get_post_type( $post_id ) ) as $taxonomy ) {
			$names = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) && $names ) {
				$terms[ $taxonomy ] = $names;
			}
		}
		return $terms;
	}

	/**
	 * Collect all post IDs in a course tree.
	 *
	 * @param array $course Course export.
	 * @param array $ids    IDs by reference.
	 * @return void
	 */
	private function collect_course_ids( $course, &$ids ) {
		if ( ! is_array( $course ) ) {
			return;
		}
		$this->collect_numeric_ids( isset( $course['id'] ) ? $course['id'] : 0, $ids );
		foreach ( array( 'access_plans', 'sections' ) as $key ) {
			foreach ( isset( $course[ $key ] ) && is_array( $course[ $key ] ) ? $course[ $key ] : array() as $item ) {
				$this->collect_nested_content_ids( $item, $ids );
			}
		}
	}

	/**
	 * Collect nested section, lesson, quiz and question IDs.
	 *
	 * @param array $item Content item.
	 * @param array $ids  IDs by reference.
	 * @return void
	 */
	private function collect_nested_content_ids( $item, &$ids ) {
		if ( ! is_array( $item ) ) {
			return;
		}
		$this->collect_numeric_ids( isset( $item['id'] ) ? $item['id'] : 0, $ids );
		foreach ( array( 'lessons', 'questions', 'quiz' ) as $key ) {
			$value = isset( $item[ $key ] ) ? $item[ $key ] : array();
			if ( 'quiz' === $key && is_array( $value ) && isset( $value['id'] ) ) {
				$this->collect_nested_content_ids( $value, $ids );
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $child ) {
					$this->collect_nested_content_ids( $child, $ids );
				}
			}
		}
	}

	/**
	 * Append one numeric ID.
	 *
	 * @param mixed $id  ID.
	 * @param array $ids IDs by reference.
	 * @return void
	 */
	private function collect_numeric_ids( $id, &$ids ) {
		if ( is_numeric( $id ) && absint( $id ) ) {
			$ids[] = absint( $id );
		}
	}

	/**
	 * Collect users used by LMS roles and content authors/instructors.
	 *
	 * @param array[] $courses Courses.
	 * @param array[] $groups  Access groups.
	 * @return int[]
	 */
	private function collect_export_user_ids( $courses, $groups ) {
		$ids = array();
		$roles = array( 'student', 'vibelms_student', 'vibelms_observer', 'instructor', 'instructors_assistant', 'lms_manager' );
		foreach ( get_users( array( 'role__in' => $roles, 'fields' => 'ID', 'number' => -1 ) ) as $user_id ) {
			$ids[] = absint( $user_id );
		}
		$this->collect_user_ids_from_content( array_merge( $courses, $groups ), $ids );
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Include users referenced only by progress or quiz-attempt tables.
	 *
	 * @param int[] $content_ids LMS content IDs.
	 * @param int[] $ids         User IDs by reference.
	 * @return void
	 */
	private function collect_database_user_ids( $content_ids, &$ids ) {
		global $wpdb;
		if ( empty( $content_ids ) ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $content_ids ), '%d' ) );
		$user_postmeta = $wpdb->prefix . 'lifterlms_user_postmeta';
		if ( $this->table_exists( $user_postmeta ) ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$user_postmeta} WHERE post_id IN ({$placeholders})", $content_ids ) );
			$ids  = array_merge( $ids, array_map( 'absint', $rows ) );
		}
		$attempts = $wpdb->prefix . 'lifterlms_quiz_attempts';
		if ( $this->table_exists( $attempts ) ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT student_id FROM {$attempts} WHERE quiz_id IN ({$placeholders})", $content_ids ) );
			$ids  = array_merge( $ids, array_map( 'absint', $rows ) );
		}
		$report_attempts = $wpdb->prefix . 'vibelms_attempts';
		if ( $this->table_exists( $report_attempts ) ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$report_attempts} WHERE quiz_id IN ({$placeholders})", $content_ids ) );
			$ids  = array_merge( $ids, array_map( 'absint', $rows ) );
		}
	}

	/**
	 * Collect author/instructor IDs from model arrays.
	 *
	 * @param mixed $value Content.
	 * @param array $ids   IDs by reference.
	 * @return void
	 */
	private function collect_user_ids_from_content( $value, &$ids ) {
		if ( ! is_array( $value ) ) {
			return;
		}
		foreach ( $value as $key => $item ) {
			if ( 'post_author' === $key && is_numeric( $item ) ) {
				$ids[] = absint( $item );
			}
			if ( in_array( $key, array( 'author', 'instructors' ), true ) ) {
				foreach ( is_array( $item ) && isset( $item['id'] ) ? array( $item ) : (array) $item as $person ) {
					if ( is_array( $person ) && ! empty( $person['id'] ) ) {
						$ids[] = absint( $person['id'] );
					}
				}
			}
			$this->collect_user_ids_from_content( $item, $ids );
		}
	}

	/**
	 * Export users without passwords or authentication secrets.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return array[]
	 */
	private function export_users( $user_ids ) {
		$users = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			$users[] = array(
				'id'            => absint( $user->ID ),
				'user_login'    => $user->user_login,
				'user_nicename' => $user->user_nicename,
				'user_email'    => $user->user_email,
				'display_name'  => $user->display_name,
				'user_url'      => $user->user_url,
				'user_registered' => $user->user_registered,
				'first_name'    => $user->first_name,
				'last_name'     => $user->last_name,
				'nickname'      => $user->nickname,
				'description'   => $user->description,
				'roles'         => array_values( $user->roles ),
				'meta'          => $this->export_user_meta( $user->ID ),
			);
		}
		return $users;
	}

	/**
	 * Export safe user meta.
	 *
	 * @param int $user_id User ID.
	 * @return array[]
	 */
	private function export_user_meta( $user_id ) {
		$meta = array();
		foreach ( get_user_meta( $user_id ) as $key => $values ) {
			if ( preg_match( '/pass|session|token|secret|activation|application_password|auth_cookie|reset/i', $key ) ) {
				continue;
			}
			$meta[ $key ] = array_map( array( $this, 'safe_meta_value' ), $values );
		}
		return $meta;
	}

	/**
	 * Export LifterLMS user-postmeta rows.
	 *
	 * @param int[] $user_ids    User IDs.
	 * @param int[] $content_ids Post IDs.
	 * @return array[]
	 */
	private function export_enrollments( $user_ids, $content_ids ) {
		global $wpdb;
		if ( empty( $user_ids ) || empty( $content_ids ) ) {
			return array();
		}
		$table = $wpdb->prefix . 'lifterlms_user_postmeta';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}
		$user_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$post_placeholders = implode( ',', array_fill( 0, count( $content_ids ), '%d' ) );
		$sql = "SELECT user_id, post_id, meta_key, meta_value, updated_date FROM {$table} WHERE user_id IN ({$user_placeholders}) AND post_id IN ({$post_placeholders}) ORDER BY meta_id ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $user_ids, $content_ids ) ), ARRAY_A );
	}

	/**
	 * Export core quiz attempts.
	 *
	 * @param int[] $user_ids    User IDs.
	 * @param int[] $content_ids Content IDs.
	 * @return array[]
	 */
	private function export_quiz_attempts( $user_ids, $content_ids ) {
		global $wpdb;
		if ( empty( $user_ids ) || empty( $content_ids ) ) {
			return array();
		}
		$table = $wpdb->prefix . 'lifterlms_quiz_attempts';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}
		$user_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$post_placeholders = implode( ',', array_fill( 0, count( $content_ids ), '%d' ) );
		$sql = "SELECT id, student_id, quiz_id, lesson_id, start_date, update_date, end_date, status, attempt, grade, can_be_resumed, current_question_id, questions FROM {$table} WHERE student_id IN ({$user_placeholders}) AND quiz_id IN ({$post_placeholders}) ORDER BY id ASC";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $user_ids, $content_ids ) ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['source_id'] = absint( $row['id'] );
			unset( $row['id'] );
			$row['questions'] = $this->decode_db_value( $row['questions'] );
		}
		return $rows;
	}

	/**
	 * Export VibeLMS report rows.
	 *
	 * @param int[] $user_ids    User IDs.
	 * @param int[] $content_ids Content IDs.
	 * @return array[]
	 */
	private function export_vibelms_attempts( $user_ids, $content_ids ) {
		global $wpdb;
		if ( empty( $user_ids ) || empty( $content_ids ) ) {
			return array();
		}
		$table = $wpdb->prefix . 'vibelms_attempts';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}
		$user_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$post_placeholders = implode( ',', array_fill( 0, count( $content_ids ), '%d' ) );
		$sql = "SELECT * FROM {$table} WHERE user_id IN ({$user_placeholders}) AND quiz_id IN ({$post_placeholders}) ORDER BY id ASC";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $user_ids, $content_ids ) ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['source_id'] = absint( $row['id'] );
			unset( $row['id'] );
		}
		return $rows;
	}

	/**
	 * Export local attachment files referenced by LMS content.
	 *
	 * @param int[]   $content_ids Content IDs.
	 * @param array[] $models      Exported model arrays.
	 * @return array[]
	 */
	private function export_media( $content_ids, $models ) {
		$ids = array();
		foreach ( get_posts( array( 'post_type' => 'attachment', 'post_parent__in' => $content_ids, 'post_status' => 'any', 'numberposts' => -1 ) ) as $attachment ) {
			$ids[] = absint( $attachment->ID );
		}
		$this->collect_attachment_ids_from_values( $models, $ids );
		$ids    = array_values( array_unique( array_filter( $ids ) ) );
		$uploads = wp_upload_dir();
		$media   = array();
		foreach ( $ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			$path       = get_attached_file( $attachment_id );
			if ( ! $attachment || ! $path || ! is_readable( $path ) ) {
				continue;
			}
			$relative = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $path ), '/\\' );
			if ( 0 === strpos( wp_normalize_path( $path ), wp_normalize_path( trailingslashit( $uploads['basedir'] ) ) ) && $relative ) {
				$media[] = array(
					'source_id'  => $attachment_id,
					'source_url' => wp_get_attachment_url( $attachment_id ),
					'parent_id'  => absint( $attachment->post_parent ),
					'post_title' => $attachment->post_title,
					'post_excerpt' => $attachment->post_excerpt,
					'post_mime_type' => $attachment->post_mime_type,
					'entry'      => 'media/' . $attachment_id . '-' . sanitize_file_name( basename( $path ) ),
					'path'       => $path,
				);
			}
		}
		return $media;
	}

	/**
	 * Remove local filesystem paths before writing media metadata to JSON.
	 *
	 * @param array[] $media Media records.
	 * @return array[]
	 */
	private function export_media_records( $media ) {
		$records = array();
		foreach ( $media as $item ) {
			$records[] = array(
				'source_id'      => absint( isset( $item['source_id'] ) ? $item['source_id'] : 0 ),
				'source_url'     => isset( $item['source_url'] ) ? $item['source_url'] : '',
				'parent_id'      => absint( isset( $item['parent_id'] ) ? $item['parent_id'] : 0 ),
				'post_title'     => isset( $item['post_title'] ) ? $item['post_title'] : '',
				'post_excerpt'   => isset( $item['post_excerpt'] ) ? $item['post_excerpt'] : '',
				'post_mime_type' => isset( $item['post_mime_type'] ) ? $item['post_mime_type'] : '',
				'entry'          => isset( $item['entry'] ) ? $item['entry'] : '',
			);
		}
		return $records;
	}

	/**
	 * Find attachments in URLs and featured-image fields.
	 *
	 * @param mixed $value Values.
	 * @param array $ids   Attachment IDs by reference.
	 * @return void
	 */
	private function collect_attachment_ids_from_values( $value, &$ids ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->collect_attachment_ids_from_values( $item, $ids );
			}
			return;
		}
		if ( ! is_string( $value ) || false === strpos( $value, '://' ) ) {
			return;
		}
		$matches = array();
		preg_match_all( '~https?://[^\s"\'<>]+~', $value, $matches );
		foreach ( $matches[0] as $url ) {
			$attachment_id = attachment_url_to_postid( trim( $url, "\\t\\n\\r\\0\\x0B.,;)]}" ) );
			if ( $attachment_id ) {
				$ids[] = absint( $attachment_id );
			}
		}
	}

	/**
	 * Import user profiles and return source-to-destination IDs.
	 *
	 * @param array[] $users  Users.
	 * @param array   $maps   User map by reference.
	 * @param array   $stats  Stats by reference.
	 * @param array   $errors Errors by reference.
	 * @return void
	 */
	private function import_users( $users, &$maps, &$stats, &$errors ) {
		foreach ( is_array( $users ) ? $users : array() as $raw ) {
			$source_id = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
			$email     = isset( $raw['user_email'] ) ? sanitize_email( $raw['user_email'] ) : '';
			$is_new_user = false;
			if ( ! $source_id || ! is_email( $email ) ) {
				$this->add_error( $errors, __( 'Пользователь пропущен: некорректный email.', 'lifterlms' ) );
				continue;
			}
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				$user_id = absint( $existing->ID );
				if ( ! in_array( 'administrator', (array) $existing->roles, true ) ) {
					wp_update_user( array( 'ID' => $user_id, 'display_name' => sanitize_text_field( isset( $raw['display_name'] ) ? $raw['display_name'] : '' ), 'first_name' => sanitize_text_field( isset( $raw['first_name'] ) ? $raw['first_name'] : '' ), 'last_name' => sanitize_text_field( isset( $raw['last_name'] ) ? $raw['last_name'] : '' ) ) );
				}
				$stats['users_reused'] = isset( $stats['users_reused'] ) ? $stats['users_reused'] + 1 : 1;
			} else {
				$login = sanitize_user( isset( $raw['user_login'] ) ? $raw['user_login'] : '', true );
				if ( ! $login ) {
					$login = 'vibelms_user_' . $source_id;
				}
				$base = $login;
				$index = 1;
				while ( username_exists( $login ) ) {
					$login = $base . '_' . $index++;
				}
				$user_id = wp_insert_user(
					array(
						'user_login'    => $login,
						'user_pass'     => wp_generate_password( 32, true, true ),
						'user_email'    => $email,
						'display_name'  => sanitize_text_field( isset( $raw['display_name'] ) ? $raw['display_name'] : $login ),
						'user_url'      => esc_url_raw( isset( $raw['user_url'] ) ? $raw['user_url'] : '' ),
						'first_name'    => sanitize_text_field( isset( $raw['first_name'] ) ? $raw['first_name'] : '' ),
						'last_name'     => sanitize_text_field( isset( $raw['last_name'] ) ? $raw['last_name'] : '' ),
						'description'   => sanitize_textarea_field( isset( $raw['description'] ) ? $raw['description'] : '' ),
						'role'          => $this->safe_import_role( isset( $raw['roles'] ) ? $raw['roles'] : array() ),
					)
				);
				if ( is_wp_error( $user_id ) ) {
					$this->add_error( $errors, sprintf( __( 'Не удалось создать пользователя %s: %s', 'lifterlms' ), $email, $user_id->get_error_message() ) );
					continue;
				}
				$is_new_user = true;
				$stats['users_created'] = isset( $stats['users_created'] ) ? $stats['users_created'] + 1 : 1;
			}
			$maps[ $source_id ] = $user_id;
			if ( $is_new_user ) {
				update_user_meta( $user_id, 'vibelms_transfer_needs_password_reset', 1 );
			}
			foreach ( isset( $raw['meta'] ) && is_array( $raw['meta'] ) ? $raw['meta'] : array() as $key => $values ) {
				if ( ! is_string( $key ) || preg_match( '/pass|session|token|secret|activation|application_password|auth_cookie|reset/i', $key ) ) {
					continue;
				}
				foreach ( (array) $values as $value ) {
					update_user_meta( $user_id, $key, $value );
				}
			}
		}
	}

	/**
	 * Import local media from the archive.
	 *
	 * @param ZipArchive $zip    Archive.
	 * @param array[]    $media  Media records.
	 * @param array       $maps  Media map by reference.
	 * @param array       $stats Stats by reference.
	 * @param array       $errors Errors by reference.
	 * @param int         $offset Batch offset.
	 * @param int         $limit  Batch size.
	 * @return void
	 */
	private function import_media( $zip, &$media, &$maps, &$stats, &$errors, $offset = 0, $limit = 0 ) {
		if ( empty( $media ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$start = max( 0, absint( $offset ) );
		$end   = count( $media );
		if ( $limit > 0 ) {
			$end = min( $end, $start + absint( $limit ) );
		}
		for ( $index = $start; $index < $end; $index++ ) {
			$raw =& $media[ $index ];
			$source_id = isset( $raw['source_id'] ) ? absint( $raw['source_id'] ) : 0;
			$entry     = isset( $raw['entry'] ) ? sanitize_file_name( basename( $raw['entry'] ) ) : '';
			if ( ! $source_id || ! $entry || false === strpos( $raw['entry'], 'media/' ) ) {
				continue;
			}
			if ( 'skip' === $this->duplicate_mode && isset( $maps[ $source_id ] ) ) {
				$raw['destination_id']  = absint( $maps[ $source_id ] );
				$raw['destination_url'] = wp_get_attachment_url( $maps[ $source_id ] );
				$stats['media_skipped'] = isset( $stats['media_skipped'] ) ? $stats['media_skipped'] + 1 : 1;
				continue;
			}
			$stream = $zip->getStream( $raw['entry'] );
			if ( ! $stream ) {
				$this->add_error( $errors, sprintf( __( 'Медиафайл #%d отсутствует в архиве.', 'lifterlms' ), $source_id ) );
				continue;
			}
			$tmp = wp_tempnam( 'vibelms-media' );
			if ( ! $tmp ) {
				fclose( $stream );
				$this->add_error( $errors, __( 'Не удалось создать временный файл для медиа.', 'lifterlms' ) );
				continue;
			}
			$target = fopen( $tmp, 'wb' );
			stream_copy_to_stream( $stream, $target );
			fclose( $target );
			fclose( $stream );
			$file = wp_handle_sideload( array( 'name' => $entry, 'tmp_name' => $tmp, 'type' => sanitize_mime_type( isset( $raw['post_mime_type'] ) ? $raw['post_mime_type'] : '' ), 'error' => 0, 'size' => filesize( $tmp ) ), array( 'test_form' => false ) );
			if ( isset( $file['error'] ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$this->add_error( $errors, sprintf( __( 'Не удалось импортировать медиа #%d: %s', 'lifterlms' ), $source_id, $file['error'] ) );
				continue;
			}
			$attachment_id = wp_insert_attachment( array( 'post_title' => sanitize_text_field( isset( $raw['post_title'] ) ? $raw['post_title'] : $entry ), 'post_excerpt' => sanitize_textarea_field( isset( $raw['post_excerpt'] ) ? $raw['post_excerpt'] : '' ), 'post_status' => 'inherit', 'post_mime_type' => $file['type'] ), $file['file'], 0, true );
			if ( is_wp_error( $attachment_id ) ) {
				$this->add_error( $errors, sprintf( __( 'Не удалось создать вложение #%d: %s', 'lifterlms' ), $source_id, $attachment_id->get_error_message() ) );
				continue;
			}
			$maps[ $source_id ] = absint( $attachment_id );
			$raw['destination_id']  = absint( $attachment_id );
			$raw['destination_url'] = wp_get_attachment_url( $attachment_id );
			if ( $this->source_site ) {
				update_post_meta( $attachment_id, self::SOURCE_SITE_META, $this->source_site );
				update_post_meta( $attachment_id, self::SOURCE_ID_META, $source_id );
			}
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file['file'] );
			if ( $metadata ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
			$stats['media'] = isset( $stats['media'] ) ? $stats['media'] + 1 : 1;
			unset( $raw );
		}
	}

	/**
	 * Prepare course arrays after media has been imported.
	 *
	 * @param array[] $courses Courses.
	 * @param array   $media   Media map.
	 * @return array[]
	 */
	private function prepare_course_data_for_import( $courses, $media ) {
		$prepared = array();
		foreach ( is_array( $courses ) ? $courses : array() as $course ) {
			$this->remember_featured_images( $course, $media );
			$prepared[] = $this->replace_media_urls( $course, $media );
		}
		return $prepared;
	}

	/**
	 * Replace source media URLs and remove URL-based sideload instructions.
	 *
	 * @param mixed $value      Value.
	 * @param array $media      Media map.
	 * @return mixed
	 */
	private function replace_media_urls( $value, $media ) {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				if ( '_extras' === $key && is_array( $item ) ) {
					$item['images'] = array();
				}
				if ( 'featured_image' === $key ) {
					continue;
				}
				$result[ $key ] = $this->replace_media_urls( $item, $media );
			}
			return $result;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		foreach ( $media as $item ) {
			if ( ! empty( $item['source_url'] ) && ! empty( $item['destination_url'] ) ) {
				$value = str_replace( $item['source_url'], $item['destination_url'], $value );
			}
		}
		return $value;
	}

	/**
	 * Remember featured images before the source URL is removed from model data.
	 *
	 * @param mixed   $value Model data.
	 * @param array[] $media Media records.
	 * @return void
	 */
	private function remember_featured_images( $value, $media ) {
		if ( ! is_array( $value ) ) {
			return;
		}
		if ( ! empty( $value['id'] ) && ! empty( $value['featured_image'] ) ) {
			foreach ( (array) $media as $item ) {
				$item_source_id = absint( isset( $item['source_id'] ) ? $item['source_id'] : 0 );
				$source_url = isset( $item['source_url'] ) ? $item['source_url'] : '';
				if ( $source_url && $source_url === $value['featured_image'] && $item_source_id && ! empty( $item['destination_id'] ) ) {
					$this->featured_media[ absint( $value['id'] ) ] = absint( $item['destination_id'] );
				}
			}
		}
		foreach ( $value as $item ) {
			$this->remember_featured_images( $item, $media );
		}
	}

	/**
	 * Import courses and their nested lessons/quizzes/questions.
	 *
	 * @param array[] $courses Courses.
	 * @param array   $posts   Post map by reference.
	 * @param array   $stats   Stats by reference.
	 * @param array   $errors  Errors by reference.
	 * @return void
	 */
	private function import_courses( $courses, &$posts, &$stats, &$errors ) {
		$import_courses = array();
		foreach ( is_array( $courses ) ? $courses : array() as $course ) {
			$source_id = absint( isset( $course['id'] ) ? $course['id'] : 0 );
			if ( 'skip' === $this->duplicate_mode && $source_id && isset( $posts[ $source_id ] ) ) {
				$stats['courses_skipped'] = isset( $stats['courses_skipped'] ) ? $stats['courses_skipped'] + 1 : 1;
				continue;
			}
			$import_courses[] = $course;
		}
		if ( empty( $import_courses ) ) {
			return;
		}
		$generator = new LLMS_Generator(
			array(
				'_generator' => 'LifterLMS/BulkCourseGenerator',
				'_source'    => '',
				'_version'   => defined( 'VIBELMS_VERSION' ) ? VIBELMS_VERSION : '',
			'courses'    => $import_courses,
			)
		);
		$generator->set_generator( 'LifterLMS/BulkCourseGenerator' );
		$generator->generate();
		if ( $generator->is_error() ) {
			foreach ( $generator->error->get_error_messages() as $message ) {
				$this->add_error( $errors, $message );
			}
			return;
		}
		foreach ( $generator->get_generated_content() as $type => $ids ) {
			if ( in_array( $type, array( 'course', 'section', 'lesson', 'quiz', 'question', 'access_plan' ), true ) ) {
				$this->map_generated_ids( $ids, $posts );
			}
		}
		$stats['courses'] = ( isset( $stats['courses'] ) ? $stats['courses'] : 0 ) + count( $generator->get_generated_courses() );
		$stats['lessons'] = ( isset( $stats['lessons'] ) ? $stats['lessons'] : 0 ) + ( isset( $generator->get_generated_content()['lesson'] ) ? count( $generator->get_generated_content()['lesson'] ) : 0 );
		$stats['quizzes'] = ( isset( $stats['quizzes'] ) ? $stats['quizzes'] : 0 ) + ( isset( $generator->get_generated_content()['quiz'] ) ? count( $generator->get_generated_content()['quiz'] ) : 0 );
		$stats['questions'] = ( isset( $stats['questions'] ) ? $stats['questions'] : 0 ) + ( isset( $generator->get_generated_content()['question'] ) ? count( $generator->get_generated_content()['question'] ) : 0 );
	}

	/**
	 * Map generated IDs using the source marker written by the generator.
	 *
	 * @param int[] $ids   New IDs.
	 * @param array $posts Map by reference.
	 * @return void
	 */
	private function map_generated_ids( $ids, &$posts ) {
		foreach ( (array) $ids as $new_id ) {
			$old_id = absint( get_post_meta( $new_id, '_llms_generated_from_id', true ) );
			if ( $old_id ) {
				$posts[ $old_id ] = absint( $new_id );
			}
		}
	}

	/**
	 * Import access groups after courses so auto-enroll IDs can be mapped.
	 *
	 * @param array[] $groups Groups.
	 * @param array   $maps   ID maps by reference.
	 * @param array   $stats  Stats by reference.
	 * @param array   $errors Errors by reference.
	 * @return void
	 */
	private function import_memberships( $groups, &$maps, &$stats, &$errors ) {
		foreach ( is_array( $groups ) ? $groups : array() as $raw ) {
			$source_id = absint( isset( $raw['id'] ) ? $raw['id'] : 0 );
			if ( ! $source_id ) {
				continue;
			}
			if ( 'skip' === $this->duplicate_mode && isset( $maps['posts'][ $source_id ] ) ) {
				$stats['memberships_skipped'] = isset( $stats['memberships_skipped'] ) ? $stats['memberships_skipped'] + 1 : 1;
				continue;
			}
			$post = $this->insert_model_post( $raw, 'llms_membership', $maps );
			if ( is_wp_error( $post ) ) {
				$this->add_error( $errors, $post->get_error_message() );
				continue;
			}
			$membership = new LLMS_Membership( $post );
			$properties = $membership->get_properties();
			foreach ( $properties as $key => $type ) {
				if ( ! array_key_exists( $key, $raw ) ) {
					continue;
				}
				$value = $raw[ $key ];
				if ( 'auto_enroll' === $key ) {
					$value = $this->map_ids( (array) $value, $maps['posts'] );
				}
				if ( 'instructors' === $key ) {
					$value = $this->map_instructors( $value, $maps['users'] );
				}
				$membership->set( $key, $value );
			}
			$this->set_terms_from_export( $post, $raw, 'membership_cat', 'categories' );
			$this->set_terms_from_export( $post, $raw, 'membership_tag', 'tags' );
			$maps['posts'][ $source_id ] = absint( $post );
			$stats['memberships'] = isset( $stats['memberships'] ) ? $stats['memberships'] + 1 : 1;
		}
	}

	/**
	 * Import certificate templates.
	 *
	 * @param array[] $certificates Certificates.
	 * @param array   $maps         ID maps by reference.
	 * @param array   $stats        Stats by reference.
	 * @param array   $errors       Errors by reference.
	 * @return void
	 */
	private function import_certificates( $certificates, &$maps, $media, &$stats, &$errors ) {
		foreach ( is_array( $certificates ) ? $certificates : array() as $raw ) {
			$source_id = absint( isset( $raw['id'] ) ? $raw['id'] : 0 );
			$post_data = isset( $raw['post'] ) && is_array( $raw['post'] ) ? $raw['post'] : array();
			if ( ! $source_id || empty( $post_data ) ) {
				continue;
			}
			if ( 'skip' === $this->duplicate_mode && isset( $maps['posts'][ $source_id ] ) ) {
				$stats['certificates_skipped'] = isset( $stats['certificates_skipped'] ) ? $stats['certificates_skipped'] + 1 : 1;
				continue;
			}
			$post_data['post_content'] = $this->replace_media_urls( isset( $post_data['post_content'] ) ? $post_data['post_content'] : '', $media );
			$post_data['post_type'] = 'llms_certificate';
			$post_data['post_author'] = $this->resolve_author_id( isset( $post_data['post_author'] ) ? $post_data['post_author'] : 0, $maps['users'] );
			$post_id = wp_insert_post( $post_data, true );
			if ( is_wp_error( $post_id ) ) {
				$this->add_error( $errors, $post_id->get_error_message() );
				continue;
			}
			$this->import_post_meta( $post_id, isset( $raw['meta'] ) ? $raw['meta'] : array(), $maps );
			$this->import_post_terms( $post_id, isset( $raw['taxonomies'] ) ? $raw['taxonomies'] : array() );
			$maps['posts'][ $source_id ] = absint( $post_id );
			$stats['certificates'] = isset( $stats['certificates'] ) ? $stats['certificates'] + 1 : 1;
		}
	}

	/**
	 * Import a generated model post using WordPress APIs.
	 *
	 * @param array $raw  Model array.
	 * @param string $type Post type.
	 * @param array $maps ID maps.
	 * @return int|WP_Error
	 */
	private function insert_model_post( $raw, $type, $maps ) {
		$post = array(
			'post_type'      => $type,
			'post_title'     => sanitize_text_field( isset( $raw['title'] ) ? $raw['title'] : '' ),
			'post_content'   => wp_kses_post( isset( $raw['content'] ) ? $raw['content'] : '' ),
			'post_excerpt'   => wp_kses_post( isset( $raw['excerpt'] ) ? $raw['excerpt'] : '' ),
			'post_status'    => sanitize_key( isset( $raw['status'] ) ? $raw['status'] : 'publish' ),
			'post_author'    => $this->resolve_author_id( isset( $raw['author'] ) && is_array( $raw['author'] ) ? ( isset( $raw['author']['id'] ) ? $raw['author']['id'] : 0 ) : 0, $maps['users'] ),
			'post_date'      => sanitize_text_field( isset( $raw['date'] ) ? $raw['date'] : '' ),
			'post_modified'   => sanitize_text_field( isset( $raw['modified'] ) ? $raw['modified'] : '' ),
		);
		$post_id = wp_insert_post( $post, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_llms_generated_from_id', absint( isset( $raw['id'] ) ? $raw['id'] : 0 ) );
		return $post_id;
	}

	/**
	 * Import settings after certificate IDs have been remapped.
	 *
	 * @param array $settings Settings.
	 * @param array $posts    Post map.
	 * @param array $stats    Stats by reference.
	 * @param array $errors   Errors by reference.
	 * @return void
	 */
	private function import_settings( $settings, $posts, &$stats, &$errors ) {
		foreach ( is_array( $settings ) ? $settings : array() as $key => $value ) {
			if ( ! in_array( $key, $this->option_names, true ) ) {
				continue;
			}
			if ( 'vibelms_certificate_template_id' === $key ) {
				$value = isset( $posts[ absint( $value ) ] ) ? $posts[ absint( $value ) ] : 0;
			}
			update_option( $key, $value, false );
			$stats['settings'] = isset( $stats['settings'] ) ? $stats['settings'] + 1 : 1;
		}
	}

	/**
	 * Import LMS user-postmeta and remap users/posts.
	 *
	 * @param array[] $rows  Rows.
	 * @param array   $maps  ID maps.
	 * @param array   $stats Stats by reference.
	 * @param array   $errors Errors by reference.
	 * @return void
	 */
	private function import_enrollments( $rows, $maps, &$stats, &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lifterlms_user_postmeta';
		if ( ! $this->table_exists( $table ) ) {
			return;
		}
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$user_id = isset( $maps['users'][ absint( $row['user_id'] ) ] ) ? $maps['users'][ absint( $row['user_id'] ) ] : 0;
			$post_id = isset( $maps['posts'][ absint( $row['post_id'] ) ] ) ? $maps['posts'][ absint( $row['post_id'] ) ] : 0;
			if ( ! $user_id || ! $post_id || empty( $row['meta_key'] ) ) {
				continue;
			}
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$table} WHERE user_id = %d AND post_id = %d AND meta_key = %s AND meta_value = %s AND updated_date = %s LIMIT 1", $user_id, $post_id, $row['meta_key'], $row['meta_value'], $row['updated_date'] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert( $table, array( 'user_id' => $user_id, 'post_id' => $post_id, 'meta_key' => sanitize_key( $row['meta_key'] ), 'meta_value' => maybe_serialize( $row['meta_value'] ), 'updated_date' => sanitize_text_field( $row['updated_date'] ) ), array( '%d', '%d', '%s', '%s', '%s' ) );
			$stats['enrollments'] = isset( $stats['enrollments'] ) ? $stats['enrollments'] + 1 : 1;
		}
	}

	/**
	 * Import core quiz attempts and map their new IDs.
	 *
	 * @param array[] $rows  Attempts.
	 * @param array   $maps  ID maps by reference.
	 * @param array   $stats Stats by reference.
	 * @param array   $errors Errors by reference.
	 * @return void
	 */
	private function import_quiz_attempts( $rows, &$maps, &$stats, &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lifterlms_quiz_attempts';
		if ( ! $this->table_exists( $table ) ) {
			return;
		}
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$student_id = isset( $maps['users'][ absint( $row['student_id'] ) ] ) ? $maps['users'][ absint( $row['student_id'] ) ] : 0;
			$quiz_id    = isset( $maps['posts'][ absint( $row['quiz_id'] ) ] ) ? $maps['posts'][ absint( $row['quiz_id'] ) ] : 0;
			if ( ! $student_id || ! $quiz_id ) {
				continue;
			}
			$data = array(
				'student_id' => $student_id,
				'quiz_id' => $quiz_id,
				'lesson_id' => isset( $maps['posts'][ absint( $row['lesson_id'] ) ] ) ? $maps['posts'][ absint( $row['lesson_id'] ) ] : 0,
				'start_date' => sanitize_text_field( $row['start_date'] ),
				'update_date' => sanitize_text_field( $row['update_date'] ),
				'end_date' => sanitize_text_field( $row['end_date'] ),
				'status' => sanitize_key( $row['status'] ),
				'attempt' => absint( $row['attempt'] ),
				'grade' => (float) $row['grade'],
				'can_be_resumed' => absint( $row['can_be_resumed'] ),
				'current_question_id' => isset( $maps['posts'][ absint( $row['current_question_id'] ) ] ) ? $maps['posts'][ absint( $row['current_question_id'] ) ] : 0,
				'questions' => maybe_serialize( $this->remap_attempt_questions( $row['questions'], $maps['posts'] ) ),
			);
			$wpdb->insert( $table, $data, array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%s' ) );
			if ( $wpdb->insert_id ) {
				$maps['attempts'][ absint( $row['source_id'] ) ] = absint( $wpdb->insert_id );
				$stats['quiz_attempts'] = isset( $stats['quiz_attempts'] ) ? $stats['quiz_attempts'] + 1 : 1;
			}
		}
	}

	/**
	 * Import VibeLMS report rows.
	 *
	 * @param array[] $rows  Rows.
	 * @param array   $maps  ID maps.
	 * @param array   $stats Stats by reference.
	 * @param array   $errors Errors by reference.
	 * @return void
	 */
	private function import_vibelms_attempts( $rows, $maps, &$stats, &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vibelms_attempts';
		if ( ! $this->table_exists( $table ) ) {
			return;
		}
		$columns = array( 'user_id', 'quiz_id', 'attempt_id', 'email', 'company', 'employee_name', 'region', 'station', 'language', 'attempt_number', 'question_count', 'correct_count', 'grade', 'status', 'certificate_id', 'started_at', 'completed_at' );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$user_id = isset( $maps['users'][ absint( $row['user_id'] ) ] ) ? $maps['users'][ absint( $row['user_id'] ) ] : 0;
			$quiz_id = isset( $maps['posts'][ absint( $row['quiz_id'] ) ] ) ? $maps['posts'][ absint( $row['quiz_id'] ) ] : 0;
			$attempt_id = isset( $maps['attempts'][ absint( $row['attempt_id'] ) ] ) ? $maps['attempts'][ absint( $row['attempt_id'] ) ] : 0;
			if ( ! $user_id || ! $quiz_id || ! $attempt_id ) {
				continue;
			}
			$data = array();
			foreach ( $columns as $column ) {
				$data[ $column ] = isset( $row[ $column ] ) ? $row[ $column ] : '';
			}
			$data['user_id'] = $user_id;
			$data['quiz_id'] = $quiz_id;
			$data['attempt_id'] = $attempt_id;
			$data['certificate_id'] = isset( $maps['posts'][ absint( $row['certificate_id'] ) ] ) ? $maps['posts'][ absint( $row['certificate_id'] ) ] : 0;
			$wpdb->replace( $table, $data, array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%d', '%s', '%s' ) );
			$stats['vibelms_attempts'] = isset( $stats['vibelms_attempts'] ) ? $stats['vibelms_attempts'] + 1 : 1;
		}
	}

	/**
	 * Apply featured images after the generator has created posts.
	 *
	 * @param array   $posts    Post map.
	 * @param array[] $courses  Course data.
	 * @param array   $media    Media map.
	 * @return void
	 */
	private function apply_featured_images( $posts ) {
		foreach ( $this->featured_media as $source_post_id => $attachment_id ) {
			if ( isset( $posts[ $source_post_id ] ) && $attachment_id ) {
				set_post_thumbnail( $posts[ $source_post_id ], $attachment_id );
			}
		}
	}

	/**
	 * Add imported media to the new content post parents.
	 *
	 * @param array[] $media   Media records.
	 * @param array   $media_map Media map.
	 * @param array   $posts   Post map.
	 * @return void
	 */
	private function repair_media_parents( $media, $media_map, $posts ) {
		foreach ( is_array( $media ) ? $media : array() as $item ) {
			$source_media = absint( isset( $item['source_id'] ) ? $item['source_id'] : 0 );
			$source_parent = absint( isset( $item['parent_id'] ) ? $item['parent_id'] : 0 );
			if ( $source_media && $source_parent && isset( $media_map[ $source_media ], $posts[ $source_parent ] ) ) {
				wp_update_post( array( 'ID' => $media_map[ $source_media ], 'post_parent' => $posts[ $source_parent ] ) );
			}
		}
	}

	/**
	 * Insert a post's meta safely.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Meta data.
	 * @param array $maps    ID maps.
	 * @return void
	 */
	private function import_post_meta( $post_id, $meta, $maps ) {
		foreach ( is_array( $meta ) ? $meta : array() as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug', '_thumbnail_id' ), true ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				$value = $this->remap_ids( $value, $maps['posts'] );
				add_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}
	}

	/**
	 * Import taxonomy names.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $terms   Taxonomies.
	 * @return void
	 */
	private function import_post_terms( $post_id, $terms ) {
		foreach ( is_array( $terms ) ? $terms : array() as $taxonomy => $names ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				wp_set_post_terms( $post_id, array_map( 'sanitize_text_field', (array) $names ), $taxonomy, false );
			}
		}
	}

	/**
	 * Set model-specific taxonomy terms.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $raw     Model data.
	 * @param string $taxonomy Taxonomy.
	 * @param string $key     Model key.
	 * @return void
	 */
	private function set_terms_from_export( $post_id, $raw, $taxonomy, $key ) {
		if ( taxonomy_exists( $taxonomy ) && ! empty( $raw[ $key ] ) ) {
			wp_set_post_terms( $post_id, array_map( 'sanitize_text_field', (array) $raw[ $key ] ), $taxonomy, false );
		}
	}

	/**
	 * Map instructor structures.
	 *
	 * @param mixed $value Instructors.
	 * @param array $users User map.
	 * @return array
	 */
	private function map_instructors( $value, $users ) {
		$result = array();
		foreach ( (array) $value as $instructor ) {
			if ( ! is_array( $instructor ) ) {
				continue;
			}
			if ( isset( $users[ absint( isset( $instructor['id'] ) ? $instructor['id'] : 0 ) ] ) ) {
				$instructor['id'] = $users[ absint( $instructor['id'] ) ];
			}
			$result[] = $instructor;
		}
		return $result;
	}

	/**
	 * Map a simple ID array.
	 *
	 * @param array $ids  IDs.
	 * @param array $map  Map.
	 * @return array
	 */
	private function map_ids( $ids, $map ) {
		$result = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( isset( $map[ $id ] ) ) {
				$result[] = absint( $map[ $id ] );
			}
		}
		return array_values( array_unique( $result ) );
	}

	/**
	 * Resolve a source author ID to a destination user.
	 *
	 * @param int   $source_id Source user ID.
	 * @param array $users     User map.
	 * @return int
	 */
	private function resolve_author_id( $source_id, $users ) {
		return isset( $users[ absint( $source_id ) ] ) ? absint( $users[ absint( $source_id ) ] ) : get_current_user_id();
	}

	/**
	 * Remap known IDs inside nested attempt data.
	 *
	 * @param mixed $value Value.
	 * @param array $map   Post map.
	 * @return mixed
	 */
	private function remap_attempt_questions( $value, $map ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => &$item ) {
			if ( 'id' === $key || 'question_id' === $key || 'quiz_id' === $key || 'lesson_id' === $key ) {
				if ( isset( $map[ absint( $item ) ] ) ) {
					$item = $map[ absint( $item ) ];
				}
			} else {
				$item = $this->remap_attempt_questions( $item, $map );
			}
		}
		return $value;
	}

	/**
	 * Remap known post IDs in arbitrary arrays/serialized values.
	 *
	 * @param mixed $value Value.
	 * @param array $map   Post map.
	 * @return mixed
	 */
	private function remap_ids( $value, $map ) {
		if ( is_string( $value ) && is_serialized( $value ) ) {
			$value = $this->decode_db_value( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => &$item ) {
				if ( in_array( $key, array( 'id', 'post_id', 'product_id', 'course_id', 'lesson_id', 'quiz_id', 'question_id', 'parent_id', 'parent_course', 'parent_section', 'certificate_id' ), true ) && isset( $map[ absint( $item ) ] ) ) {
					$item = $map[ absint( $item ) ];
				} else {
					$item = $this->remap_ids( $item, $map );
				}
			}
		}
		return $value;
	}

	/**
	 * Choose a non-privileged role for a new imported user.
	 *
	 * @param mixed $roles Source roles.
	 * @return string
	 */
	private function safe_import_role( $roles ) {
		$allowed = array( 'vibelms_student', 'student', 'vibelms_observer', 'instructor', 'instructors_assistant', 'lms_manager', 'subscriber' );
		foreach ( (array) $roles as $role ) {
			if ( in_array( $role, $allowed, true ) && get_role( $role ) ) {
				return $role;
			}
		}
		return 'subscriber';
	}

	/**
	 * Read and validate JSON from an archive.
	 *
	 * @param ZipArchive $zip  Archive.
	 * @param string      $name Entry name.
	 * @return mixed|WP_Error
	 */
	private function read_archive_json( $zip, $name ) {
		$contents = $zip->getFromName( $name );
		if ( false === $contents ) {
			return new WP_Error( 'vibelms_transfer_missing_file', sprintf( __( 'В архиве отсутствует файл %s.', 'lifterlms' ), $name ) );
		}
		$data = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'vibelms_transfer_invalid_json', sprintf( __( 'Файл %s содержит некорректный JSON.', 'lifterlms' ), $name ) );
		}
		return $data;
	}

	/**
	 * JSON encode archive data.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function json( $value ) {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Decode a trusted database value without allowing objects.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function decode_db_value( $value ) {
		if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
			return $value;
		}
		$decoded = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		return false === $decoded ? $value : $decoded;
	}

	/**
	 * Normalize values stored in user/post meta.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function safe_meta_value( $value ) {
		$value = $this->decode_db_value( $value );
		return is_object( $value ) ? '' : $value;
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Add one bounded error message.
	 *
	 * @param array  $errors Errors by reference.
	 * @param string $message Message.
	 * @return void
	 */
	private function add_error( &$errors, $message ) {
		if ( count( $errors ) < 100 ) {
			$errors[] = wp_strip_all_tags( (string) $message );
		}
	}

	/**
	 * Authorize admin-post requests.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function authorize_request( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'У вас нет доступа к переносу данных.', 'lifterlms' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Store result and redirect to the admin screen.
	 *
	 * @param string $message Message.
	 * @param array  $stats   Stats.
	 * @param array  $errors  Errors.
	 * @return void
	 */
	private function redirect_with_result( $message, $stats, $errors ) {
		set_transient( self::TRANSIENT_PREFIX . get_current_user_id(), array( 'message' => $message, 'stats' => $stats, 'errors' => $errors ), 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=vibelms-transfer' ) );
		exit;
	}

	/**
	 * Format import/export counts for the admin notice.
	 *
	 * @param array $stats Counts.
	 * @return string
	 */
	private function format_stats( $stats ) {
		$labels = array(
			'courses'             => __( 'курсов', 'lifterlms' ),
			'courses_skipped'     => __( 'курсов пропущено', 'lifterlms' ),
			'lessons'             => __( 'уроков', 'lifterlms' ),
			'quizzes'             => __( 'тестов', 'lifterlms' ),
			'questions'           => __( 'вопросов', 'lifterlms' ),
			'memberships'         => __( 'групп доступа', 'lifterlms' ),
			'memberships_skipped' => __( 'групп пропущено', 'lifterlms' ),
			'certificates'        => __( 'сертификатов', 'lifterlms' ),
			'certificates_skipped' => __( 'сертификатов пропущено', 'lifterlms' ),
			'users_created'       => __( 'пользователей создано', 'lifterlms' ),
			'users_reused'        => __( 'пользователей переиспользовано', 'lifterlms' ),
			'media'               => __( 'медиафайлов', 'lifterlms' ),
			'media_skipped'       => __( 'медиафайлов пропущено', 'lifterlms' ),
			'settings'            => __( 'настроек', 'lifterlms' ),
			'enrollments'         => __( 'зачислений', 'lifterlms' ),
			'quiz_attempts'       => __( 'попыток тестов', 'lifterlms' ),
			'vibelms_attempts'    => __( 'записей журнала', 'lifterlms' ),
		);
		$parts = array();
		foreach ( $stats as $key => $value ) {
			$parts[] = ( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ': ' . absint( $value );
		}
		return implode( ', ', $parts );
	}

	/**
	 * Record transfer diagnostics.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	private function log_transfer( $message, $context ) {
		if ( function_exists( 'llms_vibelms_diagnostics_log' ) ) {
			llms_vibelms_diagnostics_log( 'info', $message, $context );
		}
	}
}

/**
 * Return the VibeLMS transfer singleton.
 *
 * @return LLMS_VibeLMS_Transfer
 */
function llms_vibelms_transfer() {
	static $instance;
	if ( ! $instance ) {
		$instance = new LLMS_VibeLMS_Transfer();
	}
	return $instance;
}
