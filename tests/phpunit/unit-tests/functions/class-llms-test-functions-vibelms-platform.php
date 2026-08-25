<?php
/**
 * Tests for the VibeLMS assessment layer.
 *
 * @package VibeLMS/Tests/Functions
 *
 * @group functions
 * @group vibelms_platform
 */

class LLMS_Test_Functions_VibeLMS_Platform extends LLMS_UnitTestCase {

	/**
	 * Assessment settings expose the project-neutral defaults.
	 *
	 * @return void
	 */
	public function test_assessment_settings() {
		$settings = ( new LLMS_VibeLMS_Platform() )->add_settings( array() );
		$by_id    = array();
		foreach ( $settings as $setting ) {
			if ( isset( $setting['id'] ) ) {
				$by_id[ $setting['id'] ] = $setting;
			}
		}

		$this->assertSame( 15, $by_id[ LLMS_VibeLMS_Platform::REQUIRED_QUESTIONS_OPTION ]['default'] );
		$this->assertSame( 100, $by_id[ LLMS_VibeLMS_Platform::PASSING_PERCENT_OPTION ]['default'] );
		$this->assertSame( 'no', $by_id[ LLMS_VibeLMS_Platform::REQUIRE_IDENTITY_OPTION ]['default'] );
	}

	/**
	 * The identity form is available as a shortcode for Elementor and blocks.
	 *
	 * @return void
	 */
	public function test_identity_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'vibelms_student_identity' ) );
	}
}
