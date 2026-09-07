<?php
/**
 * PSR-4 autoloader for the Locfinder plugin.
 *
 * Maps the Locfinder\ namespace to the includes/ directory. This file must
 * be loaded manually by locfinder.php before plugin classes are referenced.
 *
 * @package Locfinder
 */

namespace Locfinder\Core;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the PSR-4 autoloading for the Locfinder plugin.
 */
class Autoloader {

	/**
	 * The root namespace prefix for this plugin.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'Locfinder\\';

	/**
	 * Registers the autoloader with PHP's SPL autoload stack.
	 *
	 * Call this once from locfinder.php immediately after requiring this file.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register([self::class, 'load']);
	}

	/**
	 * Loads the file for a given fully-qualified class name.
	 *
	 * Strips the root namespace prefix, converts namespace separators to
	 * directory separators, and requires the resulting file path.
	 *
	 * @param  string $class  Fully-qualified class name.
	 * @return void
	 */
	private static function load(string $class): void {
		if (strncmp($class, self::NAMESPACE_PREFIX, strlen(self::NAMESPACE_PREFIX)) !== 0) {
			return;
		}

		// Restrict class names to expected namespace characters before resolving the file path.
		if (!preg_match('/^[A-Za-z0-9_\\\\]+$/', $class)) {
			return;
		}

		$baseDir  = LOCFINDER_DIR . 'includes/';
		$relative = substr($class, strlen(self::NAMESPACE_PREFIX));
		$file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

		if (is_file($file)) {
			require_once $file;
		}
	}
}
