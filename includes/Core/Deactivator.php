<?php
/**
 * Deactivation routines for the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Core;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the deactivation routines for the Locfinder plugin.
 */
class Deactivator {

	/**
	 * Handles tasks that run when the plugin is deactivated.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
