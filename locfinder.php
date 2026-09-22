<?php
/**
 * Plugin Name:       Location Finder – Store Locator & Resource Directory
 * Plugin URI:        https://codexalogic.com/products/locfinder
 * Description:       Build searchable store locators, service directories, and resource finders with optional Google Maps, category and radius filters, and list or grid layouts.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Codexa
 * Author URI:        https://codexalogic.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       locfinder
 * Domain Path:       /languages
 *
 * @package Locfinder
 * @link    https://codexalogic.com
 */

if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants before registering the autoloader.
if (!defined('LOCFINDER_VERSION')) {
	define('LOCFINDER_VERSION', '1.1.0');
}
if (!defined('LOCFINDER_NAME')) {
	define('LOCFINDER_NAME', 'locfinder');
}
if (!defined('LOCFINDER_DIR')) {
	define('LOCFINDER_DIR', plugin_dir_path(__FILE__));
}
if (!defined('LOCFINDER_URL')) {
	define('LOCFINDER_URL', plugin_dir_url(__FILE__));
}
if (!defined('LOCFINDER_PRO_URL')) {
	define('LOCFINDER_PRO_URL', 'https://codexalogic.com/products/locfinder-pro');
}

require_once LOCFINDER_DIR . 'includes/Core/Autoloader.php';
Locfinder\Core\Autoloader::register();

register_activation_hook(__FILE__, [Locfinder\Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Locfinder\Core\Deactivator::class, 'deactivate']);

// Check for database schema upgrades on each plugin load.
add_action('plugins_loaded', [Locfinder\Database\Installer::class, 'maybeUpgrade']);

(new Locfinder\Core\Locfinder())->run();
