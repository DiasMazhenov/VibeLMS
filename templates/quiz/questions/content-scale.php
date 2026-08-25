<?php
/**
 * Scale Question Template.
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
$i               = $question->get( 'scale_min' );
$max             = $question->get( 'scale_max' );
$question_answer = $attempt ? $attempt->get_question_answer( $question->get( 'id' ) ) : array();
$answer          = is_array( $question_answer ) ? ( $question_answer[0] ?? '' ) : $question_answer;
?>

<div class="llms-aq-scale">

	<div class="llms-aq-scale-range">
		<?php while ( $i <= $max ) : ?>
			<label class="llms-aq-scale-radio" for="llms-scale-step-<?php echo esc_attr( $i ); ?>">
				<input
					id="llms-scale-step-<?php echo esc_attr( $i ); ?>"
					name="llms_aq_scale"
					type="radio"
					value="<?php echo esc_attr( $i ); ?>"
					<?php checked( $i, $answer ); ?>>
				<span class="llms-aq-scale-button"><?php echo esc_html( $i ); ?></span>
			</label>
			<?php
			++$i;
endwhile;
		?>
	</div>

	<?php if ( $question->get( 'scale_min_label' ) ) : ?>
		<label class="llms-aq-scale-label label--min" for="llms-question-answer-<?php echo esc_attr( $question->get( 'id' ) ); ?>">
			<?php echo esc_html( $question->get( 'scale_min_label' ) ); ?>
		</label>
	<?php endif; ?>

	<?php if ( $question->get( 'scale_max_label' ) ) : ?>
		<label class="llms-aq-scale-label label--max" for="llms-question-answer-<?php echo esc_attr( $question->get( 'id' ) ); ?>">
			<?php echo esc_html( $question->get( 'scale_max_label' ) ); ?>
		</label>
	<?php endif; ?>
</div>
