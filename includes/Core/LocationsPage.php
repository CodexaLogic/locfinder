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
	public const OPTION_NAME = 'locfinder_general';

	/**
	 * Option key containing the Locations page ID.
	 *
	 * Must match the key used by General::renderLocationsPage().
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'locations_page_id';

	/**
	 * Post meta key marking the page the plugin created.
	 *
	 * @var string
	 */
	private const GENERATED_META = '_locfinder_generated';

	/**
	 * URL slug given to the page the plugin creates.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'location-finder';

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
			'post_name'    => self::PAGE_SLUG,
			'post_content' => sprintf(
				'[locfinder grid_cols="3" default_radius="%d"]',
				!empty($radiusOptions) ? min($radiusOptions) : 0
			),
			'post_status' => 'publish',
			'post_type'   => 'page',
			'meta_input'  => [self::GENERATED_META => 1],
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
	 * Retitles the plugin-created page when the chosen name changes.
	 *
	 * Runs after the General settings are saved. The page is retitled only
	 * when the plugin created it and its title still matches the name the
	 * previous settings produced, so a title the site owner edited is never
	 * overwritten. The URL slug is unchanged.
	 *
	 * @param  mixed $oldValue  General settings before the save.
	 * @param  mixed $newValue  General settings after the save.
	 * @return void
	 */
	public function syncTitle(mixed $oldValue, mixed $newValue): void {
		$oldValue = is_array($oldValue) ? $oldValue : [];
		$newValue = is_array($newValue) ? $newValue : [];

		$oldTitle = Options::resolveItemLabels($oldValue)['plural'];
		$newTitle = Options::resolveItemLabels($newValue)['plural'];

		if ($oldTitle === $newTitle) {
			return;
		}

		$pageId = (int) ($newValue[self::OPTION_KEY] ?? 0);

		if (!self::pageIsValid($pageId) || !self::isGeneratedPage($pageId)) {
			return;
		}

		$currentTitle = html_entity_decode(get_post($pageId)->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if ($currentTitle !== $oldTitle) {
			return;
		}

		wp_update_post([
			'ID'         => $pageId,
			'post_title' => $newTitle,
		]);
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
	 * Checks whether a page was created by the plugin.
	 *
	 * Pages created before the marker existed are recognized by the URL slug
	 * the plugin gave them.
	 *
	 * @param  int $pageId  The page ID to check.
	 * @return bool         True if the plugin created the page.
	 */
	private static function isGeneratedPage(int $pageId): bool {
		if (get_post_meta($pageId, self::GENERATED_META, true)) {
			return true;
		}

		return (bool) preg_match('/^' . preg_quote(self::PAGE_SLUG, '/') . '(-\d+)?$/', (string) get_post_field('post_name', $pageId));
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
					/* translators: %1$s: plural name chosen in settings, mid-sentence, e.g. "locations" or "programs". %2$s: opening <a> tag. %3$s: closing </a> tag. */
					__('The page that lists your %1$s no longer exists. %2$sChoose or create a new one%3$s in General Settings.', 'locfinder'),
					esc_html(Options::getItemLabels()['pluralLower']),
					'<a href="' . esc_url($settingsUrl) . '">',
					'</a>'
				),
				['a' => ['href' => true]]
			)
		);
	}
}

