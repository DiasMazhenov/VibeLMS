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
	 * Constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_page' ), 110 );
			add_action( 'admin_post_vibelms_export_bundle', array( $this, 'export_bundle' ) );
			add_action( 'admin_post_vibelms_import_bundle', array( $this, 'import_bundle' ) );
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

		$result = get_transient( self::TRANSIENT_PREFIX . get_current_user_id() );
		if ( $result ) {
			delete_transient( self::TRANSIENT_PREFIX . get_current_user_id() );
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
				<p><strong><?php esc_html_e( 'Импорт добавляет данные и не удаляет существующие курсы, пользователей или тесты.', 'lifterlms' ); ?></strong></p>
				<p><?php esc_html_e( 'Пользователи сопоставляются по email. Пароли не переносятся: для новых пользователей создаётся случайный пароль, который администратор должен сбросить или отправить пользователю.', 'lifterlms' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="vibelms_import_bundle">
					<?php wp_nonce_field( 'vibelms_import_bundle' ); ?>
					<p><input type="file" name="vibelms_bundle" accept=".zip,application/zip" required></p>
					<?php submit_button( __( 'Импортировать ZIP', 'lifterlms' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
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
	 * Import a complete export archive.
	 *
	 * @return void
	 */
	public function import_bundle() {
		$this->authorize_request( 'vibelms_import_bundle' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->redirect_with_result( __( 'Импорт невозможен: на сервере не включено расширение PHP ZipArchive.', 'lifterlms' ), array(), array() );
		}

		$file = isset( $_FILES['vibelms_bundle'] ) && is_array( $_FILES['vibelms_bundle'] ) ? $_FILES['vibelms_bundle'] : array();
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			$this->redirect_with_result( __( 'ZIP-файл не был загружен.', 'lifterlms' ), array(), array( __( 'Проверьте размер и формат файла.', 'lifterlms' ) ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			$this->redirect_with_result( __( 'Не удалось открыть ZIP-файл.', 'lifterlms' ), array(), array() );
		}

		$manifest = $this->read_archive_json( $zip, 'manifest.json' );
		if ( is_wp_error( $manifest ) || empty( $manifest['format'] ) || self::FORMAT !== $manifest['format'] ) {
			$zip->close();
			$this->redirect_with_result( __( 'Файл не является экспортом VibeLMS.', 'lifterlms' ), array(), array() );
		}
		if ( empty( $manifest['format_version'] ) || self::FORMAT_VERSION !== absint( $manifest['format_version'] ) ) {
			$zip->close();
			$this->redirect_with_result( __( 'Версия формата архива не поддерживается этой версией VibeLMS.', 'lifterlms' ), array(), array() );
		}

		$data = array();
		foreach ( array( 'settings', 'users', 'courses', 'memberships', 'certificates', 'enrollments', 'quiz_attempts', 'vibelms_attempts', 'media' ) as $name ) {
			$data[ $name ] = $this->read_archive_json( $zip, $name . '.json' );
			if ( is_wp_error( $data[ $name ] ) ) {
				$data[ $name ] = array();
			}
		}

		$errors = array();
		$stats  = array();
		$maps   = array(
			'users' => array(),
			'posts' => array(),
			'attempts' => array(),
			'media' => array(),
		);

		$this->import_users( $data['users'], $maps['users'], $stats, $errors );
		$this->import_media( $zip, $data['media'], $maps['media'], $stats, $errors );
		$course_data = $this->prepare_course_data_for_import( $data['courses'], $data['media'] );
		$this->import_courses( $course_data, $maps['posts'], $stats, $errors );
		$membership_data = $this->prepare_course_data_for_import( $data['memberships'], $data['media'] );
		$this->import_memberships( $membership_data, $maps, $stats, $errors );
		$this->import_certificates( $data['certificates'], $maps, $data['media'], $stats, $errors );
		$this->apply_featured_images( $maps['posts'] );
		$this->repair_media_parents( $data['media'], $maps['media'], $maps['posts'] );
		$this->import_settings( $data['settings'], $maps['posts'], $stats, $errors );
		$this->import_enrollments( $data['enrollments'], $maps, $stats, $errors );
		$this->import_quiz_attempts( $data['quiz_attempts'], $maps, $stats, $errors );
		$this->import_vibelms_attempts( $data['vibelms_attempts'], $maps, $stats, $errors );
		$zip->close();

		$message = empty( $errors ) ? __( 'Импорт VibeLMS завершён.', 'lifterlms' ) : __( 'Импорт завершён с предупреждениями.', 'lifterlms' );
		$this->log_transfer( 'VibeLMS transfer import completed', array( 'stats' => $stats, 'errors' => $errors ) );
		$this->redirect_with_result( $message, $stats, $errors );
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
	 * @return void
	 */
	private function import_media( $zip, &$media, &$maps, &$stats, &$errors ) {
		if ( empty( $media ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		foreach ( $media as &$raw ) {
			$source_id = isset( $raw['source_id'] ) ? absint( $raw['source_id'] ) : 0;
			$entry     = isset( $raw['entry'] ) ? sanitize_file_name( basename( $raw['entry'] ) ) : '';
			if ( ! $source_id || ! $entry || false === strpos( $raw['entry'], 'media/' ) ) {
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
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file['file'] );
			if ( $metadata ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
			$stats['media'] = isset( $stats['media'] ) ? $stats['media'] + 1 : 1;
		}
		unset( $raw );
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
		if ( empty( $courses ) ) {
			return;
		}
		$generator = new LLMS_Generator(
			array(
				'_generator' => 'LifterLMS/BulkCourseGenerator',
				'_source'    => '',
				'_version'   => defined( 'VIBELMS_VERSION' ) ? VIBELMS_VERSION : '',
				'courses'    => $courses,
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
		$stats['courses'] = count( $generator->get_generated_courses() );
		$stats['lessons'] = isset( $generator->get_generated_content()['lesson'] ) ? count( $generator->get_generated_content()['lesson'] ) : 0;
		$stats['quizzes'] = isset( $generator->get_generated_content()['quiz'] ) ? count( $generator->get_generated_content()['quiz'] ) : 0;
		$stats['questions'] = isset( $generator->get_generated_content()['question'] ) ? count( $generator->get_generated_content()['question'] ) : 0;
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
		$parts = array();
		foreach ( $stats as $key => $value ) {
			$parts[] = $key . ': ' . absint( $value );
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
