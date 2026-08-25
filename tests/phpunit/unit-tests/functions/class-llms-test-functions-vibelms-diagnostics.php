<?php
/**
 * Tests for VibeLMS diagnostics.
 *
 * @package VibeLMS/Tests/Functions
 *
 * @group functions
 * @group vibelms_diagnostics
 */

class LLMS_Test_Functions_VibeLMS_Diagnostics extends LLMS_UnitTestCase {

	/**
	 * Sensitive nested values are redacted before logging.
	 *
	 * @return void
	 */
	public function test_sensitive_values_are_redacted() {
		$value = llms_vibelms_diagnostics_sanitize_value(
			array(
				'password' => 'secret',
				'profile'  => array( 'api_token' => 'hidden', 'name' => 'User' ),
			)
		);

		$this->assertSame( '[redacted]', $value['password'] );
		$this->assertSame( '[redacted]', $value['profile']['api_token'] );
		$this->assertSame( 'User', $value['profile']['name'] );
	}
}
