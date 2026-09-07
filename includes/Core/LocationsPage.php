<?php
/**
 * Creates and manages the auto generated Locations page.
 *
 * @package Locfinder
 */

namespace Locfinder\Core;

use Locfinder\Utilities\Options;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Manages the plugin's Locations page.
 */
class LocationsPage {

	/**
	 * Option name containing the Locations page ID.
	 *
	 * Must match General::getSlug().
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'locfinder_general';

	/**
	 * Option key containing the Locations page ID.
	 *
	 * Must match the key used by General::renderLocationsPage().
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'locations_page_id';

	/**
	 * Creates the Locations page if a valid page is not already assigned.
	 *
	 * @return void
	 */
	public static function install(): void {
		if (self::pageIsValid(self::getPageId())) {
			return;
		}

		$radiusOptions = Options::getRadiusValueList();

		$pageId = wp_insert_post([
			'post_title'   => __('Locations', 'locfinder'),
			'post_name'    => 'location-finder',
			'post_content' => sprintf(
				'[locfinder grid_cols="3" default_radius="%d"]',
				!empty($radiusOptions) ? min($radiusOptions) : 0
			),
			'post_status' => 'publish',
			'post_type'   => 'page',
		], true);

		if (is_wp_error($pageId) || !$pageId) {
			return;
		}

		$general                   = get_option(self::OPTION_NAME, []);
		$general                   = is_array($general) ? $general : [];
		$general[self::OPTION_KEY] = $pageId;

		update_option(self::OPTION_NAME, $general);
		Options::flush();
	}

	/**
	 * Gets the stored Locations page ID.
	 *
	 * @return int  The page ID, or 0 if none is stored.
	 */
	public static function getPageId(): int {
		return (int) Options::getOption(self::OPTION_KEY, 0);
	}

	/**
	 * Checks whether a page ID references an existing, non-trashed page.
	 *
	 * @param  int $pageId  The page ID to check.
	 * @return bool         True if the page exists and isn't trashed.
	 */
	public static function pageIsValid(int $pageId): bool {
		if ($pageId <= 0) {
			return false;
		}

		$post = get_post($pageId);

		return $post instanceof \WP_Post
			&& $post->post_type === 'page'
			&& $post->post_status !== 'trash';
	}

	/**
	 * Shows an admin notice when the assigned Locations page no longer exists.
	 *
	 * @return void
	 */
	public function maybeShowMissingPageNotice(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$pageId = self::getPageId();

		if ($pageId === 0 || self::pageIsValid($pageId)) {
			return;
		}

		$settingsUrl = admin_url('admin.php?page=locfinder_general');

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %1$s opening <a> tag, %2$s closing </a> tag */
					__('Location Finder\'s "Locations" page no longer exists. %1$sChoose or create a new one%2$s in General Settings.', 'locfinder'),
					'<a href="' . esc_url($settingsUrl) . '">',
					'</a>'
				),
				['a' => ['href' => true]]
			)
		);
	}
}

