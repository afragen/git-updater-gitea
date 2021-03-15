<?php

/**
 * Class BootstrapTest
 *
 * @package Git_Updater_Gitea
 */

use Fragen\Git_Updater\Gitea\Bootstrap;

/**
 * Sample test case.
 */
class BootstrapTest extends WP_UnitTestCase {
	/**
	 * A single example test.
	 */
	public function test_sample() {
		// Replace this with some actual testing code.
		$this->assertTrue(true);
	}

	public function test_add_repo_parts() {
		$empty     = ['types' => [], 'uris' => []];
		$expected  = [
			'types' => ['Gitea' => 'gitea_plugin'],
			'uris'  => ['Gitea' => ''],
		];
		$acutal = (new Bootstrap())->add_repo_parts($empty, 'plugin');
		$this->assertEqualSetsWithIndex($expected, $acutal);
	}

	public function test_set_auth_required() {
		$expected = [
			'gitea'         => true,
			'gitea_private' => true,
		];
		$acutal = (new Bootstrap())->set_auth_required([]);
		$this->assertEqualSetsWithIndex($expected, $acutal);
	}

	public function test_set_repo_type_data() {
		$enterprise             = new \stdClass();
		$enterprise->git        = 'gitea';
		$enterprise->enterprise = 'https://mygitea.example.com';
		$expected_enterprise    = [
			'git'           => 'gitea',
			'base_uri'      => 'https://mygitea.example.com/api/v1',
			'base_download' => 'https://mygitea.example.com',
		];

		$actual_enterprise = (new Bootstrap())->set_repo_type_data([], $enterprise);
		$this->assertEqualSetsWithIndex($expected_enterprise, $actual_enterprise);
	}

	public function test_parse_headers() {
		$test = [
			'host'     => null,
			'base_uri' => 'https://api.example.com',
		];

		$expected_rest_api = 'https://api.example.com/api/v3';
		$actual            = (new Bootstrap())->parse_headers($test, 'Gitea');

		$this->assertSame($expected_rest_api, $actual['enterprise_api']);
	}
}
