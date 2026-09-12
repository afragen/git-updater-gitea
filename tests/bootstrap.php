<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Git_Updater_Gitea
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	//$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the main Git Updater plugin first so its base classes are available
	// (in-container it is mounted at wp-content/plugins/git-updater).
	$gu_plugin = '/var/www/html/wp-content/plugins/git-updater/git-updater.php';
	if ( ! file_exists( $gu_plugin ) ) {
		$gu_plugin = dirname( dirname( __DIR__ ) ) . '/git-updater/git-updater.php';
	}
	if ( file_exists( $gu_plugin ) ) {
		require $gu_plugin;
	}

	require dirname( dirname( __FILE__ ) ) . '/git-updater-gitea.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Load the Git Updater base classes (API, traits, OAuth_Connect, Base, …) from the
// mounted main plugin (in-container) or a sibling git-updater checkout, so the
// API-plugin tests can extend API without committing git-updater as a Composer package.
$gu_src = null;
foreach ( array( '/var/www/html/wp-content/plugins/git-updater/src/Git_Updater', dirname( __DIR__ ) . '/../git-updater/src/Git_Updater', dirname( __DIR__ ) . '/git-updater/src/Git_Updater' ) as $candidate ) {
	if ( is_dir( $candidate ) ) {
		$gu_src = $candidate;
		break;
	}
}
if ( $gu_src ) {
	spl_autoload_register(
		static function ( $class ) use ( $gu_src ) {
			$prefix = 'Fragen\\Git_Updater\\';
			if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
				return;
			}
			$file = $gu_src . '/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require $file;
			}
		}
	);
}

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
