<?php
/**
 * Short Answer Question Template.
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

$question_answer = $attempt && method_exists( $attempt, 'get_question_answer' ) ? $attempt->get_question_answer( $question->get( 'id' ) ) : array();
$answer          = is_array( $question_answer ) ? ( $question_answer[0] ?? '' ) : $question_answer;
?>

<div class="llms-aq-short-answer">
	<input class="llms-aq-short-answer-field" id="llms-question-answer-<?php echo $question->get( 'id' ); ?>" type="text" required="required" value="<?php echo esc_html( $answer ); ?>">
</div>
