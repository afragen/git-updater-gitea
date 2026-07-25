<?php
/**
 * Custom PSR-4 autoloader for the Git Updater API plugins.
 *
 * Loads the plugin's own classes from src/. Composer still autoloads the
 * lightweight vendor packages when vendor/ is installed (it is gitignored and
 * created by `composer install`). On a live site the main Git Updater plugin
 * provides the shared base classes (API, Singleton, …).
 *
 * @package Git_Updater
 */

if ( ! function_exists( 'git_updater_register_autoloader' ) ) {
	/**
	 * Register a PSR-4 autoloader for this plugin's own classes.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @param string $subdir     Plugin sub-namespace, e.g. 'Bitbucket' | 'Gitea' | 'GitLab'.
	 * @return void
	 */
	function git_updater_register_autoloader( $plugin_dir, $subdir ) {
		$prefixes = [
			'Fragen\Git_Updater\\' . $subdir . '\\' => $plugin_dir . '/src',
			'Fragen\Git_Updater\API\\'              => $plugin_dir . '/src/' . $subdir,
		];

		spl_autoload_register(
			static function ( $class_name ) use ( $prefixes ) {
				foreach ( $prefixes as $prefix => $base ) {
					if ( strncmp( $prefix, $class_name, strlen( $prefix ) ) === 0 ) {
						$file = $base . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
						if ( is_file( $file ) ) {
							require $file;
							return true;
						}
					}
				}
				return false;
			}
		);
	}
}
