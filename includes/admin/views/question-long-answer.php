<?php
/**
 * Long Answer question type features
 *
 * @package LifterLMS_Advanced_Quizzes/Admin/Views
 *
 * @since 1.0.0
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<# if ( 'long_answer' === data.get( 'question_type' ).get( 'id' ) ) { #>
	<div class="llms-settings-row">

		<div class="llms-editable-toggle-group">

			<label class="llms-switch">
				<span class="llms-label"><?php _e( 'Word Count (Minimum)', 'lifterlms-advanced-quizzes' ); ?></span>
				<input type="checkbox" name="word_count_min"<# if ( 'yes' === data.get( 'word_count_min' ) ) { print( ' checked' ) } #>>
				<div class="llms-switch-slider"></div>
			</label>

			<# if ( 'yes' === data.get( 'word_count_min' ) ) { #>
				<div class="llms-editable-number">
					<input class="llms-input standard" data-attribute="words_min" data-original-content="{{{ data.get( 'words_min' ) }}}" min="1" name="words_min" type="number" value="{{{ data.get( 'words_min' ) }}}">
				</div>
			<# } #>

		</div>

		<div class="llms-editable-toggle-group">

			<label class="llms-switch">
				<span class="llms-label"><?php _e( 'Word Count (Maximum)', 'lifterlms-advanced-quizzes' ); ?></span>
				<input type="checkbox" name="word_count_max"<# if ( 'yes' === data.get( 'word_count_max' ) ) { print( ' checked' ) } #>>
				<div class="llms-switch-slider"></div>
			</label>

			<# if ( 'yes' === data.get( 'word_count_max' ) ) { #>
				<div class="llms-editable-number">
					<input class="llms-input standard" data-attribute="words_max" data-original-content="{{{ data.get( 'words_max' ) }}}" min="1" name="words_max" type="number" value="{{{ data.get( 'words_max' ) }}}">
				</div>
			<# } #>

		</div>

	</div>
<# } #>
