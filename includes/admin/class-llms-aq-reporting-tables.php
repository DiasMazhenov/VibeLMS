<?php
/**
 * Modify reporting tables to add data specific to advanced quizzes
 *
 * @package LifterLMS_Advanced_Quizzes/Classes/Admin
 *
 * @since 1.0.8
 * @version 1.0.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_AQ_Reporting_Tables
 *
 * @since 1.0.8
 */
class LLMS_AQ_Reporting_Tables {

	/**
	 * Constructor
	 *
	 * @since 1.0.8
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'llms_table_get_quizzes_tr_classes', array( $this, 'get_quizzes_tr_classes' ), 10, 2 );
		add_filter( 'llms_table_get_data_quizzes', array( $this, 'get_quizzes_data' ), 10, 3 );
		add_filter( 'llms_table_get_quizzes_columns', array( $this, 'add_quizzes_cols' ) );

		add_filter( 'llms_table_get_quiz_attempts_tr_classes', array( $this, 'get_quiz_attempts_tr_classes' ), 10, 2 );

	}

	/**
	 * Adds a custom "Awaiting Review" column to the quizzes table
	 *
	 * @since 1.0.8
	 *
	 * @param array $cols Default columns.
	 * @return array
	 */
	public function add_quizzes_cols( $cols ) {

		return llms_assoc_array_insert(
			$cols,
			'lesson',
			'to_review',
			array(
				'exportable' => true,
				'title'      => __( 'Awaiting Review', 'lifterlms-advanced-quizzes' ),
				'sortable'   => false,
			)
		);

	}

	/**
	 * Retrieve the number of pending attempts for a given quiz
	 *
	 * @since 1.0.8
	 *
	 * @param obj $quiz LLMS_Quiz.
	 * @return int
	 */
	protected function get_pending_count( $quiz ) {

		$q_data = new LLMS_Quiz_Data( $quiz->get( 'id' ) );
		$q_data->set_period( 'all_time' );
		return $q_data->get_count_by_status( 'pending' );

	}

	/**
	 * Modify the TR class list of the Quizzes reporting table
	 * to embolden any quizzes that have at least one attempt pending review
	 *
	 * @since 1.0.8
	 *
	 * @param string $classes Css class list (space separated).
	 * @param obj    $row     Current row data object.
	 * @return string
	 */
	public function get_quiz_attempts_tr_classes( $classes, $row ) {

		if ( 'pending' === $row->get( 'status' ) ) {
			$classes .= ' llms-quiz-pending';
		}
		return $classes;

	}

	/**
	 * Retrieve data for custom `to_review` column for the quizzes table
	 *
	 * @since 1.0.8
	 *
	 * @param mixed  $value Default cell value.
	 * @param string $key   Table cell key name.
	 * @param obj    $row   WP_Post object for the quiz.
	 * @return mixed
	 */
	public function get_quizzes_data( $value, $key, $row ) {

		if ( 'to_review' === $key ) {
			$quiz  = llms_get_post( $row );
			$value = $this->get_pending_count( $quiz );
			if ( $value > 0 ) {
				$url   = LLMS_Admin_Reporting::get_current_tab_url(
					array(
						'tab'     => 'quizzes',
						'stab'    => 'attempts',
						'quiz_id' => $quiz->get( 'id' ),
					)
				);
				$value = '<a href="' . $url . '">' . $value . '</a>';
			}
		}

		return $value;

	}

	/**
	 * Modify the TR class list of the Quizzes reporting table
	 * to embolden any quizzes that have at least one attempt pending review
	 *
	 * @since 1.0.8
	 *
	 * @param string $classes CSS class list (space separated).
	 * @param obj    $row     Current row data object.
	 * @return string
	 */
	public function get_quizzes_tr_classes( $classes, $row ) {

		$quiz = llms_get_post( $row );
		if ( $this->get_pending_count( $quiz ) ) {
			$classes .= ' llms-quiz-pending';
		}
		return $classes;

	}

}

return new LLMS_AQ_Reporting_Tables();
