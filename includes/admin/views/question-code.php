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
<# if ( 'code' === data.get( 'question_type' ).get( 'id' ) ) {
	var selected = data.get( 'code_language' ) ? data.get( 'code_language' ) : 'javascript'; #>
	<div class="llms-settings-row">

		<div class="llms-editable-toggle-group">

			<div class="llms-editable-select">
				<span class="llms-label"><?php _e( 'Language / Syntax', 'lifterlms-advanced-quizzes' ); ?></span>
				<select name="upload_filetypes">
					<?php foreach ( llms_aq_get_cm_modes() as $key => $name ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<# if ( -1 !== selected.indexOf( '<?php echo $key; ?>' ) ) { print( ' selected="selected"' ); } #>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

		</div>
	</div>
<# } #>
