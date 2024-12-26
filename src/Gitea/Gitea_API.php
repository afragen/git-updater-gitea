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
	 * @param \stdClass $type plugin|theme.
	 */
	public function __construct( $type = null ) {
		parent::__construct();
		$this->type     = $type;
		$this->response = $this->get_repo_cache();
		$this->set_default_credentials();
		$this->settings_hook( $this );
		$this->add_settings_subtab();
		$this->add_install_fields( $this );
	}

	/**
	 * Set default credentials if option not set.
	 */
	protected function set_default_credentials() {
		$running_servers = Singleton::get_instance( 'Base', $this )->get_running_git_servers();
		$set_credentials = false;
		if ( ! isset( static::$options['gitea_access_token'] ) ) {
			static::$options['gitea_access_token'] = null;
			$set_credentials                       = true;
		}
		if ( empty( static::$options['gitea_access_token'] )
			&& in_array( 'gitea', $running_servers, true )
		) {
			$this->gitea_error_notices();
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
	 * @return bool
	 */
	public function get_remote_tag() {
		return $this->get_remote_api_tag( '/repos/:owner/:repo/releases' );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param null $changes Changelog filename - (deprecated).
	 *
	 * @return mixed
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'gitea', '/repos/:owner/:repo/raw/:branch/:changelog' );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return mixed
	 */
	public function get_remote_readme() {
		return $this->get_remote_api_readme( 'gitea', '/repos/:owner/:repo/raw/:branch/readme.txt' );
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @return mixed
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( '/repos/:owner/:repo' );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return mixed
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
	 * Return list of repository assets.
	 *
	 * @return array
	 */
	public function get_repo_assets() {
		return $this->get_remote_api_assets( 'gitea', '/repos/:owner/:repo/contents/:path' );
	}

	/**
	 * Return list of files at GitHub repo root.
	 *
	 * @return array
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
		 * If a branch has been given, use branch.
		 * If branch is primary branch (default) and tags are used, use newest tag.
		 */
		if ( $this->type->primary_branch !== $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch . '.zip';
		} else {
			$endpoint .= $this->type->newest_tag . '.zip';
		}

		// Create endpoint for branch switching.
		if ( $branch_switch ) {
			$endpoint = $branch_switch . '.zip';
		}

		$download_link = $download_link_base . $endpoint;

		/**
		 * Filter download link so developers can point to specific ZipFile
		 * to use as a download link during a branch switch.
		 *
		 * @since 8.8.0
		 * @since 10.0.0
		 *
		 * @param string    $download_link Download URL.
		 * @param /stdClass $this->type    Repository object.
		 * @param string    $branch_switch Branch or tag for rollback or branch switching.
		 */
		return apply_filters( 'gu_post_construct_download_link', $download_link, $this->type, $branch_switch );
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
	 * @param \stdClass|array $response Response from API call for tags.
	 *
	 * @return \stdClass|array Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
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
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return array $arr Array of meta variables.
	 */
	public function parse_meta_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$arr      = [];
		$response = [ $response ];

		array_filter(
			$response,
			function ( $e ) use ( &$arr ) {
				$arr['private']      = $e->private;
				$arr['last_updated'] = $e->updated_at;
				$arr['watchers']     = $e->watchers_count;
				$arr['forks']        = $e->forks_count;
				$arr['open_issues']  = isset( $e->open_issues_count ) ? $e->open_issues_count : 0;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog in base64.
	 *
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return void|array|\stdClass $arr Array of changes in base64, object if error.
	 */
	public function parse_changelog_response( $response ) {
	}

	/**
	 * Parse API response and return array of branch data.
	 *
	 * @param \stdClass $response API response.
	 *
	 * @return array Array of branch data.
	 */
	public function parse_branch_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$response = is_string( $response ) ? [] : $response;
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
	 * @param \stdClass|array $response  Response from API call.
	 * @param array           $repo_type Array of repository data.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = [];
		$rollback = [];

		foreach ( (array) $response as $tag ) {
			$download_link    = implode(
				'/',
				[
					$repo_type['base_uri'],
					'repos',
					$this->type->owner,
					$this->type->slug,
					'archive/',
				]
			);
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_link . $tag . '.zip';
		}

		return [ $tags, $rollback ];
	}

	/**
	 * Parse remote root files/dirs.
	 *
	 * @param \stdClass|array $response  Response from API call.
	 *
	 * @return array
	 */
	protected function parse_contents_response( $response ) {
		$files = [];
		$dirs  = [];
		foreach ( $response as $content ) {
			if ( 'file' === $content->type ) {
				$files[] = $content->name;
			}
			if ( 'dir' === $content->type ) {
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
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return \stdClass|array
	 */
	protected function parse_asset_dir_response( $response ) {
		$assets = [];

		if ( isset( $response->message ) || is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response as $asset ) {
			$assets[ $asset->name ] = $asset->download_url;
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

		add_settings_field(
			'gitea_access_token',
			esc_html__( 'Gitea Access Token', 'git-updater-gitea' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'git_updater_gitea_install_settings',
			'gitea_settings',
			[
				'id'    => 'gitea_access_token',
				'token' => true,
				'class' => $auth_required['gitea'] ? '' : 'hidden',
			]
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
	 * Display Gitea error admin notices.
	 */
	public function gitea_error_notices() {
		add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', [ $this, 'gitea_error' ] );
	}

	/**
	 * Generate error message for missing Gitea Access Token.
	 */
	public function gitea_error() {
		$auth_required = $this->get_class_vars( 'Settings', 'auth_required' );
		$error_code    = $this->get_error_codes();

		if ( ! isset( $error_code['gitea'] )
			&& empty( static::$options['gitea_access_token'] )
			&& $auth_required['gitea']
		) {
			self::$error_code['gitea'] = [
				'git'   => 'gitea',
				'error' => true,
			];
			if ( ! \WP_Dismiss_Notice::is_admin_notice_active( 'gitea-error-1' ) ) {
				return;
			}
			?>
			<div data-dismissible="gitea-error-1" class="error notice is-dismissible">
				<p>
					<?php esc_html_e( 'You must set a Gitea Access Token.', 'git-updater-gitea' ); ?>
				</p>
			</div>
			<?php
		}
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
