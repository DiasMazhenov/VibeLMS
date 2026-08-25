<?php
/**
 * Upload Question Template.
 *
 * @package LifterLMS_Advanced_Quizzes/Templates
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @property LLMS_Quiz_Attempt $attempt  Attempt object.
 * @property LLMS_Question     $question Question object.
 */

defined( 'ABSPATH' ) || exit;

$types           = llms_parse_bool( $question->get( 'upload_restrict_filetypes' ) ) ? llms_aq_mimes_to_string( $question->get( 'upload_filetypes' ) ) : false;
$question_answer = $attempt && method_exists( $attempt, 'get_question_answer' ) ? $attempt->get_question_answer( $question->get( 'id' ) ) : array();
$answer          = is_array( $question_answer ) ? ( $question_answer[0] ?? '' ) : $question_answer;
?>

<div class="llms-aq-upload">

	<label class="llms-aq-uploader" id="llms-aq-uploader-zone" for="llms-aq-upload-file">

		<i class="fa fa-upload" aria-hidden="true"></i>
		<h2><?php _e( 'Select or drop a file...', 'lifterlms-advanced-quizzes' ); ?></h2>
		<?php if ( $types ) : ?>
			<em>
			<?php
			printf(
				// Translators: %s = Comma separated list of allowed file types.
				__( 'Allowed filetypes: %s', 'lifterlms-advanced-quizzes' ),
				$types
			);
			?>
			</em>
		<?php endif; ?>
		<input id="llms-aq-upload-file" type="file"<?php echo $types ? ' accept="' . $types . '"' : ''; ?>>

		<?php if ( $answer ) : ?>
			<div class="llms-aq-uploaded-file">
				<i class="fa fa-file llms-file-icon" aria-hidden="true"></i>
				<span><?php echo basename( get_attached_file( $answer ) ); ?></span>
			</div>
		<?php endif; ?>
		<?php wp_nonce_field( 'llms_aq_upload', 'llms-aq-upload-nonce' ); ?>
	</label>

	<input id="llms_upload_attachment_id" name="llms_upload_attachment_id" type="hidden" value=<?php echo esc_html( $answer ); ?>>

</div>
