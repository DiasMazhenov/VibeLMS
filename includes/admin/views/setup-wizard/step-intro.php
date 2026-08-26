<?php
/**
 * Setup Wizard step: Welcome
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

<h1><?php esc_html_e( 'Добро пожаловать в VibeLMS!', 'lifterlms' ); ?></h1>
<p><?php esc_html_e( 'Этот мастер поможет настроить основные параметры платформы и быстрее начать создавать курсы.', 'lifterlms' ); ?></p>
<p><?php esc_html_e( 'Настройка займёт несколько минут и является необязательной. Её можно пропустить и вернуться позже.', 'lifterlms' ); ?></p>
<?php
