<?php
/**
 * Notification Controller: Quiz Awaiting Review
 *
 * @package LifterLMS_Advanced_Quizzes/Classes/Notifications
 *
 * @since 1.0.8
 * @version 1.0.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notification Controller: Quiz Awaiting Review
 *
 * @since 1.0.8
 */
class LLMS_AQ_Notification_Controller_Quiz_Awaiting_Review extends LLMS_Abstract_Notification_Controller {

	/**
	 * Trigger Identifier
	 *
	 * @var string
	 */
	public $id = 'quiz_awaiting_review';

	/**
	 * Number of accepted arguments passed to the callback function
	 *
	 * @var integer
	 */
	protected $action_accepted_args = 2;

	/**
	 * Action hooks used to trigger sending of the notification
	 *
	 * @var array
	 */
	protected $action_hooks = array( 'lifterlms_quiz_pending' );

	/**
	 * Quiz instance.
	 *
	 * @var LLMS_Quiz
	 */
	protected $quiz;

	/**
	 * Determines if test notifications can be sent
	 *
	 * @var boolean[]
	 */
	protected $testable = array(
		'basic' => false,
		'email' => true,
	);

	/**
	 * Callback function called when a quiz is failed by a student
	 *
	 * @since 1.0.8
	 *
	 * @param int   $student_id WP User ID of a LifterLMS Student.
	 * @param array $quiz_id    WP Post ID of a LifterLMS quiz.
	 * @return void
	 */
	public function action_callback( $student_id = null, $quiz_id = null ) {

		$this->user_id = $student_id;
		$this->post_id = $quiz_id;
		$this->quiz    = llms_get_post( $quiz_id );
		if ( ! $this->quiz ) {
			return;
		}
		$this->course = $this->quiz->get_course();

		$this->send();

	}

	/**
	 * Get an array of LifterLMS Admin Page settings to send test notifications
	 *
	 * @since 1.0.8
	 *
	 * @param string $type Notification type [basic|email].
	 * @return array
	 */
	public function get_test_settings( $type ) {

		if ( 'email' !== $type ) {
			return;
		}

		$query = new LLMS_Query_Quiz_Attempt(
			array(
				'per_page' => 25,
				'status'   => 'pending',
			)
		);

		$options = array(
			'' => '',
		);

		$attempts = array();

		if ( $query->has_results() ) {
			foreach ( $query->get_attempts() as $attempt ) {
				$quiz    = llms_get_post( $attempt->get( 'quiz_id' ) );
				$student = llms_get_student( $attempt->get( 'student_id' ) );
				if ( $attempt && $student && $quiz ) {
					// Translators: %1$d = Attempt number; %2$s = Quiz title; %3$s = Student name.
					$options[ $attempt->get( 'id' ) ] = esc_attr( sprintf( __( 'Attempt #%1$d for Quiz "%2$s" by %3$s', 'lifterlms-advanced-quizzes' ), $attempt->get( 'id' ), $quiz->get( 'title' ), $student->get_name() ) );
				}
			}
		}

		return array(
			array(
				'class'             => 'llms-select2',
				'custom_attributes' => array(
					'data-allow-clear' => true,
					'data-placeholder' => __( 'Select a passed quiz', 'lifterlms-advanced-quizzes' ),
				),
				'default'           => '',
				'id'                => 'attempt_id',
				'desc'              => '<br/>' . __( 'Send yourself a test notification using information from the selected quiz.', 'lifterlms-advanced-quizzes' ),
				'options'           => $options,
				'title'             => __( 'Send a Test', 'lifterlms-advanced-quizzes' ),
				'type'              => 'select',
			),
		);
	}


	/**
	 * Takes a subscriber type (student, author, etc) and retrieves a User ID
	 *
	 * @since 1.0.8
	 *
	 * @param string $subscriber Subscriber type string.
	 * @return int|false
	 */
	protected function get_subscriber( $subscriber ) {

		switch ( $subscriber ) {

			case 'course_author':
				if ( $this->course ) {
					$uid = $this->course->get( 'author' );
				} else {
					$uid = $this->quiz->post->post_author;
				}
				break;

			case 'student':
				$uid = $this->user_id;
				break;

			default:
				$uid = false;

		}

		return $uid;

	}

	/**
	 * Get the translateable title for the notification
	 *
	 * Used on settings screens.
	 *
	 * @since 1.0.8
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Quizzes: Quiz Awaiting Review', 'lifterlms-advanced-quizzes' );
	}

	/**
	 * Send a test notification to the currently logged in users
	 *
	 * @since 1.0.8
	 *
	 * @param string $type Notification type [basic|email].
	 * @param array  $data Array of test notification data as specified by $this->get_test_data().
	 * @return int|false
	 */
	public function send_test( $type, $data = array() ) {

		if ( empty( $data['attempt_id'] ) ) {
			return;
		}

		$attempt       = new LLMS_Quiz_Attempt( $data['attempt_id'] );
		$this->user_id = $attempt->get( 'student_id' );
		$this->post_id = $attempt->get( 'quiz_id' );
		$this->quiz    = llms_get_post( $attempt->get( 'quiz_id' ) );
		$this->course  = $this->quiz->get_course();
		return parent::send_test( $type );

	}

	/**
	 * Setup the subscriber options for the notification
	 *
	 * @since 1.0.8
	 *
	 * @param string $type Notification type id.
	 * @return array
	 */
	protected function set_subscriber_options( $type ) {

		$options = array();

		switch ( $type ) {

			case 'basic':
				$options[] = $this->get_subscriber_option_array( 'student', 'yes' );
				break;

			case 'email':
				$options[] = $this->get_subscriber_option_array( 'course_author', 'yes' );
				$options[] = $this->get_subscriber_option_array( 'custom', 'no' );
				break;

		}

		return $options;

	}

}

return LLMS_AQ_Notification_Controller_Quiz_Awaiting_Review::instance();
