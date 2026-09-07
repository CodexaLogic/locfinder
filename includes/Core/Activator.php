<?php
/**
 * Activation routines for the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Core;

use Locfinder\Database\Installer;
use Locfinder\PostTypes\LocationPostType;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles activation routines for the Locfinder plugin.
 */
class Activator {

	/**
	 * Handles tasks that run when the plugin is activated.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if (!Installer::install()) {
			deactivate_plugins(plugin_basename(LOCFINDER_DIR . 'locfinder.php'));
			wp_die(
				esc_html__('Location Finder could not create its required database tables. Please check your database permissions and try again.', 'locfinder'),
				esc_html__('Plugin Activation Error', 'locfinder'),
				['back_link' => true]
			);
		}

		$cpt = new LocationPostType();
		$cpt->register();

		LocationsPage::install();

		flush_rewrite_rules();
	}
}
