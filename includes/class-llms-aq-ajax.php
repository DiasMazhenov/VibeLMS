<?php
/**
 * AJAX Functios for the add-on
 *
 * @package LifterLMS_Advanced_Quizzes/Classes
 *
 * @since 1.0.0
 * @version 3.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_AQ_AJAX class
 *
 * @since 1.0.0
 * @since 1.1.0 Refactored `upload_file()`.
 */
class LLMS_AQ_AJAX {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {

		$actions = array(
			'upload_file' => false,
		);

		// Register all ajax functions.
		foreach ( $actions as $action => $nopriv ) {

			add_action( 'wp_ajax_llms_aq_' . $action, array( $this, $action ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_llms_aq_' . $action, array( $this, $action ) );
			}
		}
	}

	/**
	 * Upload a file to the media library.
	 *
	 * @since 1.1.0
	 *
	 * @param LLMS_Question $question Question object.
	 * @param LLMS_Student  $student  Student object.
	 * @return int|WP_Error WP_Post ID of the attachment post on success or an error object on failure.
	 */
	private function do_file_upload( $question, $student ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		/**
		 * Filter overrides passed to `media_handle_upload()` during an assignment submission file upload.
		 *
		 * This filter is mainly used to disable some file tests (like `is_uploaded_file()`) encountered in `_wp_handle_upload()`
		 * which causes issues when faking an HTTP file upload by modifying the $_FILES global like we do during phpunit testing.
		 *
		 * @since 1.1.0
		 *
		 * @param array $overrides Array of overrides.
		 */
		$overrides = apply_filters(
			'llms_aq_upload_question_answer_media_handle_upload_overrides',
			array(
				'test_form' => false,
			)
		);

		// Translators: %1$s = Question title/text; %2$s = The student's name.
		$post_title = sprintf( esc_html__( '%1$s answer by %2$s', 'lifterlms-advanced-quizzes' ), wp_strip_all_tags( $question->get( 'title' ) ), $student->get_name() );

		/**
		 * Filter the attachment post title for a file uploaded for a quiz upload question
		 *
		 * @since 1.1.0
		 *
		 * @param string        $post_title The title of the attachment post.
		 * @param LLMS_Question $question   Question object.
		 * @param LLMS_Student  $student    Student object.
		 */
		$post_title = apply_filters( 'llms_aq_upload_question_attachment_title', $post_title, $question, $student );

		// Upload the situation.
		return media_handle_upload(
			'file',
			$question->get( 'id' ),
			compact( 'post_title' ),
			$overrides
		);
	}

	/**
	 * Verify the parameters for an ajax call
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Fix spelling error.
	 *              Use `llms_filter_input()` to retrieve $_POST data.
	 *
	 * @param array $params Associative array of param_key => validation function.
	 * @return void
	 */
	private function verify_parameters( $params ) {

		foreach ( $params as $var => $func ) {

			if ( ! isset( $_POST[ $var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in `upload_file()`
				// Translators: %s = Missing paramter name.
				$this->send_response( sprintf( esc_html__( 'Missing required parameter: "%s"', 'lifterlms-advanced-quizzes' ), $var ), false );
			}

			$val = llms_filter_input( INPUT_POST, $var, FILTER_UNSAFE_RAW );
			if ( ! $func( $val ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in `upload_file()`
				// Translators: %s = Missing paramter name.
				$this->send_response( sprintf( esc_html__( 'Invalid value submitted for parameter: "%s"', 'lifterlms-advanced-quizzes' ), $var ), false );
			}
		}
	}

	/**
	 * Verify an uploaded file is allowed by the question's filetype restriction settings
	 *
	 * @since 1.1.0
	 *
	 * @param LLMS_Question $question Question object.
	 * @return boolean
	 */
	private function verify_filetype( $question ) {

		if ( llms_parse_bool( $question->get( 'upload_restrict_filetypes' ) ) ) {

			$name = ! empty( $_FILES['file']['name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) ) : '';
			$ext  = '.' . pathinfo( $name, PATHINFO_EXTENSION );
			if ( ! in_array( $ext, llms_aq_mimes_to_array( $question->get( 'upload_filetypes' ) ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Uploads upload question answers.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Major refactor.
	 * @since 3.0.0 PHP 8.1 compat: don't use deprecated `FILTER_SANITIZE_STRING`.
	 *              Validate the attempt key is a valid attempt for the current user and ensure the question is a question
	 *              found in the attempt's question array.
	 * @since 3.3.0 Delete old media when new file uploaded.
	 *
	 * @return void
	 */
	public function upload_file() {

		check_ajax_referer( 'llms_aq_upload', 'nonce' );

		$this->verify_parameters(
			array(
				'attempt_key' => 'is_string',
				'question_id' => 'is_numeric',
			)
		);

		$student = llms_get_student();
		if ( ! $student ) {
			$this->send_response( esc_html__( 'Invalid user.', 'lifterlms-advanced-quizzes' ), false );
		}

		$key         = llms_filter_input_sanitize_string( INPUT_POST, 'attempt_key' );
		$question_id = (int) llms_filter_input( INPUT_POST, 'question_id', FILTER_SANITIZE_NUMBER_INT );

		// Attempt must exist and belong to the current user.
		$attempt = $student->quizzes()->get_attempt_by_key( $key );
		if ( ! $attempt || $student->get_id() !== (int) $attempt->get( 'student_id' ) || 'incomplete' !== $attempt->get( 'status' ) ) {
			$this->send_response( esc_html__( 'Invalid quiz attempt.', 'lifterlms-advanced-quizzes' ), false );
		}

		// Question must exist and belong to the current attempt.
		$question = llms_get_post( $question_id );
		if ( ! $question || ! in_array( $question_id, array_column( $attempt->get_questions(), 'id' ), true ) ) {
			$this->send_response( esc_html__( 'Invalid question.', 'lifterlms-advanced-quizzes' ), false );
		}

		if ( ! $this->verify_filetype( $question ) ) {
			$this->send_response( esc_html__( 'Invalid filetype.', 'lifterlms-advanced-quizzes' ), false );
		}

		$id = $this->do_file_upload( $question, $student );
		if ( is_wp_error( $id ) ) {
			$this->send_response( $id->get_error_message(), false, $id );
		}

		if ( method_exists( $attempt, 'can_be_resumed' ) && ( $attempt->get_quiz()->can_be_resumed() && ! $attempt->can_be_resumed() ) ) {
			$this->send_response( esc_html__( 'Invalid attempt.', 'lifterlms-advanced-quizzes' ), false );
		}

		// Deleting previously uploaded media from current user in current attempt.
		if ( method_exists( $attempt, 'get_question_answer' ) ) {
			$prev_id = $attempt->get_question_answer( $question_id );
			if ( $prev_id ) {
				wp_delete_attachment( $prev_id[0], true );
			}
		}

		$this->send_response(
			esc_html__( 'Success', 'lifterlms-advanced-quizzes' ),
			true,
			array(
				'attachment_id' => $id,
			)
		);
	}


	/**
	 * Send a JSON response (&die)
	 *
	 * @since 1.0.0
	 *
	 * @param string  $message Message.
	 * @param boolean $success Success.
	 * @param array   $data    Data to send with the message.
	 * @return void
	 */
	private function send_response( $message, $success = true, $data = array() ) {

		$ret = array(
			'data'    => $data,
			'message' => $message,
			'success' => $success,
		);

		wp_send_json( $ret );
	}
}

return new LLMS_AQ_AJAX();
