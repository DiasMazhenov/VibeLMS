<?php
/**
 * Setup Wizard step: Payments
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

$country  = get_lifterlms_country();
$currency = get_lifterlms_currency();
$payments = get_option( 'llms_gateway_manual_enabled', 'no' );

?>
<h1><?php esc_html_e( 'Payments', 'lifterlms' ); ?></h1>

<table>
	<tr>
		<td colspan="2">
			<p><label for="llms_country"><?php esc_html_e( 'Which country should be used as the default for student registrations?', 'lifterlms' ); ?></label></p>
			<p>
				<select id="llms_country" name="country" class="llms-select2">
				<?php foreach ( get_lifterlms_countries() as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $code, $country ); ?>>
						<?php echo esc_html( $name . ' (' . $code . ')' ); ?>
					</option>
				<?php endforeach; ?>
				</select>
			</p>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<p><label for="llms_currency"><?php esc_html_e( 'Which currency should be used for payment processing?', 'lifterlms' ); ?></label></p>
			<p>
				<select id="llms_currency" name="currency" class="llms-select2">
				<?php foreach ( get_lifterlms_currencies() as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $code, $currency ); ?>><?php echo esc_html( $name ); ?> (<?php echo esc_html( get_lifterlms_currency_symbol( $code ) ); ?>)</option>
				<?php endforeach; ?>
				</select>
				<i><?php esc_html_e( 'Если нужной валюты нет в списке, её можно добавить позже в настройках оплаты.', 'lifterlms' ); ?></i>
			</p>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<p><?php esc_html_e( 'VibeLMS поддерживает ручные и онлайн-платежи. Совместимый платёжный шлюз можно установить отдельно и настроить на вкладке «Оплата».', 'lifterlms' ); ?></p>
			<p><label for="llms_manual"><input id="llms_manual" name="manual_payments" type="checkbox" value="yes"<?php checked( 'yes', $payments ); ?>> <?php esc_html_e( 'Enable Offline Payments', 'lifterlms' ); ?></label></p>
			<p><?php echo esc_html__( 'Payment gateways may be configured under Settings in the "Checkout" tab.', 'lifterlms' ); ?></p>
		</td>
	</tr>
</table>
