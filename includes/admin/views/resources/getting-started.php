<?php
/**
 * VibeLMS getting started links meta box HTML.
 *
 * @package VibeLMS/Admin/Views/Resources
 * @since 0.0.18
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="llms-getting-started-links">
	<div class="llms-list">
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=course' ) ); ?>"><?php esc_html_e( 'Создать курс', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=llms-settings' ) ); ?>"><?php esc_html_e( 'Открыть настройки', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=vibelms-transfer' ) ); ?>"><?php esc_html_e( 'Перенести данные с другого сайта', 'lifterlms' ); ?></a></li>
		</ul>
	</div>
</div>
