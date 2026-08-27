<?php
/**
 * Dashboard Page HTML.
 *
 * @package LifterLMS/Admin/Views
 *
 * @since 7.1.0
 * @since 7.3.0 Leverage new `LLMS_Admin_Dashboard_Widget::get_dashboard_widget_data()` method.
 * @version 7.3.0
 */

defined( 'ABSPATH' ) || exit;

$activity_period = LLMS_Admin_Dashboard::get_activity_period();
$current_range   = $activity_period['range'];
$activity_dates  = $activity_period['dates'];

?>
<div class="wrap lifterlms lifterlms-settings llms-dashboard">

	<div class="llms-subheader">

		<h1>Панель управления VibeLMS</h1>

	</div>

	<div class="llms-inside-wrap">

		<hr class="wp-header-end">

		<div class="llms-dashboard-activity">
			<div class="vibelms-dashboard-period">
				<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="vibelms-dashboard-period-form">
					<input type="hidden" name="page" value="llms-dashboard">
					<label for="vibelms-dashboard-range"><?php esc_html_e( 'Период', 'lifterlms' ); ?></label>
					<select id="vibelms-dashboard-range" name="range">
						<?php foreach ( $activity_period['ranges'] as $range => $label ) : ?>
							<option value="<?php echo esc_attr( $range ); ?>" <?php selected( $current_range, $range ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="vibelms-dashboard-custom-dates"<?php echo 'custom' === $current_range ? '' : ' hidden'; ?>>
						<label for="vibelms-dashboard-date-start"><?php esc_html_e( 'С', 'lifterlms' ); ?></label>
						<input id="vibelms-dashboard-date-start" type="date" name="date_start" value="<?php echo esc_attr( $activity_dates['start'] ); ?>">
						<label for="vibelms-dashboard-date-end"><?php esc_html_e( 'по', 'lifterlms' ); ?></label>
						<input id="vibelms-dashboard-date-end" type="date" name="date_end" value="<?php echo esc_attr( $activity_dates['end'] ); ?>">
					</div>
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Показать', 'lifterlms' ); ?></button>
				</form>
			</div>
			<h2><?php printf( esc_html__( 'Активность за период: с %1$s по %2$s', 'lifterlms' ), esc_html( LLMS_Admin_Dashboard::format_activity_date( $activity_dates['start'] ) ), esc_html( LLMS_Admin_Dashboard::format_activity_date( $activity_dates['end'] ) ) ); ?></h2>
			<?php echo '<style type="text/css">#llms-charts-wrapper{display:none;}</style>'; ?>
			<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in template.
				echo llms_get_template(
					'admin/reporting/tabs/widgets.php',
					array(
						'json'        => wp_json_encode(
							array(
								'current_tab'         => 'settings',
								'current_range'       => $current_range,
								'current_students'    => array(),
								'current_courses'     => array(),
								'current_memberships' => array(),
								'dates'               => array(
									'start' => $activity_dates['start'],
									'end'   => $activity_dates['end'],
								),
							)
						),
						'widget_data' => array( LLMS_Admin_Dashboard_Widget::get_dashboard_widget_data() ),
					)
				);
				?>
		</div> <!-- end llms-dashboard-activity -->

		<form id="llms-dashboard-form" method="post" action="admin-post.php">
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-1">

					<div id="postbox-container-2" class="postbox-container">
						<?php do_meta_boxes( 'toplevel_page_llms-dashboard', 'normal', '' ); ?>
					</div>

					<br class="clear">

				</div> <!-- end dashboard-widgets -->

				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>

			</div> <!-- end dashboard-widgets-wrap -->
		</form>
		<script type="text/javascript">
			//<![CDATA[
			(function($) {
				var dashboardRange = document.getElementById('vibelms-dashboard-range');
				var customDates = document.querySelector('.vibelms-dashboard-custom-dates');
				if (dashboardRange && customDates) {
					var toggleCustomDates = function() {
						var isCustom = 'custom' === dashboardRange.value;
						customDates.hidden = ! isCustom;
						Array.prototype.forEach.call(customDates.querySelectorAll('input'), function(input) {
							input.disabled = ! isCustom;
						});
					};
					dashboardRange.addEventListener('change', toggleCustomDates);
					toggleCustomDates();
				}

				function initVibeLMSPostboxes() {
					$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
					if ( window.postboxes && 'function' === typeof window.postboxes.add_postbox_toggles ) {
						window.postboxes.add_postbox_toggles('toplevel_page_llms-dashboard');
					}
				}

				if ( 'complete' === document.readyState ) {
					initVibeLMSPostboxes();
				} else {
					$(window).one('load', initVibeLMSPostboxes);
				}
			})(jQuery);
			//]]>
		</script>

	</div>

</div> <!-- end .wrap.llms-dashboard -->
