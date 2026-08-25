<?php
/**
 * Code Question Template.
 *
 * @package LifterLMS_Advanced_Quizzes/Templates
 *
 * @since 1.0.0
 * @since 3.3.0 Account for question answers.
 * @version 3.3.0
 *
 * @property LLMS_Quiz_Attempt $attempt  Attempt object.
 * @property LLMS_Question     $question Question object.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter to modify the CodeMirror settings for the Code Question.
 *
 * @since 1.0.0
 *
 * @param array         $settings Array of settings.
 * @param LLMS_Question $question Question object.
 */
$settings = apply_filters(
	'llms_aq_code_question_cm_settings',
	array(
		'assets_path'    => plugins_url( '/assets/vendor/codemirror/', LLMS_ADVANCED_QUIZZES_PLUGIN_FILE ),
		'mode'           => $question->get( 'code_language' ) ? $question->get( 'code_language' ) : 'javascript',
		'tabSize'        => 4,
		'lineNumbers'    => true,
		'indentWithTabs' => true,
		'autofocus'      => true,
		'theme'          => 'eclipse',
	),
	$question
);
$question_answer = $attempt ? $attempt->get_question_answer( $question->get( 'id' ) ) : '';
$answer          = is_array( $question_answer ) ? ( $question_answer[0] ?? '' ) : $question_answer;
?>

<div class="llms-aq-code">
	<textarea class="llms-aq-code-field" id="llms-question-answer-<?php echo $question->get( 'id' ); ?>" required="required"><?php echo esc_html( $answer ); ?></textarea>
	<script id="llms-aq-code-settings" type="text/javascript">window.llms.advanced_quizzes.cm_settings = JSON.parse( '<?php echo wp_json_encode( $settings ); ?>' );</script>
</div>
