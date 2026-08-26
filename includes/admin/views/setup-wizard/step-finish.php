<?php
/**
 * Setup Wizard step: Finish
 *
 * @package LifterLMS/Views/Admin/SetupWizard
 *
 * @since 4.4.4
 * @since 4.8.0 Unknown.
 * @since 7.4.0 Escape output.
 * @version 7.4.0
 *
 * @property LLMS_Admin_Setup_Wizard $this Setup wizard class instance.
 */

defined( 'ABSPATH' ) || exit;

?>
<h1><?php esc_html_e( 'Настройка VibeLMS завершена!', 'lifterlms' ); ?></h1>
<p><?php esc_html_e( 'Платформа готова. Начните с создания курса или перенесите данные с другого сайта.', 'lifterlms' ); ?></p>
<ul>
	<li><span class="dashicons dashicons-welcome-learn-more"></span> <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=course' ) ); ?>"><?php esc_html_e( 'Создать новый курс', 'lifterlms' ); ?></a></li>
	<li><span class="dashicons dashicons-migrate"></span> <a href="<?php echo esc_url( admin_url( 'admin.php?page=vibelms-transfer' ) ); ?>"><?php esc_html_e( 'Перенести данные VibeLMS', 'lifterlms' ); ?></a></li>
</ul>
