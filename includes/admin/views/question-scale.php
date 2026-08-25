<?php
/**
 * Scale question type features
 *
 * @package LifterLMS_Advanced_Quizzes/Admin/Views
 *
 * @since 1.0.0
 * @version 1.0.1
 */

defined( 'ABSPATH' ) || exit;
?>
<# if ( 'scale' === data.get( 'question_type' ).get( 'id' ) ) { #>
	<div class="llms-settings-row">

		<div class="llms-editable-toggle-group">
			<span class="llms-label"><?php _e( 'Minimum Value', 'lifterlms-advanced-quizzes' ); ?></span>
			<div class="llms-editable-number">
				<input class="llms-input standard" data-attribute="scale_min" data-original-content="{{{ data.get( 'scale_min' ) }}}" name="scale_min" type="number" value="{{{ data.get( 'scale_min' ) }}}">
			</div>
		</div>

		<div class="llms-editable-toggle-group">
			<span class="llms-label"><?php _e( 'Minimum Label', 'lifterlms-advanced-quizzes' ); ?></span>
			<input class="llms-input standard" data-attribute="scale_min_label" data-original-content="{{{ data.get( 'scale_min_label' ) }}}" name="scale_min_label" type="text" value="{{{ data.get( 'scale_min_label' ) }}}">
		</div>

		<div class="llms-editable-toggle-group">
			<span class="llms-label"><?php _e( 'Maximum Value', 'lifterlms-advanced-quizzes' ); ?></span>
			<div class="llms-editable-number">
				<input class="llms-input standard" data-attribute="scale_max" data-original-content="{{{ data.get( 'scale_max' ) }}}" name="scale_max" type="number" value="{{{ data.get( 'scale_max' ) }}}">
			</div>
		</div>

		<div class="llms-editable-toggle-group">
			<span class="llms-label"><?php _e( 'Maximum Label', 'lifterlms-advanced-quizzes' ); ?></span>
			<input class="llms-input standard" data-attribute="scale_max_label" data-original-content="{{{ data.get( 'scale_max_label' ) }}}" name="scale_max_label" type="text" value="{{{ data.get( 'scale_max_label' ) }}}">
		</div>

	</div>

	<div class="llms-settings-row">
		<div class="llms-editable-toggle-group">

			<label class="llms-switch">
				<span class="llms-label"><?php _e( 'Correct Answer', 'lifterlms-advanced-quizzes' ); ?></span>
				<input type="checkbox" name="auto_grade"<# if ( 'yes' === data.get( 'auto_grade' ) ) { print( ' checked' ) } #>>
				<div class="llms-switch-slider"></div>
			</label>

			<# if ( 'yes' === data.get( 'auto_grade' ) ) { #>
				<div class="llms-editable-number">
					<input class="llms-input standard" data-attribute="correct_value" data-original-content="{{{ data.get( 'correct_value' ) }}}" name="correct_value" type="number" value="{{{ data.get( 'correct_value' ) }}}">
				</div>
			<# } #>

		</div>
	</div>
<# } #>
