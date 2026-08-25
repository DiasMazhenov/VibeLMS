<?php
/**
 * Reorder Items question template.
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

$question_answer = $attempt ? $attempt->get_question_answer( $question->get( 'id' ) ) : '';

if ( $question_answer ) {
	// Updating Choices array to match the order of the $question_answer array.
	$choices = array();
	foreach ( $question_answer as $answer ) {
		$choices[] = $question->get_choice( $answer );
	}
} else {
	$choices = $question->get_choices();
	if ( function_exists( 'llms_shuffle_choices' ) ) {
		$choices = llms_shuffle_choices( $choices );
	} else {
		shuffle( $choices );
	}
}
?>

<ol class="llms-aq-reorder-list llms-question-choices" data-aq-type="text">
	<?php foreach ( $choices as $index => $choice ) : ?>

		<li class="llms-choice type--text llms-aq-reorder-item" id="choice-wrapper-<?php echo $choice->get( 'id' ); ?>">
			<label for="choice-<?php echo $choice->get( 'id' ); ?>">
				<input id="choice-<?php echo $choice->get( 'id' ); ?>" name="question_<?php echo $question->get( 'id' ); ?>[]" type="hidden" value="<?php echo $choice->get( 'id' ); ?>">
				<span class="llms-marker type--lister llms-aq-drag-handle">
					<span class="iterator"><?php echo $index + 1; ?></span>
				</span>
				<p class="llms-choice-text"><?php echo $choice->get( 'choice' ); ?></p>
			</label>
		</li>

	<?php endforeach; ?>
</ol>
