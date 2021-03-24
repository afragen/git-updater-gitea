<?php
/**
 * Git Updater - Gitea
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater-gitea
 * @package   git-updater-gitea
 */

namespace Fragen\Git_Updater\Gitea;

use Fragen\Git_Updater\API\Gitea_API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load textdomain.
\add_action(
	'init',
	function () {
		load_plugin_textdomain( 'git-updater-gitlab' );
	}
);

/**
 * Class Bootstrap
 */
class Bootstrap {
	/**
	 * Run the bootstrap.
	 *
	 * @return bool|void
	 */
	public function run() {
		// Exit if GitHub Updater not running.
		if ( ! class_exists( '\\Fragen\\Git_Updater\\Bootstrap' ) ) {
			return false;
		}

		new Gitea_API();
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'gu_get_repo_parts', [ $this, 'add_repo_parts' ], 10, 2 );
		add_filter( 'gu_parse_enterprise_headers', [ $this, 'parse_headers' ], 10, 2 );
		add_filter( 'gu_settings_auth_required', [ $this, 'set_auth_required' ], 10, 1 );
		add_filter( 'gu_get_repo_api', [ $this, 'set_repo_api' ], 10, 3 );
		add_filter( 'gu_api_repo_type_data', [ $this, 'set_repo_type_data' ], 10, 2 );
		add_filter( 'gu_api_url_type', [ $this, 'set_api_url_data' ], 10, 4 );
		add_filter( 'gu_post_get_credentials', [ $this, 'set_credentials' ], 10, 2 );
		add_filter( 'gu_get_auth_header', [ $this, 'set_auth_header' ], 10, 2 );
		add_filter( 'gu_git_servers', [ $this, 'set_git_servers' ], 10, 1 );
		add_filter( 'gu_installed_apis', [ $this, 'set_installed_apis' ], 10, 1 );
		add_filter( 'gu_post_api_response_body', [ $this, 'convert_remote_body_response' ], 10, 2 );
		add_filter( 'gu_parse_release_asset', [ $this, 'parse_release_asset' ], 10, 4 );
		add_filter( 'gu_install_remote_install', [ $this, 'set_remote_install_data' ], 10, 2 );
		add_filter( 'gu_get_language_pack_json', [ $this, 'set_language_pack_json' ], 10, 4 );
		add_filter( 'gu_post_process_language_pack_package', [ $this, 'process_language_pack_data' ], 10, 4 );
		add_filter( 'gu_get_git_icon_data', [ $this, 'set_git_icon_data' ], 10, 2 );
	}

	/**
	 * Add API specific data to `get_repo_parts()`.
	 *
	 * @param array  $repos Array of repo data.
	 * @param string $type  plugin|theme.
	 *
	 * @return array
	 */
	public function add_repo_parts( $repos, $type ) {
		$repos['types'] = array_merge( $repos['types'], [ 'Gitea' => 'gitea_' . $type ] );
		$repos['uris']  = array_merge( $repos['uris'], [ 'Gitea' => '' ] );

		return $repos;
	}

	/**
	 * Modify enterprise API header data.
	 *
	 * @param array  $header Array of repo data.
	 * @param string $git    Name of git host.
	 *
	 * @return string
	 */
	public function parse_headers( $header, $git ) {
		if ( 'Gitea' === $git ) {
			$header['enterprise_uri']  = $header['base_uri'];
			$header['enterprise_api']  = trim( $header['enterprise_uri'], '/' );
			$header['enterprise_api'] .= '/api/v3';
		}

		return $header;
	}

	/**
	 * Add API specific auth required data.
	 *
	 * @param array $auth_required Array of authentication required data.
	 *
	 * @return array
	 */
	public function set_auth_required( $auth_required ) {
		return array_merge(
			$auth_required,
			[
				'gitea'         => true,
				'gitea_private' => true,
			]
		);
	}

	/**
	 * Return git host API object.
	 *
	 * @param \stdClass $repo_api Git API object.
	 * @param string    $git      Name of git host.
	 * @param \stdClass $repo     Repository object.
	 *
	 * @return \stdClass
	 */
	public function set_repo_api( $repo_api, $git, $repo ) {
		if ( 'gitea' === $git ) {
			$repo_api = new Gitea_API( $repo );
		}

		return $repo_api;
	}

	/**
	 * Add API specific repo data.
	 *
	 * @param array     $arr  Array of repo API data.
	 * @param \stdClass $repo Repository object.
	 *
	 * @return array
	 */
	public function set_repo_type_data( $arr, $repo ) {
		if ( 'gitea' === $repo->git ) {
			$arr['git']           = 'gitea';
			$arr['base_uri']      = $repo->enterprise . '/api/v1';
			$arr['base_download'] = $repo->enterprise;
		}

		return $arr;
	}

	/**
	 * Add API specific URL data.
	 *
	 * @param array     $type          Array of API type data.
	 * @param \stdClass $repo          Repository object.
	 * @param bool      $download_link Boolean indicating a download link.
	 * @param string    $endpoint      API URL endpoint.
	 *
	 * @return array
	 */
	public function set_api_url_data( $type, $repo, $download_link, $endpoint ) {
		if ( 'gitea' === $type['git'] ) {
			if ( $download_link ) {
				$type['base_download'] = $type['base_uri'];
			}
		}

		return $type;
	}

	/**
	 * Add credentials data for API.
	 *
	 * @param array $credentials Array of repository credentials data.
	 * @param array $args        Hook args.
	 *
	 * @return array
	 */
	public function set_credentials( $credentials, $args ) {
		if ( isset( $args['type'], $args['headers'], $args['options'], $args['slug'], $args['object'] ) ) {
			$type    = $args['type'];
			$headers = $args['headers'];
			$options = $args['options'];
			$slug    = $args['slug'];
			$object  = $args['object'];
		} else {
			return;
		}
		if ( 'gitea' === $type || $object instanceof Gitea_API ) {
			$token = ! empty( $options['gitea_access_token'] ) ? $options['gitea_access_token'] : null;
			$token = ! empty( $options[ $slug ] ) ? $options[ $slug ] : $token;

			$credentials['type']  = 'gitea';
			$credentials['isset'] = true;
			$credentials['token'] = isset( $token ) ? $token : null;
		}

		return $credentials;
	}

	/**
	 * Add Basic Authentication header.
	 *
	 * @param array $headers     HTTP GET headers.
	 * @param array $credentials Repository credentials.
	 *
	 * @return array
	 */
	public function set_auth_header( $headers, $credentials ) {
		if ( 'gitea' === $credentials['type'] ) {
			$headers['headers']['Authorization'] = 'token ' . $credentials['token'];
		}

		return $headers;
	}

	/**
	 * Add API as git server.
	 *
	 * @param array $git_servers Array of git servers.
	 *
	 * @return array
	 */
	public function set_git_servers( $git_servers ) {
		return array_merge( $git_servers, [ 'gitea' => 'Gitea' ] );
	}

	/**
	 * Add API data to $installed_apis.
	 *
	 * @param array $installed_apis Array of installed APIs.
	 *
	 * @return array
	 */
	public function set_installed_apis( $installed_apis ) {
		return array_merge( $installed_apis, [ 'gitea_api' => true ] );
	}

	/**
	 * Convert HHTP remote body response to JSON.
	 *
	 * @param array     $response HTTP GET response.
	 * @param \stdClass $obj API object.
	 *
	 * @return array
	 */
	public function convert_remote_body_response( $response, $obj ) {
		if ( $obj instanceof Gitea_API ) {
			$body = wp_remote_retrieve_body( $response );
			if ( null === json_decode( $body ) ) {
				$response['body'] = json_encode( $body );
			}
		}

		return $response;
	}

	/**
	 * Parse API release asset.
	 *
	 * @param \stdClass $response API response object.
	 * @param string    $git      Name of git host.
	 * @param string    $request  Schema of API request.
	 * @param \stdClass $obj      Current class object.
	 *
	 * @return \stdClass|string
	 */
	public function parse_release_asset( $response, $git, $request, $obj ) {
		if ( 'gitea' === $git ) {
			// TODO: make work.
		}

		return $response;
	}

	/**
	 * Set remote installation data for specific API.
	 *
	 * @param array $install Array of remote installation data.
	 * @param array $headers Array of repository header data.
	 *
	 * @return array
	 */
	public function set_remote_install_data( $install, $headers ) {
		if ( 'gitea' === $install['git_updater_api'] ) {
			$install = ( new Gitea_API() )->remote_install( $headers, $install );
		}

		return $install;
	}

	/**
	 * Filter to return API specific language pack data.
	 *
	 * @param \stdClass $response Object of Language Pack API response.
	 * @param string    $git      Name of git host.
	 * @param array     $headers  Array of repo headers.
	 * @param \stdClass $obj      Current class object.
	 *
	 * @return \stdClass
	 */
	public function set_language_pack_json( $response, $git, $headers, $obj ) {
		if ( 'gitea' === $git ) {
			$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/raw/master/language-pack.json' );
			$response = isset( $response->content )
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				? json_decode( base64_decode( $response->content ) )
				: null;
		}

		return $response;
	}

	/**
	 * Filter to post process API specific language pack data.
	 *
	 * @param null|string $package URL to language pack.
	 * @param string      $git     Name of git host.
	 * @param \stdClass   $locale  Object of language pack data.
	 * @param array       $headers Array of repository headers.
	 *
	 * @return string
	 */
	public function process_language_pack_data( $package, $git, $locale, $headers ) {
		if ( 'gitea' === $git ) {
			// TODO: make sure this works as expected.
			$package = [ $headers['uri'], 'raw/master' ];
			$package = implode( '/', $package ) . $locale->package;
		}

		return $package;
	}

	/**
	 * Set API icon data for display.
	 *
	 * @param array  $icon_data Header data for API.
	 * @param string $type_cap Plugin|Theme.
	 *
	 * @return array
	 */
	public function set_git_icon_data( $icon_data, $type_cap ) {
		$icon_data['headers'] = array_merge(
			$icon_data['headers'],
			[ "Gitea{$type_cap}URI" => "Gitea {$type_cap} URI" ]
		);
		$icon_data['icons']   = array_merge(
			$icon_data['icons'],
			[ 'gitea' => basename( dirname( __DIR__ ) ) . '/assets/gitea-logo.svg' ]
		);
		return $icon_data;
	}
}
