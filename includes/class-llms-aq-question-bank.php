<?php
/**
 * LifterLMS Question Bank
 *
 * @package LifterLMS_Advanced_Quizzes/Classes
 *
 * @since 3.1.0
 * @version 3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_AQ_Question_Bank class.
 *
 * @since 3.1.0
 */
class LLMS_AQ_Question_Bank {

	/**
	 * Initializer.
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Use new filter hook `llms_quiz_attempt_questions_array` of `llms_quiz_attempt_questions`.
	 *              to fix issues with questions limit.
	 *
	 * @return void
	 */
	public static function init() {

		add_filter( 'llms_get_quiz_properties', array( __CLASS__, 'register_question_bank_properties' ) );
		add_filter( 'llms_get_quiz_property_defaults', array( __CLASS__, 'register_question_bank_properties_defaults' ) );

		add_filter( 'llms_quiz_attempt_questions_randomize', array( __CLASS__, 'maybe_quiz_randomize' ), 10, 2 );
		add_filter( 'llms_quiz_attempt_questions_array', array( __CLASS__, 'modify_attempt_questions_array' ), 10, 2 );
		add_filter( 'llms_quiz_questions_count', array( __CLASS__, 'update_question_count' ), 10, 2 );

		add_filter( 'llms_get_question_properties', array( __CLASS__, 'register_properties' ) );
		add_action( 'llms_builder_question_after_features', array( __CLASS__, 'add_include_question_bank_control' ) );

	}

	/**
	 * Register question bank properties.
	 *
	 * @since 3.1.0
	 *
	 * @param array $properties Array of properties.
	 * @return array
	 */
	public static function register_question_bank_properties( $properties ) {

		$properties['question_bank']        = 'yesno';
		$properties['qb_n_questions']       = 'int';
		$properties['qb_n_questions_types'] = 'yesno';
		$properties['qb_n_questions_type']  = 'array';

		return $properties;
	}

	/**
	 * Register question bank property defaults.
	 *
	 * @since 3.1.0
	 *
	 * @param array $properties Array of properties' defaults.
	 * @return array
	 */
	public static function register_question_bank_properties_defaults( $properties ) {

		$properties['question_bank']        = 'no';
		$properties['qb_n_questions']       = 1;
		$properties['qb_n_questions_types'] = 'no';

		$question_types                    = array_keys( llms_get_question_types() );
		$properties['qb_n_questions_type'] = array_combine(
			$question_types,
			array_fill( 0, count( $question_types ), 0 )
		);

		return $properties;
	}

	/**
	 * Enables randomize for quiz's questions.
	 *
	 * @since 3.1.0
	 *
	 * @param bool      $randomize Whether or not to randomize.
	 * @param LLMS_Quiz $quiz      LLMS_Quiz instance.
	 * @return bool
	 */
	public static function maybe_quiz_randomize( $randomize, $quiz ) {

		return $randomize ? $randomize : llms_parse_bool( $quiz->get( 'question_bank' ) );

	}

	/**
	 * Set quiz attempt questions.
	 *
	 * @since 3.1.0
	 * @deprecated 3.2.0
	 *
	 * @param array     $questions Array of questions.
	 * @param LLMS_Quiz $quiz      LLMS_Quiz instance.
	 * @return array
	 */
	public static function set_quiz_questions( $questions, $quiz ) {

		llms_deprecated_function( __METHOD__, '3.2.0' );

		if ( empty( $questions ) || ! llms_parse_bool( $quiz->get( 'question_bank' ) ) ) {
			return $questions;
		}

		// Limiting the number of questions if the question bank is enabled and the limit is set.
		$questions_limit = min( count( $questions ), (int) $quiz->get( 'qb_n_questions' ) );
		if ( $questions_limit < 1 ) {
			return $questions;
		}

		$quiz_questions = [];

		// Get all required questions to be included in the quiz.
		foreach ( $questions as $index => $question ) {
			if ( llms_parse_bool( $question->get( 'include_in_variations' ) ) ) {
				$quiz_questions[ $index ] = $question;
				$questions_limit--;
				if ( 0 === $questions_limit ) { // Limit reached: bail.
					return array_values( $quiz_questions );
				}
			}
		}

		// Removing required questions from questions array.
		$questions = ! empty( $quiz_questions ) ? array_diff_key( $questions, $quiz_questions ) : $questions;

		// No other questions to add: bail.
		if ( empty( $questions ) ) {
			return array_values( $quiz_questions );
		}

		// Add questions from multiple types if enabled.
		if ( llms_parse_bool( $quiz->get( 'qb_n_questions_types' ) ) ) {

			// Get all requested question types values, filtering out unlimited (when the number per type is 0).
			$question_types = array_filter( $quiz->get( 'qb_n_questions_type' ) );

			// Getting all questions of the selected types.
			foreach ( $question_types as $type => $value ) {

				$iterations = 0;

				foreach ( $questions as $index => $question ) {

					if ( $iterations === $value ) {
						break;
					}

					if ( $question->get( 'question_type' ) === $type ) {
						$quiz_questions[ $index ] = $question;
						$iterations++;
						$questions_limit--;
					}

					// Limit reached: bail.
					if ( ! $questions_limit ) {
						return array_values( $quiz_questions );
					}
				}
			}
		}

		// Get all questions except the questions already added.
		$questions = ! empty( $quiz_questions ) ? array_diff_key( $questions, $quiz_questions ) : $questions;

		// No other questions to add: bail.
		if ( empty( $questions ) ) {
			return array_values( $quiz_questions );
		}

		// Get the number of questions left in limit to be added to the bank.
		$questions_left = array_slice( $questions, 0, $questions_limit );
		$quiz_questions = array_merge( $quiz_questions, $questions_left );

		return array_values( $quiz_questions );

	}

	/**
	 * Set quiz attempt questions array.
	 *
	 * @since 3.2.0
	 *
	 * @param array     $questions Array of questions (each question is an array).
	 * @param LLMS_Quiz $quiz      LLMS_Quiz instance.
	 * @return array
	 */
	public static function modify_attempt_questions_array( $questions, $quiz ) {

		if ( empty( $questions ) || ! llms_parse_bool( $quiz->get( 'question_bank' ) ) ) {
			return $questions;
		}

		// Limiting the number of questions if the question bank is enabled and the limit is set.
		$questions_limit = min( count( $questions ), (int) $quiz->get( 'qb_n_questions' ) );
		if ( $questions_limit < 1 ) {
			return $questions;
		}

		// Randomize questions prior to process required questions.
		$questions = LLMS_Quiz_Attempt::randomize_attempt_questions( $questions );

		$quiz_questions = [];

		// Get all required questions to be included in the quiz.
		foreach ( $questions as $index => $question_array ) {
			$question = llms_get_post( $question_array['id'] );
			if ( llms_parse_bool( $question->get( 'include_in_variations' ) ) ) {
				$quiz_questions[ $index ] = $question_array;
				$questions_limit--;
				if ( 0 === $questions_limit ) { // Limit reached: bail.
					return array_values( $quiz_questions );
				}
			}
		}

		// Removing required questions from questions array.
		$questions = ! empty( $quiz_questions ) ? array_diff_key( $questions, $quiz_questions ) : $questions;

		// No other questions to add: bail.
		if ( empty( $questions ) ) {
			return array_values( $quiz_questions );
		}

		// Add questions from multiple types if enabled.
		if ( llms_parse_bool( $quiz->get( 'qb_n_questions_types' ) ) ) {

			// Get all requested question types values, filtering out unlimited (when the number per type is 0).
			$question_types = array_filter( $quiz->get( 'qb_n_questions_type' ) );

			// Getting all questions of the selected types.
			foreach ( $question_types as $type => $value ) {

				$iterations = 0;

				foreach ( $questions as $index => $question_array ) {

					if ( $iterations === $value ) {
						break;
					}

					$question = llms_get_post( $question_array['id'] );
					if ( $question->get( 'question_type' ) === $type ) {
						$quiz_questions[ $index ] = $question_array;
						$iterations++;
						$questions_limit--;
					}

					// Limit reached: bail.
					if ( ! $questions_limit ) {
						return array_values( $quiz_questions );
					}
				}
			}
		}

		// Get all questions except the questions already added.
		$questions = ! empty( $quiz_questions ) ? array_diff_key( $questions, $quiz_questions ) : $questions;

		// No other questions to add: bail.
		if ( empty( $questions ) ) {
			return array_values( $quiz_questions );
		}

		// Get the number of questions left in limit to be added to the bank.
		$questions_left = array_slice( $questions, 0, $questions_limit );
		$quiz_questions = array_merge( $quiz_questions, $questions_left );

		return array_values( $quiz_questions );

	}

	/**
	 * Update question count.
	 *
	 * @since 3.1.0
	 *
	 * @param int       $count Number of questions.
	 * @param LLMS_Quiz $quiz  LLMS_Quiz instance.
	 * @return int
	 */
	public static function update_question_count( $count, $quiz ) {

		$question_bank = llms_parse_bool( $quiz->get( 'question_bank' ) );

		if ( $question_bank ) {

			$questions_limit = $quiz->get( 'qb_n_questions' );

			if ( $questions_limit ) {
				$count = min( $questions_limit, $count );
			}
		}

		return $count;
	}

	/**
	 * Adding question bank properties for getting/setting.
	 *
	 * @since 3.1.0
	 *
	 * @param array $props Default question props.
	 * @return array
	 */
	public static function register_properties( $props ) {

		$props['include_in_variations'] = 'yesno';
		return $props;

	}

	/**
	 * Add 'Include in all variations' control for every question type.
	 *
	 * @since 3.1.0
	 *
	 * @return void
	 */
	public static function add_include_question_bank_control() {

		?>
		<# if ( 'yes' === data.collection.parent.attributes.question_bank ) { #>
			<div class="llms-settings-row">
				<div class="llms-editable-toggle-group">
					<label class="llms-switch">
						<span class="llms-label"><?php esc_html_e( 'Include in all quiz variations', 'lifterlms-advanced-quizzes' ); ?></span>
						<input type="checkbox" name="include_in_variations"<# if ( 'yes' === data.get( 'include_in_variations' ) ) { print( ' checked' ) } #>>
						<div class="llms-switch-slider"></div>
					</label>
				</div>
			</div>
		<# } #>
		<?php

	}

}

LLMS_AQ_Question_Bank::init();
