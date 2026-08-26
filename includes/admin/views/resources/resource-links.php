<?php
/**
 * VibeLMS local resource links meta box HTML.
 *
 * @package VibeLMS/Admin/Views/Resources
 * @since 0.0.18
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="llms-resource-links">
	<div class="llms-list">
		<h3><span class="dashicons dashicons-admin-post"></span> <?php esc_html_e( 'Контент', 'lifterlms' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=course' ) ); ?>"><?php esc_html_e( 'Курсы', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=llms_membership' ) ); ?>"><?php esc_html_e( 'Группы доступа', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=llms-settings' ) ); ?>"><?php esc_html_e( 'Настройки платформы', 'lifterlms' ); ?></a></li>
		</ul>
	</div>
	<div class="llms-list">
		<h3><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Отчёты и перенос', 'lifterlms' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=llms-reporting' ) ); ?>"><?php esc_html_e( 'Отчёты', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=vibelms-transfer' ) ); ?>"><?php esc_html_e( 'Перенос данных', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=vibelms-attempts' ) ); ?>"><?php esc_html_e( 'Журнал попыток', 'lifterlms' ); ?></a></li>
		</ul>
	</div>
</div>
