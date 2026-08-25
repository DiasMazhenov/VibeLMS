<?php
/**
 * Quick links meta box HTML.
 *
 * @package VibeLMS/Admin/Views/Dashboard
 *
 * @since 0.0.05
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="llms-quick-links">
	<div class="llms-list">
		<h3><?php esc_html_e( 'Content', 'lifterlms' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=course' ) ); ?>"><?php esc_html_e( 'Create a New Course', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=llms_membership' ) ); ?>"><?php esc_html_e( 'Add a New Membership', 'lifterlms' ); ?></a></li>
		</ul>
		<a class="llms-button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=course' ) ); ?>"><?php esc_html_e( 'Create a New Course', 'lifterlms' ); ?></a>
	</div>
	<div class="llms-list">
		<h3><?php esc_html_e( 'Reports', 'lifterlms' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=llms-reporting&tab=students' ) ); ?>"><?php esc_html_e( 'View Students', 'lifterlms' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=llms_order' ) ); ?>"><?php esc_html_e( 'View Orders', 'lifterlms' ); ?></a></li>
		</ul>
		<a class="llms-button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=llms-reporting' ) ); ?>">Открыть отчёты</a>
	</div>
</div>
