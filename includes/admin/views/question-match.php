<?php
/**
 * Matching question type features
 *
 * @package LifterLMS_Advanced_Quizzes/Admin/Views
 *
 * @since 1.0.2
 * @version 1.0.2
 */

defined( 'ABSPATH' ) || exit;

$options = array(
	'txt_txt' => esc_html__( 'Text to Text', 'lifterlms-advanced-quizzes' ),
	'txt_img' => esc_html__( 'Text to Image', 'lifterlms-advanced-quizzes' ),
	'img_img' => esc_html__( 'Image to Image', 'lifterlms-advanced-quizzes' ),
);

?>
<# if ( 'match' === data.get( 'question_type' ).get( 'id' ) ) {
	var selected = data.get( 'match_type' ) ? data.get( 'match_type' ) : 'txt_txt'; console.log( selected ); #>
	<div class="llms-settings-row">

		<div class="llms-editable-toggle-group">

			<div class="llms-editable-select">
				<span class="llms-label"><?php _e( 'Matching Type', 'lifterlms-advanced-quizzes' ); ?></span>
				<select name="match_type">
					<?php foreach ( $options as $key => $name ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<# if ( -1 !== selected.indexOf( '<?php echo $key; ?>' ) ) { print( ' selected="selected"' ); } #>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

		</div>
	</div>
<# } #>
