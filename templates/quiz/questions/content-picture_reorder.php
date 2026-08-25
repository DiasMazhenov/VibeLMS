<?php
/**
 * Picture choice question template.
 *
 * @package LifterLMS_Advanced_Quizzes/Templates
 *
 * @since 1.0.0
 * @since 1.0.10 Unknown.
 * @since 2.0.1 Fixed spacing for different sized images.
 * @since 3.3.0 Account for question answers.
 * @version 3.3.0
 *
 * @property LLMS_Quiz_Attempt $attempt  Quiz attempt instance.
 * @property LLMS_Question     $question Quiz question instance.
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
$cols = llms_get_picture_choice_question_cols( count( $choices ) );
?>

<ol class="llms-aq-reorder-list llms-question-choices llms-flex-cols" data-aq-cols="<?php echo $cols; ?>" data-aq-type="picture">
	<?php foreach ( $choices as $index => $choice ) : ?>

		<li class="llms-aq-reorder-item llms-choice type--picture llms-col-<?php echo absint( $cols ); ?>" id="choice-wrapper-<?php echo $choice->get( 'id' ); ?>">
			<label class="llms-aq-drag-handle" for="choice-<?php echo $choice->get( 'id' ); ?>">
				<input id="choice-<?php echo $choice->get( 'id' ); ?>" name="question_<?php echo $question->get( 'id' ); ?>[]" type="hidden" value="<?php echo $choice->get( 'id' ); ?>">
				<span class="llms-marker type--lister">
					<span class="iterator"><?php echo $index + 1; ?></span>
				</span>
				<div class="llms-choice-image"><?php echo $choice->get_image(); ?></div>
			</label>
		</li>

	<?php endforeach; ?>
</ol>
