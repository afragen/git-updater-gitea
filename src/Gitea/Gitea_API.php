<?php
/**
 * Git Updater - Gitea
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater-gitea
 * @package  git-updater-gitea
 */

namespace Fragen\Git_Updater\API;

use Fragen\Singleton;
use stdClass;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Gitea_API
 *
 * Get remote data from a Gitea repo.
 *
 * @author  Andy Fragen
 * @author  Marco Betschart
 */
class Gitea_API extends API implements API_Interface {
	/**
	 * Constructor.
	 *
	 * @param stdClass $type plugin|theme.
	 */
	public function __construct( $type = null ) {
		parent::__construct();
		$this->type = $type;
		$this->set_default_credentials();
		$this->settings_hook( $this );
		$this->add_settings_subtab();
		$this->add_install_fields( $this );
	}

	/**
	 * Set default credentials if option not set.
	 */
	protected function set_default_credentials() {
		$set_credentials = false;
		if ( ! isset( static::$options['gitea_access_token'] ) ) {
			static::$options['gitea_access_token'] = null;
			$set_credentials                       = true;
		}

		if ( $set_credentials ) {
			add_site_option( 'git_updater', static::$options );
		}
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $file Filename.
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		return $this->get_remote_api_info( 'gitea', "/repos/:owner/:repo/raw/:branch/{$file}" );
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool|null
	 */
	public function get_remote_tag() {
		return $this->get_remote_api_tag( 'gitea', '/repos/:owner/:repo/releases' );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param null $changes Changelog filename - (deprecated).
	 *
	 * @return bool|null
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'gitea', $changes, '/repos/:owner/:repo/raw/:branch/:changelog' );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool|null
	 */
	public function get_remote_readme() {
		return $this->get_remote_api_readme( 'gitea', '/repos/:owner/:repo/raw/:branch/:readme' );
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @return bool|null
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( 'gitea', '/repos/:owner/:repo' );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool|null
	 */
	public function get_remote_branches() {
		return $this->get_remote_api_branches( 'gitea', '/repos/:owner/:repo/branches' );
	}

	/**
	 * Get Gitea release asset.
	 *
	 * @return false
	 */
	public function get_release_asset() {
		// TODO: eventually figure this out.
		return false;
	}

	/**
	 * Return array of release assets.
	 *
	 * @return array
	 */
	public function get_release_assets() {
		return $this->get_api_release_assets( 'gitea', '/repos/:owner/:repo/releases' );
	}

	/**
	 * Return list of repository assets.
	 *
	 * @return bool|null
	 */
	public function get_repo_assets() {
		return $this->get_remote_api_assets( 'gitea', '/repos/:owner/:repo/contents/:path' );
	}

	/**
	 * Return list of files at GitHub repo root.
	 *
	 * @return bool|null
	 */
	public function get_repo_contents() {
		return $this->get_remote_api_contents( 'gitea', '/repos/:owner/:repo/contents/' );
	}

	/**
	 * Construct $this->type->download_link using Gitea API.
	 *
	 * @param boolean $branch_switch For direct branch changing.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $branch_switch = false ) {
		self::$method       = 'download_link';
		$download_link_base = $this->get_api_url( '/repos/:owner/:repo/archive/', true );
		$endpoint           = '';

		/*
		 * Read tag data from the repo cache so non-fetch callers (rollback,
		 * branch switch, REST update, branch listings) resolve the correct
		 * endpoint even when $this->type has not been hydrated by a fetch.
		 */
		$cache      = $this->get_repo_cache( $this->type->slug, false, [ 'tags', 'newest_tag' ] );
		$tags       = $this->type->tags ?? [];
		$newest_tag = $this->type->newest_tag ?? '0.0.0';
		if ( is_array( $cache ) ) {
			if ( is_array( $cache['tags'] ?? null ) && ! empty( $cache['tags'] ) ) {
				$tags = $cache['tags'];
			}
			if ( ! empty( $cache['newest_tag'] ) ) {
				$newest_tag = (string) $cache['newest_tag'];
			} elseif ( is_array( $cache['tags'] ?? null ) && ! empty( $cache['tags'] ) ) {
				// Missing newest_tag entry: derive newest from the cached tag list
				// (a flat list of names; sort_tags() semantics).
				$sorted = $cache['tags'];
				usort( $sorted, fn ( $a, $b ) => version_compare( trim( $b, 'v' ), trim( $a, 'v' ) ) );
				$newest_tag = (string) reset( $sorted );
			}
		}

		$target = false !== $branch_switch ? $branch_switch : $this->type->branch;

		// Release asset.
		if ( $this->use_release_asset( $branch_switch ) ) {
			$release_asset = $this->resolve_release_asset( $branch_switch );

			// Only cache the primary/latest release asset; tag-specific assets
			// must not pollute the release_asset_download cache.
			if ( $release_asset && ! $this->is_tag_target( (string) $target ) ) {
				$this->set_repo_cache( 'release_asset_download', $release_asset );
			}
			return $release_asset;
		}

		/*
		 * If a branch has been given, use branch.
		 * If branch is primary branch (default) and tags are used, use newest tag.
		 */
		if ( $this->type->primary_branch !== $target || empty( $tags ) ) {
			$endpoint .= $target . '.zip';
		} else {
			$endpoint .= $newest_tag . '.zip';
		}

		$download_link = $download_link_base . $endpoint;

		/**
		 * Filter download link so developers can point to specific ZipFile
		 * to use as a download link during a branch switch.
		 *
		 * @since 8.8.0
		 * @since 10.0.0
		 *
		 * @param string   $download_link Download URL.
		 * @param stdClass $this->type    Repository object.
		 * @param string   $branch_switch Branch or tag for rollback or branch switching.
		 */
		return apply_filters( 'gu_post_construct_download_link', $download_link, $this->type, $branch_switch );
	}

	/**
	 * Resolve the release asset URL for a given target.
	 *
	 * @param bool|string $branch_switch Branch or tag to switch to, or false.
	 *
	 * @return string|bool Release asset URL, or false if none.
	 */
	private function resolve_release_asset( $branch_switch = false ) {
		$target = false !== $branch_switch ? $branch_switch : $this->type->branch;

		if ( $target === $this->type->primary_branch ) {
			return $this->get_latest_release_asset();
		}

		if ( is_string( $target ) && $this->is_tag_target( $target ) ) {
			return $this->get_release_asset_for_tag( $target );
		}

		return '';
	}

	/**
	 * Check whether a string target is a tag (not a branch).
	 *
	 * @param string $target Target branch or tag.
	 *
	 * @return bool
	 */
	private function is_tag_target( string $target ): bool {
		return ! array_key_exists( $target, (array) ( $this->type->branches ?? [] ) );
	}

	/**
	 * Fetch the release asset URL for a specific tag.
	 *
	 * @param string $tag Tag name.
	 *
	 * @return string Release asset URL, or empty string if none.
	 */
	private function get_release_asset_for_tag( string $tag ): string {
		$response = $this->api( '/repos/:owner/:repo/releases/tags/' . $tag );
		if ( is_wp_error( $response ) || ! isset( $response->assets ) || ! is_array( $response->assets ) ) {
			return '';
		}

		foreach ( $response->assets as $asset ) {
			if ( isset( $asset->name, $asset->url ) && str_starts_with( $asset->name, $this->type->slug ) ) {
				return $asset->url;
			}
		}

		return '';
	}

	/**
	 * Fetch the latest release asset URL, applying the dev-release-asset filter.
	 *
	 * @return string|bool Release asset URL, or false if none.
	 */
	private function get_latest_release_asset() {
		$release_assets = $this->get_release_assets();
		if ( ! $release_assets ) {
			return '';
		}
		$release_assets['assets'] = $release_assets['assets'] ?? [];
		$release_asset            = reset( $release_assets['assets'] );

		if ( apply_filters( 'gu_dev_release_asset', false, $this->type ) ) {
			$current_asset_version     = array_key_first( $release_assets['assets'] ) ?? '';
			$current_dev_asset_version = array_key_first( $release_assets['dev_assets'] ?? [] ) ?? '';
			if ( version_compare( $current_asset_version, $current_dev_asset_version, '<' ) ) {
				$release_asset = reset( $release_assets['dev_assets'] );
			}
		}

		return $release_asset;
	}

	/**
	 * Create Gitea API endpoints.
	 *
	 * @param Gitea_API|API $git      Git host API object.
	 * @param string        $endpoint Endpoint.
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( $git::$method ) {
			case 'file':
			case 'readme':
			case 'meta':
			case 'tags':
			case 'assets':
			case 'changes':
			case 'translation':
			case 'download_link':
				break;
			case 'branches':
				$endpoint = add_query_arg( 'per_page', '100', $endpoint );
				break;
			default:
				break;
		}

		return $endpoint;
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param stdClass|array $response Response from API call for tags.
	 *
	 * @return stdClass|array|bool Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		if ( is_string( $response ) ) {
			return false;
		}

		$arr = [];
		array_map(
			function ( $e ) use ( &$arr ) {
				$arr[] = $e->tag_name;

				return $arr;
			},
			(array) $response
		);

		return $arr;
	}

	/**
	 * Parse API response and return array of meta variables.
	 *
	 * @param stdClass|array $response Response from API call.
	 *
	 * @return array|stdClass|bool $arr Array of meta variables.
	 */
	public function parse_meta_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		if ( is_string( $response ) ) {
			return false;
		}

		$arr      = [];
		$response = [ $response ];

		array_filter(
			$response,
			function ( $e ) use ( &$arr ) {
				$arr['private']      = $e->private ?? false;
				$arr['last_updated'] = $e->updated_at ?? '';
				$arr['added']        = $e->created_at ?? '';
				$arr['watchers']     = $e->watchers_count ?? 0;
				$arr['forks']        = $e->forks_count ?? 0;
				$arr['open_issues']  = $e->open_issues_count ?? 0;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog in base64.
	 *
	 * @param stdClass|array $response Response from API call.
	 *
	 * @return void|array|stdClass $arr Array of changes in base64, object if error.
	 */
	public function parse_changelog_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$arr      = [];
		$response = [ $response ];

		array_filter(
			$response,
			function ( $e ) use ( &$arr ) {
				$arr['changes'] = $e->content;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array of branch data.
	 *
	 * @param stdClass $response API response.
	 *
	 * @return array Array of branch data.
	 */
	public function parse_branch_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$response = is_string( $response ) ? [] : $response;

		/*
		 * Seed type->branches before the loop so construct_download_link()
		 * classifies branches versus tags during the per-branch download link
		 * resolution. populate_api_data() fills the repo object later; without
		 * this, every branch target is treated as a tag and release-asset repos
		 * re-resolve /releases for each branch.
		 */
		$this->type->branches = [];
		foreach ( $response as $branch ) {
			$this->type->branches[ $branch->name ] = [];
		}

		$branches = [];
		foreach ( $response as $branch ) {
			$branches[ $branch->name ]['download']         = $this->construct_download_link( $branch->name );
			$branches[ $branch->name ]['commit_hash']      = $branch->commit->id;
			$branches[ $branch->name ]['commit_timestamp'] = $branch->commit->timestamp;
		}

		return $branches;
	}

	/**
	 * Parse tags and create download links.
	 *
	 * @param stdClass|array $response  Response from API call.
	 * @param array          $repo_type Array of repository data.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags          = [];
		$download_base = implode(
			'/',
			[
				$repo_type['base_uri'],
				'repos',
				$this->type->owner,
				$this->type->slug,
				'archive/',
			]
		);

		foreach ( (array) $response as $tag ) {
			// Ignore leading 'v' and skip anything with dash or words.
			if ( ! preg_match( '/[^v]+[-a-z]+/', $tag ) ) {
				$tags[ $tag ] = $download_base . $tag . '.zip';
			}
		}
		uksort( $tags, fn ( $a, $b ) => version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) ) );

		return $tags;
	}

	/**
	 * Parse remote root files/dirs.
	 *
	 * @param stdClass|array<string, mixed> $response Response from API call.
	 *
	 * @return array{files: list<string>, dirs: list<string>}
	 */
	protected function parse_contents_response( $response ) {
		$files = [];
		$dirs  = [];

		foreach ( $response as $content ) {
			$content = (object) $content;
			if ( property_exists( $content, 'type' ) && 'file' === $content->type ) {
				$files[] = $content->name;
			}
			if ( property_exists( $content, 'type' ) && 'dir' === $content->type ) {
				$dirs[] = $content->name;
			}
		}

		return [
			'files' => $files,
			'dirs'  => $dirs,
		];
	}

	/**
	 * Parse remote assets directory.
	 *
	 * @param stdClass|array $response Response from API call.
	 *
	 * @return stdClass|array
	 */
	protected function parse_asset_dir_response( $response ) {
		$assets = [];

		if ( isset( $response->message ) || is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response as $asset ) {
			if ( 'file' === $asset->type ) {
				$assets[ $asset->name ] = $asset->download_url;
			}
		}

		if ( empty( $assets ) ) {
			$assets['message'] = 'No assets found';
			$assets            = (object) $assets;
		}

		return $assets;
	}

	/**
	 * Add settings for Gitea Access Token.
	 *
	 * @param array $auth_required Array of authentication data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		if ( $auth_required['gitea'] ) {
			add_settings_section(
				'gitea_settings',
				esc_html__( 'Gitea Access Token', 'git-updater-gitea' ),
				[ $this, 'print_section_gitea_token' ],
				'git_updater_gitea_install_settings'
			);
		}

		if ( $auth_required['gitea_private'] ) {
			add_settings_section(
				'gitea_id',
				esc_html__( 'Gitea Private Settings', 'git-updater-gitea' ),
				[ $this, 'print_section_gitea_info' ],
				'git_updater_gitea_install_settings'
			);
		}

		$token_args     = [
			'id'    => 'gitea_access_token',
			'token' => true,
			'class' => $auth_required['gitea'] ? '' : 'hidden',
		];
		$server_args    = [
			'id'          => 'gitea_server',
			'placeholder' => 'https://gitea.example.com',
			'class'       => '',
		];
		$client_id_args = [
			'id'    => 'gitea_client_id',
			'class' => '',
		];
		$args           = [
			'provider' => 'gitea',
			'class'    => '',
		];

		if ( class_exists( 'Fragen\Git_Updater\OAuth\OAuth_Connect' ) ) {
			$oauth = Singleton::get_instance( 'OAuth\OAuth_Connect', $this );
			if ( $oauth->is_oauth_token( 'gitea' ) ) {
				$token_args['class'] = trim( $token_args['class'] . ' hidden' );
			}
			if ( ! empty( static::$options['gitea_access_token'] ) && ! $oauth->is_oauth_token( 'gitea' ) ) {
				$server_args['class']    = trim( $server_args['class'] . ' hidden' );
				$client_id_args['class'] = trim( $client_id_args['class'] . ' hidden' );
				$args['class']           = trim( $args['class'] . ' hidden' );
			}

			$remove_args = [
				'provider' => 'gitea',
				'class'    => '',
			];
			if ( empty( static::$options['gitea_access_token'] ) || $oauth->is_oauth_token( 'gitea' ) ) {
				$remove_args['class'] = trim( $remove_args['class'] . ' hidden' );
			}

			add_settings_field(
				'gitea_oauth_connect',
				esc_html__( 'Gitea OAuth', 'git-updater-gitea' ),
				[ $oauth, 'render_connect_field' ],
				'git_updater_gitea_install_settings',
				'gitea_settings',
				$args
			);

			add_settings_field(
				'gitea_remove_token',
				esc_html__( 'Remove Token', 'git-updater-gitea' ),
				[ $oauth, 'render_remove_token_field' ],
				'git_updater_gitea_install_settings',
				'gitea_settings',
				$remove_args
			);
		}

		add_settings_field(
			'gitea_access_token',
			esc_html__( 'Gitea Access Token', 'git-updater-gitea' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'git_updater_gitea_install_settings',
			'gitea_settings',
			$token_args
		);

			add_settings_field(
				'gitea_server',
				esc_html__( 'Gitea Server URL', 'git-updater-gitea' ),
				[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
				'git_updater_gitea_install_settings',
				'gitea_settings',
				$server_args
			);

			add_settings_field(
				'gitea_client_id',
				esc_html__( 'Gitea OAuth App Client ID', 'git-updater-gitea' ),
				[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
				'git_updater_gitea_install_settings',
				'gitea_settings',
				$client_id_args
			);
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'git_updater_gitea_install_settings';
		$setting_field['section']         = 'gitea_id';
		$setting_field['callback_method'] = [
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		];

		return $setting_field;
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'gu_add_settings_subtabs',
			function ( $subtabs ) {
				return array_merge( $subtabs, [ 'gitea' => esc_html__( 'Gitea', 'git-updater-gitea' ) ] );
			}
		);
	}

	/**
	 * Print the Gitea Settings text.
	 */
	public function print_section_gitea_info() {
		esc_html_e( 'Enter your repository specific Gitea Access Token.', 'git-updater-gitea' );
	}

	/**
	 * Print the Gitea Access Token Settings text.
	 */
	public function print_section_gitea_token() {
		esc_html_e( 'Enter your Gitea Access Token.', 'git-updater-gitea' );
		printf( '<p class="description">%s</p>', esc_html__( 'Access tokens are stored in this site\'s options table. Database backups contain them in cleartext — handle backup files accordingly.', 'git-updater-gitea' ) );
		$icon = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/gitea-logo.svg';
		printf( '<img class="git-oauth-icon" src="%s" alt="Gitea logo" />', esc_attr( $icon ) );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type Plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'gitea_access_token',
			esc_html__( 'Gitea Access Token', 'git-updater-gitea' ),
			[ $this, 'gitea_access_token' ],
			'git_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Gitea Access Token for remote install.
	 */
	public function gitea_access_token() {
		?>
		<label for="gitea_access_token">
			<input class="gitea_setting" type="password" style="width:50%;" id="gitea_access_token" name="gitea_access_token" value="" autocomplete="new-password">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Gitea Access Token for private Gitea repositories.', 'git-updater-gitea' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Add remote install feature, create endpoint.
	 *
	 * @param array $headers Array of headers.
	 * @param array $install Array of install data.
	 *
	 * @return mixed $install
	 */
	public function remote_install( $headers, $install ) {
		$options['gitea_access_token'] = isset( static::$options['gitea_access_token'] ) ? static::$options['gitea_access_token'] : null;

		$base = $headers['base_uri'] . '/api/v1';

		$install['download_link'] = "{$base}/repos/{$install['git_updater_repo']}/archive/{$install['git_updater_branch']}.zip";

		/*
		 * Add/Save access token if present.
		 */
		if ( ! empty( $install['gitea_access_token'] ) ) {
			$install['options'][ $install['repo'] ] = $install['gitea_access_token'];
		}

		return $install;
	}
}
