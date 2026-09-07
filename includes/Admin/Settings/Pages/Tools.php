<?php
/**
 * Defines the tools settings page.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Settings\Base;
use Locfinder\Utilities\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings and fields for the tools settings page.
 */
class Tools extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_tools';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Tools', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_tools',
			__('Debugging & Maintenance Tools', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'enable_debug_mode',
			__('Debug Mode', 'locfinder'),
			[$this, 'renderDebugMode'],
			$this->getSlug(),
			'locfinder_tools'
		);

		add_settings_field(
			'clear_cache_button',
			__('Clear Geocode Cache', 'locfinder'),
			[$this, 'renderClearCacheButton'],
			$this->getSlug(),
			'locfinder_tools'
		);

		add_settings_section(
			'locfinder_uninstall',
			__('Uninstall', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__('Delete Data on Uninstall', 'locfinder'),
			[$this, 'renderDeleteDataOnUninstall'],
			$this->getSlug(),
			'locfinder_uninstall'
		);
	}

	/**
	 * Renders the debug mode checkbox.
	 *
	 * When enabled, diagnostic information is output to the browser console
	 * for users with the manage_options capability. Controlled by a capability
	 * check in Frontend::enqueueFiles() rather than just this setting alone.
	 *
	 * @return void
	 */
	public function renderDebugMode(): void {
		$on   = !empty($this->getPageOption('enable_debug_mode', 0));
		$id   = 'locfinder_debug_mode';
		$name = $this->getOptionName() . '[enable_debug_mode]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Enable debug logs for admins on the front end', 'locfinder')
		);
	}

	/**
	 * Renders the clear geocode cache button.
	 *
	 * The button triggers an AJAX request handled by
	 * AjaxController::clearGeocodeCache() rather than the Settings API.
	 * No sanitize() callback is required.
	 *
	 * @return void
	 */
	public function renderClearCacheButton(): void {
		$nonce = wp_create_nonce('locfinder_clear_geo_cache');

		printf(
			'<button type="button" id="locfinder-clear-cache-btn" class="button button-outline" data-nonce="%1$s" data-action="locfinder_clear_geo_cache">%2$s</button>
			<span id="locfinder-clear-cache-message" class="locfinder-admin__message"></span>',
			esc_attr($nonce),
			esc_html__('Clear Geocode Cache', 'locfinder')
		);
	}

	/**
	 * Renders the delete data on uninstall checkbox.
	 *
	 * Unchecked by default to prevent accidental data loss. When checked,
	 * all location posts, custom tables, options, and transients are removed
	 * when the plugin is deleted via uninstall.php.
	 *
	 * @return void
	 */
	public function renderDeleteDataOnUninstall(): void {
		$on   = !empty($this->getPageOption('delete_data_on_uninstall', 0));
		$id   = 'delete_data_on_uninstall';
		$name = $this->getOptionName() . '[delete_data_on_uninstall]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>
			<p class="description locfinder-admin__warning">%5$s</p>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Permanently delete all location data when the plugin is uninstalled', 'locfinder'),
			esc_html__('Warning: This will permanently delete all locations, contacts, hours, and plugin settings. This cannot be undone.', 'locfinder')
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * The clear cache button is AJAX-only and has no field to sanitize here.
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		$out['enable_debug_mode']        = Sanitizer::checkboxKey($options, $existing, 'enable_debug_mode');
		$out['delete_data_on_uninstall'] = Sanitizer::checkboxKey($options, $existing, 'delete_data_on_uninstall');

		return $out;
	}
}
