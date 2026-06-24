<?php
/**
 * Test OAuth Connect integration for Gitea
 *
 * @package Git_Updater_Gitea
 */

/**
 * Test OAuth Connect field registration
 */
class Test_Gitea_OAuth_Connect extends WP_UnitTestCase {

	/**
	 * Test that OAuth connect field is registered in add_settings
	 */
	public function test_oauth_connect_field_is_registered(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		
		// Get the settings fields registered
		global $wp_settings_fields;
		
		// Call add_settings to register fields
		$api->add_settings( [ 'gitea_private' => true ] );
		
		// Check that the OAuth connect field was registered
		$this->assertArrayHasKey( 'gitea_oauth_connect', $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings'] );
	}

	/**
	 * Test OAuth connect field uses correct callback
	 */
	public function test_oauth_connect_field_uses_correct_callback(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		$api->add_settings( [ 'gitea_private' => true ] );
		
		global $wp_settings_fields;
		$field = $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings']['gitea_oauth_connect'];
		
		$this->assertEquals( 'Gitea OAuth', $field['title'] );
		$this->assertIs_array( $field['callback'] );
		$this->assertInstanceOf( Fragen\Git_Updater\OAuth\OAuth_Connect::class, $field['callback'][0] );
		$this->assertEquals( 'render_connect_field', $field['callback'][1] );
	}

	/**
	 * Test OAuth connect field passes correct provider argument
	 */
	public function test_oauth_connect_field_passes_correct_provider(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		$api->add_settings( [ 'gitea_private' => true ] );
		
		global $wp_settings_fields;
		$field = $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings']['gitea_oauth_connect'];
		
		$this->assertArrayHasKey( 'provider', $field['args'] );
		$this->assertEquals( 'gitea', $field['args']['provider'] );
	}

	/**
	 * Test that Gitea Server URL field is registered
	 */
	public function test_gitea_server_field_is_registered(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		$api->add_settings( [ 'gitea_private' => true ] );
		
		global $wp_settings_fields;
		
		$this->assertArrayHasKey( 'gitea_server', $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings'] );
	}

	/**
	 * Test Gitea Server URL field has correct placeholder
	 */
	public function test_gitea_server_field_has_placeholder(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		$api->add_settings( [ 'gitea_private' => true ] );
		
		global $wp_settings_fields;
		$field = $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings']['gitea_server'];
		
		$this->assertEquals( 'Gitea Server URL', $field['title'] );
		$this->assertArrayHasKey( 'placeholder', $field['args'] );
		$this->assertEquals( 'https://gitea.example.com', $field['args']['placeholder'] );
	}

	/**
	 * Test that Gitea OAuth App Client ID field is registered
	 */
	public function test_gitea_client_id_field_is_registered(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		$api->add_settings( [ 'gitea_private' => true ] );
		
		global $wp_settings_fields;
		
		$this->assertArrayHasKey( 'gitea_client_id', $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings'] );
	}

	/**
	 * Test Gitea Client ID field has correct ID
	 */
	public function test_gitea_client_id_field_has_correct_id(): void {
		$api = new Fragen\Git_Updater\API\Gitea_API();
		$api->add_settings( [ 'gitea_private' => true ] );
		
		global $wp_settings_fields;
		$field = $wp_settings_fields['git_updater_gitea_install_settings']['gitea_settings']['gitea_client_id'];
		
		$this->assertEquals( 'Gitea OAuth App Client ID', $field['title'] );
		$this->assertArrayHasKey( 'id', $field['args'] );
		$this->assertEquals( 'gitea_client_id', $field['args']['id'] );
	}
}
