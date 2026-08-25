<?php
/**
 * LifterLMS Advanced Question Types
 *
 * @package LifterLMS_Advanced_Quizzes/Classes
 *
 * @since 1.0.0
 * @version 3.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_AQ_Question_Types class
 *
 * @since 1.0.0
 */
class LLMS_AQ_Question_Types {

	/**
	 * Initializer.
	 *
	 * @since 1.0.0
	 * @since 1.0.2 Unknown.
	 * @since 1.1.0 Add filter to handle case sensitivity during grading of a fill-in-the-blank question.
	 *
	 * @return void
	 */
	public static function init() {

		// Add custom settings to the builder.
		add_action( 'llms_builder_question_after_features', array( __CLASS__, 'output_features' ) );

		// Load question type definitions.
		add_filter( 'llms_get_question_types', array( __CLASS__, 'load' ), 8 );

		// Convert underscores to inputs for blank question type on fronend.
		add_filter( 'llms_blank_question_get_question', array( __CLASS__, 'add_blanks' ), 10, 4 );

		// Register properties with the LLMS_Question post model.
		add_filter( 'llms_get_question_properties', array( __CLASS__, 'register_properties' ), 10, 2 );
		// Ensure that only props relevant to the question type are added when converting a LLMS_Question to an array.
		add_filter( 'llms_get_question_to_array_properties', array( __CLASS__, 'to_array_props' ), 10, 2 );

		// Grade reorder questions.
		add_filter( 'llms_reorder_question_pre_grade', array( __CLASS__, 'grade_reorder' ), 10, 3 );
		add_filter( 'llms_picture_reorder_question_pre_grade', array( __CLASS__, 'grade_reorder' ), 10, 3 );

		// Conditionally disable case-sensitivity for fill in the blank questions.
		add_filter( 'llms_quiz_grading_case_sensitive', array( __CLASS__, 'maybe_enable_case_sensitivity' ), 10, 4 );

		// Handle output of answers on frontend/admin panel.
		add_filter( 'llms_quiz_attempt_question_get_answer_pre', array( __CLASS__, 'get_upload_answer' ), 10, 4 );
		add_filter( 'llms_quiz_attempt_question_get_answer_pre', array( __CLASS__, 'get_blank_answer' ), 10, 4 );
		add_filter( 'llms_quiz_attempt_question_get_answer_pre', array( __CLASS__, 'get_code_answer' ), 10, 3 );

		// Handle fill in the blank correct answer on frontend/admin panel.
		add_filter( 'llms_quiz_attempt_question_get_correct_answer', array( __CLASS__, 'get_blank_correct_answer' ), 10, 4 );
	}

	/**
	 * Convert underscores to inputs for fill in the blank questions.
	 *
	 * @since 1.0.0
	 * @since 3.3.0 Account for question answers.
	 *
	 * @param string            $question_text Question text (title).
	 * @param string            $format        Display format.
	 * @param LLMS_Question     $question      LLMS_Question instance.
	 * @param LLMS_Quiz_Attempt $attempt       LLMS_Quiz_Attempt instance.
	 * @return string
	 */
	public static function add_blanks( $question_text, $format, $question, $attempt ) {

		$question_answer = $attempt && method_exists( $attempt, 'get_question_answer' ) ? $attempt->get_question_answer( $question->get( 'id' ) ) : array();

		if ( 'html' !== $format ) {
			return $question_text;
		}

		preg_match_all( '/[_]{3,}/', $question_text, $matches );

		$blanks = $matches[0];

		foreach ( $blanks as $blank ) {
			$blank_len     = strlen( $blank );
			$input         = sprintf( '<input class="llms-aq-blank" required="required" style="width:%dpx" type="text" value="%s">', $blank_len * 22, esc_attr( is_array( $question_answer ) ? array_shift( $question_answer ) : '' ) );
			$pos           = strpos( $question_text, $blank );
			$question_text = substr_replace( $question_text, $input, $pos, $blank_len );
		}

		return $question_text;
	}

	/**
	 * Handle display of the "Answers" for blank question types.
	 *
	 * @since 1.0.2
	 *
	 * @param string                    $answer_string    Default answer string, should be an attachment ID for uploads.
	 * @param array                     $answers          Raw answers array.
	 * @param LLMS_Quiz_Question        $question         LLMS_Question instance.
	 * @param LLMS_Quiz_Attemp_Question $attempt_question LLMS_Quiz_Attemp_Question instance.
	 * @return string
	 */
	public static function get_blank_answer( $answer_string, $answers, $question, $attempt_question ) {

		// Don't proceed unless upload.
		if ( 'blank' !== $question->get( 'question_type' ) ) {
			return $answer_string;
		}

		$question_text = $question->get_question( 'plain' );

		preg_match_all( '/[_]{3,}/', $question_text, $matches );
		$blanks = $matches[0];

		foreach ( $blanks as $i => $blank ) {
			$blank_len     = strlen( $blank );
			$answer        = sprintf( '<u class="llms-aq-blank-answer"><b>&nbsp;%s&nbsp;</b></u>', $answers[ $i ] );
			$pos           = strpos( $question_text, $blank );
			$question_text = substr_replace( $question_text, $answer, $pos, $blank_len );
		}

		return $question_text;
	}

	/**
	 * Handle display of the correct "Answers" for blank question types.
	 *
	 * @since 1.0.2
	 *
	 * @param string                    $answer_string    Default answer string, should be an attachment ID for uploads.
	 * @param array                     $answers          Raw answers array.
	 * @param LLMS_Quiz_Question        $question         LLMS_Question instance.
	 * @param LLMS_Quiz_Attemp_Question $attempt_question LLMS_Quiz_Attemp_Question instance.
	 * @return string
	 */
	public static function get_blank_correct_answer( $answer_string, $answers, $question, $attempt_question ) {

		if ( 'blank' !== $question->get( 'question_type' ) ) {
			return $answer_string;
		}

		$question_text = $question->get_question( 'plain' );
		$answers       = $question->get_conditional_correct_value();

		preg_match_all( '/[_]{3,}/', $question_text, $matches );
		$blanks = $matches[0];

		foreach ( $blanks as $i => $blank ) {
			$blank_len     = strlen( $blank );
			$answer        = sprintf( '<u class="llms-aq-blank-answer"><b>&nbsp;%s&nbsp;</b></u>', $answers[ $i ] );
			$pos           = strpos( $question_text, $blank );
			$question_text = substr_replace( $question_text, $answer, $pos, $blank_len );
		}

		return $question_text;
	}

	/**
	 * Retrieve all the question types loaded by the LifterLMS AQ.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private static function get_types() {

		$types = array();

		$types['blank'] = array(
			'clarifications' => false,
			'choices'        => false,
			'grading'        => 'conditional',
			'placeholder'    => esc_attr__( 'Enter your question... (3 or more underscores make a blank)', 'lifterlms-advanced-quizzes' ),
			'properties'     => array(
				'auto_grade'     => 'yesno',
				'correct_value'  => 'string',
				'case_sensitive' => 'yesno',
			),
			'upgrade'        => false,
		);

		$types['reorder'] = array(
			'choices' => array(
				'selectable' => false,
				'markers'    => range( 1, 26 ),
				'max'        => 26,
				'min'        => 2,
				'multi'      => true,
				'type'       => 'text',
			),
			'upgrade' => false,
		);

		$types['picture_reorder'] = array(
			'choices' => array(
				'selectable' => false,
				'markers'    => range( 1, 26 ),
				'max'        => 26,
				'min'        => 2,
				'multi'      => true,
				'type'       => 'image',
			),
			'upgrade' => false,
		);

		$types['short_answer'] = array(
			'choices'        => false,
			'clarifications' => false,
			'grading'        => 'manual',
			'upgrade'        => false,
		);

		$types['long_answer'] = array(
			'choices'        => false,
			'clarifications' => false,
			'grading'        => 'manual',
			'properties'     => array(
				'word_count_min' => 'yesno',
				'word_count_max' => 'yesno',
				'words_min'      => 'absint',
				'words_max'      => 'absint',
			),
			'upgrade'        => false,
		);

		$types['upload'] = array(
			'choices'        => false,
			'clarifications' => false,
			'grading'        => 'manual',
			'properties'     => array(
				'upload_filetypes'          => 'array',
				'upload_restrict_filetypes' => 'yesno',
			),
			'upgrade'        => false,
		);

		$types['code'] = array(
			'choices'        => false,
			'clarifications' => false,
			'grading'        => 'manual',
			'properties'     => array(
				'code_language' => 'string',
			),
			'upgrade'        => false,
		);

		$types['scale'] = array(
			'choices'    => false,
			'grading'    => 'conditional',
			'properties' => array(
				'auto_grade'      => 'yesno',
				'correct_value'   => 'string',
				'scale_min'       => 'absint',
				'scale_min_label' => 'string',
				'scale_max'       => 'absint',
				'scale_max_label' => 'string',
			),
			'upgrade'    => false,
		);

		return $types;
	}

	/**
	 * Handle display of the "Answers" for upload question types.
	 *
	 * Images get embedded as an image, all others get converted to a download anchor.
	 *
	 * @since 1.0.1
	 * @since 1.0.2 Unknown.
	 *
	 * @param string                    $answer_string    Default answer string, should be an attachment ID for uploads.
	 * @param array                     $answers          Raw answers array.
	 * @param LLMS_Question             $question         LLMS_Question instance.
	 * @param LLMS_Quiz_Attemp_Question $attempt_question LLMS_Quiz_Attemp_Question instance.
	 * @return string
	 */
	public static function get_upload_answer( $answer_string, $answers, $question, $attempt_question ) {

		// Don't proceed unless upload.
		if ( 'upload' !== $question->get( 'question_type' ) ) {
			return $answer_string;
		}

		$answer_string = array_pop( $answers );

		// If saved value isn't numeric something is weird.
		if ( ! is_numeric( $answer_string ) ) {
			return $answer_string;
		}

		// Return <img> for an image.
		if ( wp_attachment_is_image( $answer_string ) ) {

			return wp_get_attachment_image( $answer_string, 'full' );

		}

		// Otherwise return a download <a>.
		$upload_url = wp_get_attachment_url( $answer_string );
		// Translators: %d = Quiz question order.
		$name = sprintf( esc_attr__( 'Question #%d Upload', 'lifterlms-advanced-quizzes' ), $question->get( 'menu_order' ) );
		return '<a href="' . esc_url( $upload_url ) . '" download="' . $name . '">' . $name . '</a>';
	}

	/**
	 * Handle display of the "Answers" for code question types.
	 *
	 * @since 3.0.0
	 *
	 * @param string        $answer_string Default answer string.
	 * @param array         $answers       Raw answers array.
	 * @param LLMS_Question $question      LLMS_Question obj.
	 * @return string
	 */
	public static function get_code_answer( $answer_string, $answers, $question ) {

		// Don't proceed unless upload.
		if ( 'code' !== $question->get( 'question_type' ) ) {
			return $answer_string;
		}

		$answer_string = array_pop( $answers );

		return '<pre><code>' . htmlentities( $answer_string, ENT_QUOTES ) . '</code></pre>';
	}

	/**
	 * Grade a reorder question.
	 *
	 * Basically the same as grading a multi-correct option choice question
	 * except that we don't want to sort the answer for comparison, if we do sort
	 * it then it's always right correct.
	 *
	 * @since 1.0.0
	 *
	 * @param null          $correct  Correct value (null during pre, returns yes/no bool).
	 * @param array         $answer   Student-submitted choice ids as an indexed array.
	 * @param LLMS_Question $question Instance of the LLMS_Question.
	 * @return string
	 */
	public static function grade_reorder( $correct, $answer, $question ) {

		return ( $answer === $question->get_correct_choice() ) ? 'yes' : 'no';
	}

	/**
	 * Load core question types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $questions Array of question types (probably empty).
	 * @return array
	 */
	public static function load( $questions ) {

		$model = LLMS_Question_Types::get_model();

		foreach ( self::get_types() as $id => $type ) {

			$default          = isset( $questions[ $id ] ) ? $questions[ $id ] : $model;
			$questions[ $id ] = wp_parse_args( $type, $default );

		}

		return $questions;
	}

	/**
	 * Conditionally enable grading case sensitivity for fill in the blank question types.
	 *
	 * @since 1.1.0
	 *
	 * @param boolean       $case_sensitive Whether or not answers are treated as case-sensitive.
	 * @param string[]      $answer         User-submitted answers.
	 * @param string[]      $correct        Correct answers.
	 * @param LLMS_Question $question       Question object.
	 * @return boolaen
	 */
	public static function maybe_enable_case_sensitivity( $case_sensitive, $answer, $correct, $question ) {

		if ( 'blank' === $question->get( 'question_type' ) && llms_parse_bool( $question->get( 'case_sensitive' ) ) ) {
			$case_sensitive = true;
		}

		return $case_sensitive;
	}

	/**
	 * Outpuc JS template parts for question type-specific settings on the Course Builder.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_features() {

		include LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/views/question-blank.php';
		include LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/views/question-code.php';
		include LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/views/question-long-answer.php';
		include LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/views/question-match.php';
		include LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/views/question-scale.php';
		include LLMS_ADVANCED_QUIZZES_PLUGIN_DIR . 'includes/admin/views/question-upload.php';
	}

	/**
	 * Add all question type properties for getting/setting.
	 *
	 * @since 1.0.0
	 *
	 * @param array         $props    Default question props.
	 * @param LLMS_Question $question LLMS_Question instance.
	 * @return array
	 */
	public static function register_properties( $props, $question ) {

		foreach ( self::get_types() as $type ) {
			if ( isset( $type['properties'] ) ) {
				$props = wp_parse_args( $type['properties'], $props );
			}
		}

		return $props;
	}

	/**
	 * When converting question to array, ensure only props applicable to the question type are included.
	 *
	 * @since 1.0.0
	 *
	 * @param array         $props    Indexed array of property keys.
	 * @param LLMS_Question $question Instance of the LLMS_Question.
	 * @return array
	 */
	public static function to_array_props( $props, $question ) {

		$curr_type = null;

		foreach ( self::get_types() as $type => $data ) {

			if ( ! isset( $data['properties'] ) ) {
				continue;
			} elseif ( $type === $question->get( 'question_type' ) ) {
				$curr_type = $data;
			}

			$props = array_diff( $props, array_keys( $data['properties'] ) );

		}

		if ( $curr_type ) {

			$props = array_merge( $props, array_diff( array_keys( $curr_type['properties'] ), $props ) );

		}

		return $props;
	}
}

LLMS_AQ_Question_Types::init();
