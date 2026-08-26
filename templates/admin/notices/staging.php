<?php
/**
 * Staging Site Recurring Payment Notice
 *
 * @package LifterLMS/Templates/Admin
 *
 * @since 3.0.2
 * @version 3.0.2
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	exit;
}
?>

<p><strong><?php echo esc_html__( 'Похоже, VibeLMS установлен на тестовом сайте.', 'lifterlms' ); ?></strong></p>

<p><?php esc_html_e( 'VibeLMS отключил автоматические платежи, чтобы пользователи тестового сайта не получили повторные списания.', 'lifterlms' ); ?></p>

<p><?php esc_html_e( 'Выберите действие ниже. Позже режим можно изменить в настройках VibeLMS.', 'lifterlms' ); ?></p>

<p>
	<a class="button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'llms-staging-status', 'disable', admin_url( 'admin.php?page=llms-settings' ) ), 'llms_staging_status', '_llms_staging_nonce' ) ); ?>"><?php echo esc_html__( 'Leave Automatic Payments Disabled', 'lifterlms' ); ?></a>
	&nbsp;&nbsp;
	<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'llms-staging-status', 'enable', admin_url( 'admin.php?page=llms-settings' ) ), 'llms_staging_status', '_llms_staging_nonce' ) ); ?>"><?php echo esc_html__( 'Enable Automatic Payments', 'lifterlms' ); ?></a>
</p>
