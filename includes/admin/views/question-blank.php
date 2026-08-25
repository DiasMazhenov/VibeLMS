<?php
/**
 * Fill in the blank question type features
 *
 * @package LifterLMS_Advanced_Quizzes/Admin/Views
 *
 * @since 1.0.0
 * @version 1.1.0
 */

defined( 'ABSPATH' ) || exit;
?>
<# if ( 'blank' === data.get( 'question_type' ).get( 'id' ) ) { #>
	<div class="llms-settings-row">
		<div class="llms-editable-toggle-group">

			<label class="llms-switch">
				<span class="llms-label"><?php _e( 'Correct Answer', 'lifterlms-advanced-quizzes' ); ?></span>
				<input type="checkbox" name="auto_grade"<# if ( 'yes' === data.get( 'auto_grade' ) ) { print( ' checked' ) } #>>
				<div class="llms-switch-slider"></div>
			</label>

			<# if ( 'yes' === data.get( 'auto_grade' ) ) { #>
				<div class="llms-editable tip--top-right" data-tip="<?php esc_attr_e( 'Separate multiple blanks with a pipe \'|\' character', 'lifterlms-advanced-quizzes' ); ?>">
					<input class="llms-input standard" data-attribute="correct_value" data-original-content="{{{ data.get( 'correct_value' ) }}}" name="correct_value" type="text" value="{{{ data.get( 'correct_value' ) }}}">
				</div>

				<label class="llms-switch">
					<span class="llms-label" style="text-align: right;">
						<# if ( 'yes' === data.get( 'case_sensitive' ) ) { #>
							<?php _e( 'Answers are case-sensitive', 'lifterlms-advanced-quizzes' ); ?>
						<# } else { #>
							<?php _e( 'Answers are not case-sensitive', 'lifterlms-advanced-quizzes' ); ?>
						<# } #>
					</span>
					<input type="checkbox" name="case_sensitive"<# if ( 'yes' === data.get( 'case_sensitive' ) ) { print( ' checked' ) } #>>
					<div class="llms-switch-slider"></div>
				</label>
			<# } #>

		</div>
	</div>
<# } #>
