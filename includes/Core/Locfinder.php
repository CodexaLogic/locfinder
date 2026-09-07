<?php
/**
 * Defines the core plugin class.
 *
 * @package Locfinder
 */

namespace Locfinder\Core;

use Locfinder\Admin\Admin;
use Locfinder\Blocks\BlockRegistrar;
use Locfinder\Api\AjaxController;
use Locfinder\Frontend\Frontend;
use Locfinder\Frontend\Shortcode;
use Locfinder\PostTypes\LocationMeta;
use Locfinder\PostTypes\LocationPostType;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Bootstraps and wires all plugin hooks.
 */
class Locfinder {

	/**
	 * Registers every plugin hook with WordPress.
	 *
	 * Sets up post types, admin and public hooks, and registers shortcodes.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->defineAdminHooks();
		$this->definePostTypeHooks();
		$this->definePostMetaHooks();
		$this->defineFrontendHooks();
		$this->defineBlockHooks();
		$this->defineShortcodeHooks();
		$this->defineAjaxHooks();
		$this->defineLocationsPageHooks();
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	private function defineAdminHooks(): void {
		$admin = new Admin();
		add_action('admin_enqueue_scripts', [$admin, 'enqueueFiles']);
		add_action('admin_menu',            [$admin, 'addAdminMenu']);
		add_action('admin_init',            [$admin, 'settingsInit']);
		add_filter('locfinder/settings_tabs', [$admin, 'filterSettingsTabs']);
		add_filter('script_loader_tag', [$admin, 'updateScriptTag'], 10, 2);
	}

	/**
	 * Registers front-end hooks.
	 *
	 * @return void
	 */
	private function defineFrontendHooks(): void {
		$frontend = new Frontend();
		add_action('wp_enqueue_scripts', [$frontend, 'enqueueFiles']);
		add_filter('template_include',   [$frontend, 'loadTemplate'], 10, 1);
		add_filter('script_loader_tag', [$frontend, 'updateScriptTag'], 10, 2);
		add_action('init', [$frontend, 'registerBlockTemplate']);
	}

	/**
	 * Registers the custom post type and taxonomy hooks.
	 *
	 * @return void
	 */
	private function definePostTypeHooks(): void {
		$postType = new LocationPostType();
		add_action('init', [$postType, 'register'], 10);
	}

	/**
	 * Registers post meta hooks for the location CPT.
	 *
	 * @return void
	 */
	private function definePostMetaHooks(): void {
		$postMeta = new LocationMeta();
		add_action('init', [$postMeta, 'registerPostMeta'], 10);
		add_action('add_meta_boxes', [$postMeta, 'registerMetaBox'], 10);
		add_action('save_post_locfinder_location', [$postMeta, 'saveLocationMeta'], 10, 2);
		add_action('before_delete_post', [$postMeta, 'deleteLocationData'], 10, 1);
		add_action('transition_post_status', [$postMeta, 'flushCacheOnStatusChange'], 10, 3);
		add_action('set_object_terms', [$postMeta, 'flushCacheOnTermChange'], 10, 6);
	}

	/**
	 * Registers Gutenberg block hooks.
	 *
	 * @return void
	 */
	private function defineBlockHooks(): void {
		$blocks = new BlockRegistrar();
		add_action('init', [$blocks, 'registerBlocks']);
	}

	/**
	 * Registers shortcode hooks.
	 *
	 * @return void
	 */
	private function defineShortcodeHooks(): void {
		$shortcodes = new Shortcode();
		add_action('init', [$shortcodes, 'register']);
	}

	/**
	 * Registers AJAX hooks.
	 *
	 * @return void
	 */
	private function defineAjaxHooks(): void {
		$ajax = new AjaxController();
		add_action('wp_ajax_locfinder_get_results', [$ajax, 'getLocationResults']);
		add_action('wp_ajax_nopriv_locfinder_get_results', [$ajax, 'getLocationResults']);
		add_action('wp_ajax_locfinder_get_location_details', [$ajax, 'getLocationDetails']);
		add_action('wp_ajax_nopriv_locfinder_get_location_details', [$ajax, 'getLocationDetails']);
		add_action('wp_ajax_locfinder_clear_geo_cache', [$ajax, 'clearGeocodeCache']);
	}

	/**
	 * Registers hooks for the auto-created Locations page.
	 *
	 * @return void
	 */
	private function defineLocationsPageHooks(): void {
		$locationsPage = new LocationsPage();
		add_action('admin_notices', [$locationsPage, 'maybeShowMissingPageNotice']);
	}
}
