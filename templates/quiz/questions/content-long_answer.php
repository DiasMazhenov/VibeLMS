<?php
/**
 * Long Answer Question Template.
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

$min             = llms_parse_bool( $question->get( 'word_count_min' ) ) ? $question->get( 'words_min' ) : false;
$max             = llms_parse_bool( $question->get( 'word_count_max' ) ) ? $question->get( 'words_max' ) : false;
$question_answer = $attempt ? $attempt->get_question_answer( $question->get( 'id' ) ) : array();
$answer          = is_array( $question_answer ) ? ( $question_answer[0] ?? '' ) : $question_answer;
?>

<div class="llms-aq-long-answer">
	<div class="llms-aq-long-answer-field notranslate" data-words-max="<?php echo $max; ?>" data-words-min="<?php echo $min; ?>" id="llms-question-answer-<?php echo $question->get( 'id' ); ?>" required="required"><?php echo wp_kses_post( $answer ); ?></div>
</div>
