<?php
/**
 * Project-neutral VibeLMS materials and front-end chrome.
 *
 * @package VibeLMS/Classes
 * @since 0.0.29
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage language-aware slides, videos and documents.
 *
 * Materials are native WordPress posts, so ACF remains optional.
 *
 * @since 0.0.29
 */
class LLMS_VibeLMS_Content {

	const POST_TYPE = 'vibelms_material';
	const TYPE_META = '_vibelms_material_type';
	const LANGUAGE_META = '_vibelms_material_language';
	const URL_META = '_vibelms_material_url';
	const ATTACHMENT_META = '_vibelms_material_attachment_id';
	const ORDER_META = '_vibelms_material_order';
	const LANGUAGE_COOKIE = 'vibelms_language';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_set_language_cookie' ), 1 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_material' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'lifterlms_general_settings', array( $this, 'add_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_post_vibelms_download_material', array( $this, 'download_material' ) );
		add_action( 'admin_post_nopriv_vibelms_download_material', array( $this, 'download_material' ) );
		add_shortcode( 'vibelms_materials', array( $this, 'shortcode_materials' ) );
		add_shortcode( 'vibelms_language_switcher', array( $this, 'shortcode_language_switcher' ) );
		add_shortcode( 'vibelms_header', array( $this, 'shortcode_header' ) );
		add_shortcode( 'vibelms_footer', array( $this, 'shortcode_footer' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => __( 'Материалы', 'lifterlms' ),
					'singular_name' => __( 'Материал', 'lifterlms' ),
					'menu_name' => __( 'Материалы', 'lifterlms' ),
					'add_new' => __( 'Добавить материал', 'lifterlms' ),
					'add_new_item' => __( 'Добавить материал', 'lifterlms' ),
					'edit_item' => __( 'Изменить материал', 'lifterlms' ),
					'new_item' => __( 'Новый материал', 'lifterlms' ),
					'view_item' => __( 'Просмотреть материал', 'lifterlms' ),
					'search_items' => __( 'Найти материалы', 'lifterlms' ),
					'not_found' => __( 'Материалы не найдены.', 'lifterlms' ),
					'not_found_in_trash' => __( 'В корзине материалов нет.', 'lifterlms' ),
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => 'lifterlms',
				'show_in_rest' => false,
				'rewrite' => false,
				'menu_icon' => 'dashicons-media-document',
				'supports' => array( 'title', 'editor', 'thumbnail' ),
				'capability_type' => 'post',
				'capabilities' => array(
					'edit_post' => 'manage_options',
					'read_post' => 'manage_options',
					'delete_post' => 'manage_options',
					'edit_posts' => 'manage_options',
					'edit_others_posts' => 'manage_options',
					'publish_posts' => 'manage_options',
					'read_private_posts' => 'manage_options',
					'delete_posts' => 'manage_options',
					'delete_private_posts' => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'create_posts' => 'manage_options',
				),
			)
		);
	}

	public function add_settings( $settings ) {
		$settings[] = array( 'id' => 'vibelms_content_settings', 'type' => 'sectionstart' );
		$settings[] = array( 'id' => 'vibelms_content_settings_title', 'title' => __( 'VibeLMS: материалы и сайт', 'lifterlms' ), 'type' => 'title' );
		$settings[] = array( 'desc' => __( 'Если заполнено, ссылка «Поддержка» появится во фронтенд-подвале.', 'lifterlms' ), 'id' => 'vibelms_support_url', 'title' => __( 'Ссылка поддержки', 'lifterlms' ), 'type' => 'text' );
		$settings[] = array( 'desc' => __( 'Можно использовать {year} и {site}.', 'lifterlms' ), 'id' => 'vibelms_footer_text', 'title' => __( 'Текст подвала', 'lifterlms' ), 'type' => 'text' );
		$settings[] = array( 'id' => 'vibelms_content_settings', 'type' => 'sectionend' );
		return $settings;
	}

	public function register_meta_box() {
		add_meta_box( 'vibelms-material-details', __( 'Настройки материала', 'lifterlms' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'side', 'high' );
	}

	public function render_meta_box( $post ) {
		$type = get_post_meta( $post->ID, self::TYPE_META, true ) ?: 'slide';
		$language = get_post_meta( $post->ID, self::LANGUAGE_META, true ) ?: $this->get_default_language();
		$url = get_post_meta( $post->ID, self::URL_META, true );
		$attachment = absint( get_post_meta( $post->ID, self::ATTACHMENT_META, true ) );
		$order = absint( get_post_meta( $post->ID, self::ORDER_META, true ) );
		wp_nonce_field( 'vibelms_save_material', 'vibelms_material_nonce' );
		?>
		<p><label for="vibelms-material-type"><strong><?php esc_html_e( 'Тип', 'lifterlms' ); ?></strong></label>
		<select class="widefat" id="vibelms-material-type" name="vibelms_material_type">
			<?php foreach ( array( 'slide' => __( 'Слайд', 'lifterlms' ), 'video' => __( 'Видео', 'lifterlms' ), 'document' => __( 'Документ', 'lifterlms' ) ) as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<p><label for="vibelms-material-language"><strong><?php esc_html_e( 'Язык материала', 'lifterlms' ); ?></strong></label>
		<select class="widefat" id="vibelms-material-language" name="vibelms_material_language">
			<?php foreach ( $this->get_supported_languages() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $language, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<p><label for="vibelms-material-order"><strong><?php esc_html_e( 'Порядок', 'lifterlms' ); ?></strong></label>
		<input class="widefat" id="vibelms-material-order" name="vibelms_material_order" type="number" min="0" value="<?php echo esc_attr( $order ); ?>"></p>
		<p class="vibelms-material-video-field"><label for="vibelms-material-url"><strong><?php esc_html_e( 'Ссылка на видео', 'lifterlms' ); ?></strong></label>
		<input class="widefat" id="vibelms-material-url" name="vibelms_material_url" type="url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://"></p>
		<p class="vibelms-material-document-field"><strong><?php esc_html_e( 'Файл документа', 'lifterlms' ); ?></strong><br>
		<input id="vibelms-material-attachment-id" name="vibelms_material_attachment_id" type="hidden" value="<?php echo esc_attr( $attachment ); ?>">
		<button type="button" class="button" id="vibelms-material-choose-file"><?php esc_html_e( 'Выбрать файл', 'lifterlms' ); ?></button>
		<span id="vibelms-material-file-name" style="display:block;margin-top:6px;"><?php echo esc_html( $attachment ? get_the_title( $attachment ) : __( 'Файл не выбран.', 'lifterlms' ) ); ?></span></p>
		<p class="description"><?php esc_html_e( 'Для слайда используйте Изображение записи. Текст редактора станет описанием материала.', 'lifterlms' ); ?></p>
		<?php
	}

	public function save_material( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $post instanceof WP_Post || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$nonce = isset( $_POST['vibelms_material_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vibelms_material_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vibelms_save_material' ) ) {
			return;
		}
		$types = array( 'slide', 'video', 'document' );
		$type = isset( $_POST['vibelms_material_type'] ) ? sanitize_key( wp_unslash( $_POST['vibelms_material_type'] ) ) : 'slide';
		$language = isset( $_POST['vibelms_material_language'] ) ? sanitize_key( wp_unslash( $_POST['vibelms_material_language'] ) ) : $this->get_default_language();
		$url = isset( $_POST['vibelms_material_url'] ) ? esc_url_raw( wp_unslash( $_POST['vibelms_material_url'] ) ) : '';
		$media_id = isset( $_POST['vibelms_material_attachment_id'] ) ? absint( $_POST['vibelms_material_attachment_id'] ) : 0;
		$order = isset( $_POST['vibelms_material_order'] ) ? absint( $_POST['vibelms_material_order'] ) : 0;
		$languages = $this->get_supported_languages();
		update_post_meta( $post_id, self::TYPE_META, in_array( $type, $types, true ) ? $type : 'slide' );
		update_post_meta( $post_id, self::LANGUAGE_META, isset( $languages[ $language ] ) ? $language : $this->get_default_language() );
		update_post_meta( $post_id, self::URL_META, $url );
		update_post_meta( $post_id, self::ATTACHMENT_META, $media_id );
		update_post_meta( $post_id, self::ORDER_META, $order );
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		$script = "(function($){function sync(){var type=$('#vibelms-material-type').val();$('.vibelms-material-video-field').toggle('video'===type);$('.vibelms-material-document-field').toggle('document'===type)}$(document).on('click','#vibelms-material-choose-file',function(e){e.preventDefault();var frame=wp.media({title:'" . esc_js( __( 'Выберите файл материала', 'lifterlms' ) ) . "',button:{text:'" . esc_js( __( 'Использовать файл', 'lifterlms' ) ) . "'},multiple:false});frame.on('select',function(){var file=frame.state().get('selection').first().toJSON();$('#vibelms-material-attachment-id').val(file.id);$('#vibelms-material-file-name').text(file.filename||file.title||'')});frame.open()});$(document).on('change','#vibelms-material-type',sync);$(sync)})(jQuery);";
		wp_add_inline_script( 'media-editor', $script );
	}

	public function add_columns( $columns ) {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result['vibelms_material_type'] = __( 'Тип', 'lifterlms' );
				$result['vibelms_material_language'] = __( 'Язык', 'lifterlms' );
			}
		}
		return $result;
	}

	public function render_column( $column, $post_id ) {
		if ( 'vibelms_material_type' === $column ) {
			$types = array( 'slide' => __( 'Слайд', 'lifterlms' ), 'video' => __( 'Видео', 'lifterlms' ), 'document' => __( 'Документ', 'lifterlms' ) );
			$type = get_post_meta( $post_id, self::TYPE_META, true );
			echo esc_html( isset( $types[ $type ] ) ? $types[ $type ] : '—' );
		}
		if ( 'vibelms_material_language' === $column ) {
			$languages = $this->get_supported_languages();
			$key = get_post_meta( $post_id, self::LANGUAGE_META, true );
			echo esc_html( isset( $languages[ $key ] ) ? $languages[ $key ] : $key );
		}
	}

	public function enqueue_frontend_assets() {
		if ( is_admin() ) {
			return;
		}
		$base_url = trailingslashit( plugin_dir_url( LLMS_PLUGIN_FILE ) );
		$version = defined( 'VIBELMS_VERSION' ) ? VIBELMS_VERSION : null;
		wp_enqueue_style( 'vibelms-frontend', $base_url . 'assets/css/vibelms-frontend.css', array(), $version );
		wp_enqueue_script( 'vibelms-frontend', $base_url . 'assets/js/vibelms-frontend.js', array(), $version, true );
	}

	public function shortcode_materials( $atts ) {
		$atts = shortcode_atts( array( 'type' => 'all', 'language' => 'current', 'limit' => 0 ), $atts, 'vibelms_materials' );
		if ( ! $this->can_view_materials() ) {
			return '<p class="vibelms-materials-locked">' . esc_html__( 'Войдите, чтобы открыть учебные материалы.', 'lifterlms' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Войти', 'lifterlms' ) . '</a></p>';
		}
		$type = sanitize_key( (string) $atts['type'] );
		$type = in_array( $type, array( 'all', 'slide', 'video', 'document' ), true ) ? $type : 'all';
		$language = sanitize_key( (string) $atts['language'] );
		$languages = $this->get_supported_languages();
		$language = 'current' === $language || ! isset( $languages[ $language ] ) ? $this->get_current_language() : $language;
		$materials = $this->get_materials( $type, $language, absint( $atts['limit'] ) );
		if ( empty( $materials ) ) {
			return '<p class="vibelms-materials-empty">' . esc_html__( 'Материалы пока не добавлены.', 'lifterlms' ) . '</p>';
		}
		$groups = array( 'slide' => array(), 'video' => array(), 'document' => array() );
		foreach ( $materials as $material ) {
			$material_type = get_post_meta( $material->ID, self::TYPE_META, true );
			if ( isset( $groups[ $material_type ] ) ) {
				$groups[ $material_type ][] = $material;
			}
		}
		$output = '<div class="vibelms-materials vibelms-materials--' . esc_attr( $type ) . '" data-vibelms-materials="' . esc_attr( $type ) . '">';
		foreach ( $groups as $group_type => $items ) {
			if ( ( 'all' !== $type && $type !== $group_type ) || empty( $items ) ) {
				continue;
			}
			$output .= $this->render_material_group( $group_type, $items );
		}
		return $output . '</div>';
	}

	private function render_material_group( $type, $items ) {
		$output = '<section class="vibelms-material-group vibelms-material-group--' . esc_attr( $type ) . '">';
		if ( 'slide' === $type ) {
			$output .= '<div class="vibelms-material-slider" data-vibelms-slider><div class="vibelms-material-slider__track" data-vibelms-slider-track>';
			foreach ( $items as $index => $material ) {
				$output .= '<article class="vibelms-material-slide" data-vibelms-slide="' . esc_attr( $index ) . '">';
				if ( has_post_thumbnail( $material ) ) {
					$output .= get_the_post_thumbnail( $material, 'large', array( 'loading' => 'lazy', 'class' => 'vibelms-material-slide__image' ) );
				}
				$output .= '<div class="vibelms-material__body"><h3>' . esc_html( get_the_title( $material ) ) . '</h3>' . $this->render_description( $material ) . '</div></article>';
			}
			$output .= '</div>';
			if ( count( $items ) > 1 ) {
				$output .= '<div class="vibelms-material-slider__controls"><button type="button" class="vibelms-material-slider__button" data-vibelms-slider-prev aria-label="' . esc_attr__( 'Предыдущий слайд', 'lifterlms' ) . '">‹</button><div class="vibelms-material-slider__dots">';
				foreach ( $items as $index => $material ) {
					$output .= '<button type="button" class="vibelms-material-slider__dot' . ( 0 === $index ? ' is-active' : '' ) . '" data-vibelms-slider-dot="' . esc_attr( $index ) . '" aria-label="' . esc_attr( sprintf( __( 'Слайд %d', 'lifterlms' ), $index + 1 ) ) . '"' . ( 0 === $index ? ' aria-current="true"' : '' ) . '></button>';
				}
				$output .= '</div><button type="button" class="vibelms-material-slider__button" data-vibelms-slider-next aria-label="' . esc_attr__( 'Следующий слайд', 'lifterlms' ) . '">›</button></div>';
			}
			return $output . '</div></section>';
		}
		$output .= '<div class="vibelms-material-grid">';
		foreach ( $items as $material ) {
			$output .= '<article class="vibelms-material-card"><div class="vibelms-material__body"><h3>' . esc_html( get_the_title( $material ) ) . '</h3>';
			if ( 'video' === $type ) {
				$output .= $this->render_video( $material );
			} else {
				$output .= $this->render_description( $material );
				$attachment = absint( get_post_meta( $material->ID, self::ATTACHMENT_META, true ) );
				if ( $attachment ) {
					$url = wp_nonce_url( add_query_arg( array( 'action' => 'vibelms_download_material', 'material_id' => $material->ID ), admin_url( 'admin-post.php' ) ), 'vibelms_download_material_' . $material->ID );
					$output .= '<a class="vibelms-material-card__download" href="' . esc_url( $url ) . '">' . esc_html__( 'Скачать документ', 'lifterlms' ) . '</a>';
				}
			}
			$output .= '</div></article>';
		}
		return $output . '</div></section>';
	}

	private function render_description( $material ) {
		return $material->post_content ? '<div class="vibelms-material__description">' . wpautop( wp_kses_post( $material->post_content ) ) . '</div>' : '';
	}

	private function render_video( $material ) {
		$url = esc_url( get_post_meta( $material->ID, self::URL_META, true ) );
		if ( ! $url ) {
			return '<p>' . esc_html__( 'Ссылка на видео ещё не добавлена.', 'lifterlms' ) . '</p>';
		}
		if ( preg_match( '/\.(mp4|webm|ogg)(\?.*)?$/i', $url ) ) {
			return wp_video_shortcode( array( 'src' => $url, 'preload' => 'metadata' ) );
		}
		$embed = wp_oembed_get( $url );
		return $embed ? '<div class="vibelms-material-video">' . wp_kses_post( $embed ) . '</div>' : '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Открыть видео', 'lifterlms' ) . '</a>';
	}

	public function download_material() {
		$material_id = isset( $_GET['material_id'] ) ? absint( $_GET['material_id'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! is_user_logged_in() || ! $material_id || ! wp_verify_nonce( $nonce, 'vibelms_download_material_' . $material_id ) ) {
			wp_die( esc_html__( 'Ссылка недействительна или требуется вход в аккаунт.', 'lifterlms' ), '', array( 'response' => 403 ) );
		}
		$allowed = apply_filters( 'vibelms_can_download_material', true, $material_id, get_current_user_id() );
		$attachment_id = absint( get_post_meta( $material_id, self::ATTACHMENT_META, true ) );
		$path = $attachment_id ? get_attached_file( $attachment_id ) : '';
		if ( ! $allowed || self::POST_TYPE !== get_post_type( $material_id ) || 'document' !== get_post_meta( $material_id, self::TYPE_META, true ) || ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Документ недоступен.', 'lifterlms' ), '', array( 'response' => 404 ) );
		}
		$mime = get_post_mime_type( $attachment_id ) ?: 'application/octet-stream';
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . sanitize_mime_type( $mime ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( sanitize_file_name( basename( $path ) ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}

	public function shortcode_language_switcher() {
		$current_url = ( is_singular() && get_permalink() ) ? get_permalink() : home_url( '/' );
		$current = $this->get_current_language();
		$output = '<nav class="vibelms-language-switcher" aria-label="' . esc_attr__( 'Язык материалов', 'lifterlms' ) . '">';
		foreach ( $this->get_supported_languages() as $language => $label ) {
			$url = add_query_arg( 'vibelms_language', $language, $current_url );
			$output .= '<a href="' . esc_url( $url ) . '" class="' . ( $current === $language ? 'is-active' : '' ) . '"' . ( $current === $language ? ' aria-current="true"' : '' ) . '>' . esc_html( strtoupper( $language ) ) . '<span class="screen-reader-text"> — ' . esc_html( $label ) . '</span></a>';
		}
		return $output . '</nav>';
	}

	public function shortcode_header() {
		$account_id = absint( get_option( 'lifterlms_myaccount_page_id', 0 ) );
		$account_url = $account_id ? get_permalink( $account_id ) : home_url( '/' );
		$course_url = post_type_exists( 'course' ) ? get_post_type_archive_link( 'course' ) : home_url( '/' );
		$logo = get_custom_logo();
		$brand = $logo ? '<div class="vibelms-site-header__brand">' . $logo . '</div>' : '<a class="vibelms-site-header__brand" href="' . esc_url( home_url( '/' ) ) . '"><span class="vibelms-site-header__mark" aria-hidden="true">V</span><span>VibeLMS</span></a>';
		$output = '<header class="vibelms-site-header"><div class="vibelms-site-header__inner">' . $brand . '<nav class="vibelms-site-header__nav" aria-label="' . esc_attr__( 'Основная навигация', 'lifterlms' ) . '"><a href="' . esc_url( $course_url ) . '">' . esc_html__( 'Курсы', 'lifterlms' ) . '</a><a href="' . esc_url( $account_url ) . '">' . esc_html__( 'Мой кабинет', 'lifterlms' ) . '</a></nav><div class="vibelms-site-header__actions">' . do_shortcode( '[vibelms_language_switcher]' );
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$output .= '<span class="vibelms-site-header__email">' . esc_html( $user->user_email ) . '</span><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Выйти', 'lifterlms' ) . '</a>';
		} else {
			$output .= '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Войти', 'lifterlms' ) . '</a>';
		}
		return $output . '</div></div></header>';
	}

	public function shortcode_footer() {
		$text = (string) get_option( 'vibelms_footer_text', '' );
		$text = $text ? str_replace( array( '{year}', '{site}' ), array( wp_date( 'Y' ), get_bloginfo( 'name' ) ), $text ) : sprintf( __( '© %1$s %2$s. Все права защищены.', 'lifterlms' ), wp_date( 'Y' ), get_bloginfo( 'name' ) );
		$support = esc_url( get_option( 'vibelms_support_url', '' ) );
		$output = '<footer class="vibelms-site-footer"><div class="vibelms-site-footer__inner"><span>' . esc_html( $text ) . '</span>';
		if ( $support ) {
			$output .= '<a href="' . $support . '">' . esc_html__( 'Поддержка', 'lifterlms' ) . '</a>';
		}
		$output .= '<a href="https://mazhenov.kz" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Разработано в веб-студии Mazhenov Design', 'lifterlms' ) . '</a></div></footer>';
		return $output;
	}

	private function get_materials( $type, $language, $limit ) {
		$meta_query = array( array( 'key' => self::LANGUAGE_META, 'value' => $language ) );
		if ( 'all' !== $type ) {
			$meta_query[] = array( 'key' => self::TYPE_META, 'value' => $type );
		}
		return get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => $limit ? min( 100, $limit ) : -1, 'meta_key' => self::ORDER_META, 'orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_query' => $meta_query ) );
	}

	private function get_supported_languages() {
		return function_exists( 'llms_vibelms_platform' ) ? llms_vibelms_platform()->get_supported_languages() : array( 'ru' => __( 'Русский', 'lifterlms' ), 'kz' => __( 'Казахский', 'lifterlms' ) );
	}

	private function get_default_language() {
		$languages = $this->get_supported_languages();
		return (string) key( $languages );
	}

	private function get_current_language() {
		$languages = $this->get_supported_languages();
		$candidate = isset( $_GET['vibelms_language'] ) ? sanitize_key( wp_unslash( $_GET['vibelms_language'] ) ) : '';
		if ( ! $candidate && isset( $_COOKIE[ self::LANGUAGE_COOKIE ] ) ) {
			$candidate = sanitize_key( wp_unslash( $_COOKIE[ self::LANGUAGE_COOKIE ] ) );
		}
		if ( ! $candidate && function_exists( 'llms_vibelms_platform' ) && is_user_logged_in() ) {
			$candidate = llms_vibelms_platform()->get_user_language();
		}
		return isset( $languages[ $candidate ] ) ? $candidate : $this->get_default_language();
	}

	public function maybe_set_language_cookie() {
		$language = isset( $_GET['vibelms_language'] ) ? sanitize_key( wp_unslash( $_GET['vibelms_language'] ) ) : '';
		if ( ! $language || ! isset( $this->get_supported_languages()[ $language ] ) || headers_sent() ) {
			return;
		}
		setcookie( self::LANGUAGE_COOKIE, $language, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::LANGUAGE_COOKIE ] = $language;
	}

	private function can_view_materials() {
		return (bool) apply_filters( 'vibelms_can_view_materials', is_user_logged_in(), get_current_user_id() );
	}
}

function llms_vibelms_content() {
	static $instance;
	if ( ! $instance ) {
		$instance = new LLMS_VibeLMS_Content();
	}
	return $instance;
}
