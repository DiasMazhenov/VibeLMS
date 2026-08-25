<?php
/**
 * Upload question type features
 *
 * @package LifterLMS_Advanced_Quizzes/Admin/Views
 *
 * @since 1.0.0
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<# if ( 'upload' === data.get( 'question_type' ).get( 'id' ) ) { #>
	<div class="llms-settings-row">

		<div class="llms-editable-toggle-group">

			<label class="llms-switch">
				<span class="llms-label"><?php _e( 'Restrict File Types', 'lifterlms-advanced-quizzes' ); ?></span>
				<input type="checkbox" name="upload_restrict_filetypes"<# if ( 'yes' === data.get( 'upload_restrict_filetypes' ) ) { print( ' checked' ) } #>>
				<div class="llms-switch-slider"></div>
			</label>

			<# if ( 'yes' === data.get( 'upload_restrict_filetypes' ) ) {
				var selected = data.get( 'upload_filetypes' ); #>
				<div class="llms-editable-select">
					<select name="upload_filetypes" multiple="multiple">
						<?php foreach ( array_keys( get_allowed_mime_types() ) as $type ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
							<option value="<?php echo esc_attr( $type ); ?>"<# if ( -1 !== selected.indexOf( '<?php echo $type; ?>' ) ) { print( ' selected="selected"' ); } #>><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<# } #>

		</div>
	</div>
<# } #>
