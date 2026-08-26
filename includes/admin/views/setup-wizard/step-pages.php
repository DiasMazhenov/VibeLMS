<?php
/**
 * VibeLMS setup wizard step: page setup.
 *
 * @since 4.4.4
 * @since 7.3.0 Using the `LLMS_Install::get_pages()` method now.
 * @since 7.4.0 Escape remaining strings.
 * @version 7.4.0
 *
 * @property LLMS_Admin_Setup_Wizard $this Setup wizard class instance.
 */

defined( 'ABSPATH' ) || exit;
?>
<h1><?php esc_html_e( 'Настройка страниц', 'lifterlms' ); ?></h1>

<p><?php esc_html_e( 'VibeLMS использует несколько основных страниц. Несуществующие страницы будут созданы автоматически.', 'lifterlms' ); ?></p>

<table>
	<?php
	$pages = LLMS_Install::get_pages();
	foreach ( $pages as $page ) {
		// Skip pages that don't have all the info we want to show.
		if ( empty( $page['description'] ) || empty( $page['wizard_title'] ) ) {
			continue;
		}
		$page_id  = absint( get_option( $page['option'] ?? '' ) );
		$page_url = $page_id ? get_edit_post_link( $page_id, 'raw' ) : admin_url( 'edit.php?post_type=page' );
		?>
		<tr>
		<td><a href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $page['wizard_title'] ); ?></a></td>
		<td><p><?php echo esc_html( $page['description'] ); ?></p></td>
		</tr>
		<?php
	}
	?>
</table>

<p>
	<?php esc_html_e( 'После настройки страницы можно изменить в разделе «Страницы», а порядок пунктов меню — в разделе «Внешний вид → Меню».', 'lifterlms' ); ?>
</p>
