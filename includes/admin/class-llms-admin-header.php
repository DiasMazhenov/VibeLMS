<?php
/**
 * LLMS_Admin_Header class file
 *
 * @package LifterLMS/Admin/Classes
 *
 * @since 7.1.0
 * @version 7.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin Header UI.
 *
 * @since 7.1.0
 */
class LLMS_Admin_Header {

	/**
	 * Constructor.
	 *
	 * @since 7.1.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'in_admin_header', array( $this, 'admin_header' ) );
	}

	/**
	 * Show admin header banner on LifterLMS admin screens.
	 *
	 * @since 7.1.0
	 * @since 7.1.2 Making the LifterLMS logo link to the LifterLMS.com site.
	 * @since 7.1.3 Using strpos instead of str_starts_with for compatibility.
	 * @since 7.4.0 Added `llms_admin_show_header` filter and move wizard check.
	 *
	 * @return void
	 */
	public function admin_header() {

		// Assume we should not show our header.
		$show_header = false;

		// Get the current screen and determine if we should show the header.
		$current_screen = get_current_screen();

		// Show header on our custom post types in admin, but not on the block editor.
		if (
			isset( $current_screen->post_type ) &&
			in_array( $current_screen->post_type, array( 'course', 'lesson', 'llms_quiz', 'llms_membership', 'llms_review', 'llms_engagement', 'llms_order', 'llms_coupon', 'llms_voucher', 'llms_form', 'llms_achievement', 'llms_my_achievement', 'llms_certificate', 'llms_my_certificate', 'llms_email' ), true ) &&
			false === $current_screen->is_block_editor
		) {
			$show_header = true;
		}

		// Get the current page if available.
		$page = (string) llms_filter_input( INPUT_GET, 'page' );

		// Show header on our settings pages.
		if (
			( strpos( $page, 'llms-' ) === 0 ) ||
			( strpos( $page, 'vibelms-' ) === 0 ) ||
			( ! empty( $current_screen->id ) && strpos( $current_screen->id, 'lifterlms' ) === 0 ) ||
			( ! empty( $current_screen->id ) && in_array( $current_screen->id, array( 'users', 'user-edit' ), true ) )
		) {
			$show_header = true;
		}

		// Don't show header on the Course Builder.
		if ( isset( $current_screen->base ) && 'admin_page_llms-course-builder' === $current_screen->base ) {
			$show_header = false;
		}

		/**
		 * Allow developers to filter the show header value.
		 *
		 * @since 7.4.0
		 *
		 * @param bool      $show_header    Whether to show the header.
		 * @param WP_Screen $current_screen The current screen object.
		 * @param string    $page           The current page if available.
		 */
		$show_header = apply_filters( 'llms_admin_show_header', $show_header, $current_screen, $page );

		// Conditionally show our header.
		if ( ! empty( $show_header ) ) { ?>
			<header class="llms-header">
				<div class="llms-inside-wrap">
					<a class="vibelms-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=llms-dashboard' ) ); ?>">
						<span class="vibelms-brand-mark" aria-hidden="true">V</span>
						<span class="vibelms-brand-name">VibeLMS</span>
					</a>
					<div class="llms-meta">
						<span class="llms-version"><?php echo esc_html( sprintf( __( 'Версия: %s', 'lifterlms' ), VIBELMS_VERSION ) ); ?></span>
					</div>
					<?php $this->render_quick_navigation( $current_screen, $page ); ?>
				</div>
			</header>
			<?php
		}
	}

	/**
	 * Render the primary VibeLMS admin navigation.
	 *
	 * @param WP_Screen $current_screen Current admin screen.
	 * @param string    $page           Current page slug.
	 * @return void
	 */
	private function render_quick_navigation( $current_screen, $page ) {
		$links = array(
			'dashboard' => array(
				'label'      => __( 'Панель', 'lifterlms' ),
				'icon'       => 'dashicons-dashboard',
				'url'        => admin_url( 'admin.php?page=llms-dashboard' ),
				'capability' => 'manage_lifterlms',
			),
			'courses' => array(
				'label'      => __( 'Курсы', 'lifterlms' ),
				'icon'       => 'dashicons-welcome-learn-more',
				'url'        => admin_url( 'edit.php?post_type=course' ),
				'capability' => 'edit_courses',
			),
			'quizzes' => array(
				'label'      => __( 'Тесты', 'lifterlms' ),
				'icon'       => 'dashicons-clipboard',
				'url'        => admin_url( 'admin.php?page=llms-quiz-builder' ),
				'capability' => 'edit_courses',
			),
			'participants' => array(
				'label'      => __( 'Участники', 'lifterlms' ),
				'icon'       => 'dashicons-groups',
				'url'        => admin_url( 'users.php?role=vibelms_student' ),
				'capability' => 'list_users',
			),
			'access-groups' => array(
				'label'      => __( 'Группы доступа', 'lifterlms' ),
				'icon'       => 'dashicons-admin-users',
				'url'        => admin_url( 'edit.php?post_type=llms_membership' ),
				'capability' => 'edit_memberships',
			),
			'attempts' => array(
				'label'      => __( 'Журнал', 'lifterlms' ),
				'icon'       => 'dashicons-list-view',
				'url'        => admin_url( 'admin.php?page=vibelms-attempts' ),
				'capability' => 'vibelms_view_reports',
			),
			'transfer' => array(
				'label'      => __( 'Перенос', 'lifterlms' ),
				'icon'       => 'dashicons-migrate',
				'url'        => admin_url( 'admin.php?page=vibelms-transfer' ),
				'capability' => 'manage_options',
			),
			'settings' => array(
				'label'      => __( 'Настройки', 'lifterlms' ),
				'icon'       => 'dashicons-admin-generic',
				'url'        => admin_url( 'admin.php?page=llms-settings' ),
				'capability' => 'manage_lifterlms',
			),
		);
		$links = apply_filters( 'vibelms_admin_quick_links', $links, $current_screen, $page );

		if ( ! is_array( $links ) ) {
			return;
		}

		echo '<nav class="llms-header-nav" aria-label="' . esc_attr__( 'Навигация VibeLMS', 'lifterlms' ) . '"><ul>';
		foreach ( $links as $key => $link ) {
			if ( ! is_array( $link ) || empty( $link['url'] ) || ( ! empty( $link['capability'] ) && ! current_user_can( $link['capability'] ) ) ) {
				continue;
			}

			$active = $this->is_quick_link_active( $key, $current_screen, $page ) ? ' is-active' : '';
			echo '<li><a class="llms-header-nav__link' . esc_attr( $active ) . '" href="' . esc_url( $link['url'] ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>';
			if ( ! empty( $link['icon'] ) ) {
				echo '<span class="dashicons ' . esc_attr( $link['icon'] ) . '" aria-hidden="true"></span>';
			}
			echo '<span>' . esc_html( $link['label'] ) . '</span></a></li>';
		}
		echo '</ul></nav>';
	}

	/**
	 * Determine the active primary navigation item.
	 *
	 * @param string    $key            Link key.
	 * @param WP_Screen $current_screen Current admin screen.
	 * @param string    $page           Current page slug.
	 * @return bool
	 */
	private function is_quick_link_active( $key, $current_screen, $page ) {
		$post_type = isset( $current_screen->post_type ) ? $current_screen->post_type : '';
		$screen_id = isset( $current_screen->id ) ? $current_screen->id : '';

		if ( 'dashboard' === $key ) {
			return 'llms-dashboard' === $page;
		}
		if ( 'courses' === $key ) {
			return in_array( $post_type, array( 'course', 'lesson' ), true ) || 'edit-course' === $screen_id;
		}
		if ( 'quizzes' === $key ) {
			return 'llms-quiz-builder' === $page || 'llms_quiz' === $post_type;
		}
		if ( 'participants' === $key ) {
			return in_array( $screen_id, array( 'users', 'user-edit' ), true );
		}
		if ( 'access-groups' === $key ) {
			return 'llms_membership' === $post_type;
		}
		if ( 'attempts' === $key ) {
			return 'vibelms-attempts' === $page;
		}
		if ( 'transfer' === $key ) {
			return 'vibelms-transfer' === $page;
		}
		if ( 'settings' === $key ) {
			return 'llms-settings' === $page;
		}

		return false;
	}
}

return new LLMS_Admin_Header();
