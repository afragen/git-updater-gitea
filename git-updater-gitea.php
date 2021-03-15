<?php
/**
 * Git Updater - Gitea.
 * Requires GitHub Updater plugin.
 *
 * @package git-updater-gitea
 * @author  Andy Fragen
 * @link    https://github.com/afragen/git-updater-gitea
 * @link    https://github.com/afragen/github-updater
 */

/**
 * Plugin Name:       GitHub Updater - Gitea
 * Plugin URI:        https://github.com/afragen/git-updater-gitea
 * Description:       Add Gitea hosted repositories to the GitHub Updater plugin.
 * Version:           0.6.0
 * Author:            Andy Fragen
 * License:           MIT
 * Network:           true
 * Domain Path:       /languages
 * Text Domain:       git-updater-gitea
 * GitHub Plugin URI: https://github.com/afragen/git-updater-gitea
 * Primary Branch:    main
 * Requires at least: 5.2
 * Requires PHP:      7.0
 */

namespace Fragen\Git_Updater\Gitea;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

( new Bootstrap() )->load_hooks();

add_action(
	'plugins_loaded',
	function() {
		( new Bootstrap() )->run();
	}
);
