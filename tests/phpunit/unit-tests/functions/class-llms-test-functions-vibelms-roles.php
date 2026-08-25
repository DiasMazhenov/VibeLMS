<?php
/**
 * Tests for VibeLMS roles.
 *
 * @package VibeLMS/Tests/Functions
 *
 * @group functions
 * @group vibelms_roles
 */

class LLMS_Test_Functions_VibeLMS_Roles extends LLMS_UnitTestCase {

	/**
	 * The role definitions expose only their intended capabilities.
	 *
	 * @return void
	 */
	public function test_role_definitions() {
		$roles = llms_vibelms_role_definitions();

		$this->assertArrayHasKey( 'vibelms_student', $roles );
		$this->assertArrayHasKey( 'vibelms_observer', $roles );
		$this->assertTrue( $roles['vibelms_student']['caps']['vibelms_access_learning'] );
		$this->assertTrue( $roles['vibelms_observer']['caps']['vibelms_view_reports'] );
		$this->assertTrue( $roles['vibelms_observer']['caps']['vibelms_export_reports'] );
		$this->assertArrayNotHasKey( 'edit_posts', $roles['vibelms_observer']['caps'] );
	}
}
