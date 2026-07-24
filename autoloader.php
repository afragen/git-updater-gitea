<?php
/**
 * Custom PSR-4 autoloader for the Git Updater API plugins.
 *
 * Loads the plugin's own classes from src/. Composer still autoloads the
 * lightweight vendor packages when vendor/ is installed (it is gitignored and
 * created by `composer install`). On a live site the main Git Updater plugin
 * provides the shared base classes (API, Singleton, …).
 *
 * @param string $plugin_dir Absolute path to the plugin directory.
 * @param string $subdir     Plugin sub-namespace, e.g. 'Bitbucket' | 'Gitea' | 'GitLab'.
 */

if ( ! function_exists( 'fragen_git_updater_register_autoloader' ) ) {
	function fragen_git_updater_register_autoloader( $plugin_dir, $subdir ) {
		$prefixes = array(
			'Fragen\\Git_Updater\\' . $subdir . '\\' => $plugin_dir . '/src',
			'Fragen\\Git_Updater\\API\\'             => $plugin_dir . '/src/' . $subdir,
		);

		spl_autoload_register(
			static function ( $class ) use ( $prefixes ) {
				foreach ( $prefixes as $prefix => $base ) {
					if ( strncmp( $prefix, $class, strlen( $prefix ) ) === 0 ) {
						$file = $base . '/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
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
