<?php
/**
 * Quiz Notification View: Quiz Awaiting Review
 *
 * @package LifterLMS_Advanced_Quizzes/Classes/Notifications
 *
 * @since 1.0.8
 * @version 1.0.9
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quiz Notification View: Quiz Awaiting Review
 *
 * @since 1.0.8
 */
class LLMS_AQ_Notification_View_Quiz_Awaiting_Review extends LLMS_Abstract_Notification_View_Quiz_Completion {

	/**
	 * Notification Trigger ID
	 *
	 * @var string
	 */
	public $trigger_id = 'quiz_awaiting_review';

	/**
	 * Setup body content for output
	 *
	 * @since 1.0.8
	 *
	 * @return string
	 */
	protected function set_body() {
		if ( 'email' === $this->notification->get( 'type' ) ) {
			return $this->set_body_email();
		}
		$content = __( 'Your quiz attempt is awaiting review by your instructor.', 'lifterlms-advanced-quizzes' );
		return $content;
	}

	/**
	 * Setup notification icon for output
	 *
	 * @since 1.0.8
	 *
	 * @return string
	 */
	protected function set_icon() {
		return $this->get_icon_default( 'warning' );
	}

	/**
	 * Setup notification subject for output
	 *
	 * @since 1.0.8
	 *
	 * @return string
	 */
	protected function set_subject() {
		// Translators: %1$s = Student Name, %2$s = Quiz Title.
		return sprintf( __( '%1$s quiz attempt for "%2$s" is awaiting your review', 'lifterlms-advanced-quizzes' ), '{{STUDENT_NAME}}', '{{QUIZ_TITLE}}' );
	}

	/**
	 * Setup notification title for output
	 *
	 * @since 1.0.8
	 *
	 * @return string
	 */
	protected function set_title() {
		if ( 'email' === $this->notification->get( 'type' ) ) {
			return __( 'Quiz Details', 'lifterlms-advanced-quizzes' );
		}
		return __( 'Quiz Submitted', 'lifterlms-advanced-quizzes' );
	}

}
