<?php
/**
 * Defines the base class for settings pages.
 *
 * Each settings page owns its own option, registered under its own settings
 * group. Pages define their slug, title, fields, and sanitization, while
 * this class provides shared rendering, option, capability, and Pro helpers.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Base class for Locfinder settings pages.
 */
abstract class Base {

	/**
	 * Gets the unique slug for the settings page.
	 *
	 * The slug is used in the URL to identify the page, and also doubles
	 * as the page option name and settings group.
	 *
	 * @return string  Unique slug identifying the page in the URL, option name, and settings group.
	 */
	abstract public function getSlug(): string;

	/**
	 * Gets the human-readable title for the settings page.
	 *
	 * @return string  Human-readable title shown in the settings navigation.
	 */
	abstract public function getTitle(): string;

	/**
	 * Registers settings, sections, and fields for the settings page.
	 *
	 * Called during admin_init by the admin router. Field name="" attributes
	 * must use $this->getOptionName() rather than a hardcoded string so
	 * each page's fields are submitted under that page's own option.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Sanitizes and validates options before they are saved.
	 *
	 * Registered directly as the page's register_setting() sanitize callback
	 * via a thin adapter in Admin::makeSanitizeCallback(), which supplies
	 * $existing from get_option($this->getOptionName()) so the method's
	 * signature never needs to change regardless of how it is wired up.
	 *
	 * @param  array $options   Raw submitted values for the page's option only.
	 * @param  array $existing  The page's own previously saved option value.
	 * @return array            Sanitized options ready to be stored via update_option().
	 */
	abstract public function sanitize(array $options, array $existing): array;

	/**
	 * Gets the WordPress option name the page's settings are stored under.
	 *
	 * Defaults to the page slug, which is guaranteed unique across all pages.
	 * Override only if a page needs to share an option with another page, or
	 * to use a name that differs from its slug.
	 *
	 * @return string  WordPress option name for the page's settings.
	 */
	public function getOptionName(): string {
		return $this->getSlug();
	}

	/**
	 * Gets the settings group used by the page for settings_fields().
	 *
	 * Defaults to the page slug, matching getOptionName().
	 *
	 * @return string  Settings group name passed to settings_fields().
	 */
	public function getSettingsGroup(): string {
		return $this->getSlug();
	}

	/**
	 * Gets the capability required to view and manage the settings page.
	 *
	 * Override in subclasses if a different capability is needed.
	 *
	 * @return string  WordPress capability slug, defaults to 'manage_options'.
	 */
	public function getCapability(): string {
		return 'manage_options';
	}

	/**
	 * Renders the settings page.
	 *
	 * Uses the shared options-display.php partial for layout.
	 *
	 * @return void
	 */
	public function render(): void {
		if (!current_user_can($this->getCapability())) {
			return;
		}

		$currentPageSlug     = $this->getSlug();
		$pageTitle           = $this->getTitle();
		$version             = defined('LOCFINDER_VERSION') ? LOCFINDER_VERSION : '1.0.0';
		$tabs                = apply_filters('locfinder/settings_tabs', []);
		$settingsGroup       = $this->getSettingsGroup();
		$hasSaveableSettings = $this->hasSaveableSettings();

		require LOCFINDER_DIR . 'includes/Admin/views/options-display.php';
	}

	/**
	 * Indicates whether the page should be automatically registered by Admin.
	 *
	 * Override in subclasses to return false if the page is registered manually.
	 *
	 * @return bool  True if the page should be auto-registered, false otherwise.
	 */
	public function shouldAutoRegister(): bool {
		return true;
	}

	/**
	 * Indicates whether the page has options that can be saved.
	 *
	 * Controls whether options-display.php wraps the page in a
	 * <form action="options.php"> with settings_fields() and a Save
	 * button. Override to return false for a informational page
	 * (see Shortcodes) that has no option to submit.
	 *
	 * @return bool  True if the page should render a save form, false for a read-only page.
	 */
	public function hasSaveableSettings(): bool {
		return true;
	}

	/**
	 * Gets a stored option value from the page's option.
	 *
	 * Each page reads only from its own option row, e.g. MapDisplay reads
	 * from get_option('locfinder_map_display'), General reads from
	 * get_option('locfinder_general'). This guarantees a page can only
	 * read values it is responsible for saving.
	 *
	 * @param  string $key      The option key to retrieve.
	 * @param  mixed  $default  Fallback if the key is missing.
	 * @return mixed            The stored value, or $default if not set.
	 */
	protected function getPageOption(string $key, mixed $default = ''): mixed {
		$opts = get_option($this->getOptionName(), []);
		$opts = is_array($opts) ? $opts : [];
		return $opts[$key] ?? $default;
	}
}
