<?php
/**
 * Typed getters for Locfinder plugin options.
 *
 * Provides centralized option access with consistent typing, defaults, and
 * in-request caching.
 *
 * @package Locfinder
 */

namespace Locfinder\Utilities;

use Locfinder\Database\LocationRepository;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Utility class for accessing and caching Locfinder plugin options.
 */
class Options {

	/**
	 * In-request cache of all merged plugin options.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * In-request cache for the resolved default map center.
	 *
	 * Avoids re-querying the locations table or reparsing the site
	 * timezone every time getDefaultLat()/getDefaultLng() are called
	 * within the same request.
	 *
	 * @var array{lat: float, lng: float}|null
	 */
	private static ?array $resolvedDefaultCenter = null;

	/**
	 * Option names for settings pages that store grouped options.
	 *
	 * Documentation-only and separately stored settings are intentionally omitted.
	 *
	 * @var array<int,string>
	 */
	private const PAGE_OPTIONS = [
		'locfinder_general',
		'locfinder_map_display',
		'locfinder_search_filters',
		'locfinder_results',
		'locfinder_data',
		'locfinder_a11y',
		'locfinder_tools',
	];

	/**
	 * Default hex color used when no pin/primary color has been saved.
	 *
	 * Single source of truth — also referenced by the admin field
	 * renderers and sanitizers in MapDisplay.php and General.php so the
	 * displayed default and the frontend-read default can never drift
	 * apart again.
	 *
	 * @var string
	 */
	public const DEFAULT_COLOR = '#00606b';

	/**
	 * Gets all plugin options merged into one in-request cache.
	 *
	 * Reads every settings page's own option and merges them into a single
	 * flat array so every typed getter works without knowing which page owns
	 * a given key. Later entries in PAGE_OPTIONS win on key collision, but
	 * pages should not define overlapping keys.
	 *
	 * @return array<string,mixed>  Merged options from all settings pages.
	 */
	public static function getAllOptions(): array {
		if (self::$cache === null) {
			$merged = [];
			foreach (self::PAGE_OPTIONS as $optionName) {
				$pageOpts = get_option($optionName, []);
				if (is_array($pageOpts)) {
					$merged = array_merge($merged, $pageOpts);
				}
			}
			self::$cache = $merged;
		}
		return self::$cache;
	}

	/**
	 * Gets a single option value by key.
	 *
	 * @param  string $key      The option key to retrieve.
	 * @param  mixed  $default  Fallback value if the key is not set.
	 * @return mixed            The stored value, or $default if not set.
	 */
	public static function getOption(string $key, mixed $default = ''): mixed {
		return self::getAllOptions()[$key] ?? $default;
	}

	/**
	 * Flushes the in-request cache.
	 *
	 * Call after saving options so subsequent reads reflect the new values.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache                 = null;
		self::$resolvedDefaultCenter = null;
	}

	/* Typed getters */

	/**
	 * Gets the Google Maps API key.
	 *
	 * Empty string is the valid "not configured" state, intentionally no fallback.
	 *
	 * @return string  API key, or empty string if not configured.
	 */
	public static function getApiKey(): string {
		return (string) self::getOption('google_maps_api_key', '');
	}

	/**
	 * Gets the Google Maps Map ID for AdvancedMarkerElement support.
	 *
	 * Empty string is the valid "not configured" state, intentionally no fallback.
	 *
	 * @return string  Map ID, or empty string if not configured.
	 */
	public static function getMapId(): string {
		return (string) self::getOption('google_maps_map_id', '');
	}

	/**
	 * Gets the distance unit for radius values and distance display.
	 *
	 * @return string  'mi' or 'km'.
	 */
	public static function getDistanceUnit(): string {
		$value = (string) self::getOption('distance_unit', 'mi');
		return in_array($value, ['mi', 'km'], true) ? $value : 'mi';
	}

	/**
	 * Gets the resolved default map center latitude.
	 *
	 * @return float Latitude between -90 and 90.
	 */
	public static function getDefaultLat(): float {
		return self::resolveDefaultCenter()['lat'];
	}

	/**
	 * Gets the resolved default map center longitude.
	 *
	 * @return float Longitude between -180 and 180.
	 */
	public static function getDefaultLng(): float {
		return self::resolveDefaultCenter()['lng'];
	}

	/**
	 * Resolves the default map center through a fallback chain:
	 *
	 * 1. The admin's own saved value, if explicitly configured.
	 * 2. The average lat/lng of all saved, geocoded locations, so the
	 *    map centers on wherever the site's real data already is.
	 * 3. The site's WordPress timezone reference city, a rough regional
	 *    guess for brand-new sites with no locations yet.
	 * 4. The geographic center of the continental US, the last-resort
	 *    fallback when none of the above are available.
	 *
	 * @return array{lat: float, lng: float}
	 */
	private static function resolveDefaultCenter(): array {
		if (self::$resolvedDefaultCenter !== null) {
			return self::$resolvedDefaultCenter;
		}

		$lat = self::getOption('default_center_lat', '');
		$lng = self::getOption('default_center_lng', '');

		if (is_numeric($lat) && is_numeric($lng)) {
			return self::$resolvedDefaultCenter = [
				'lat' => max(-90, min(90, (float) $lat)),
				'lng' => max(-180, min(180, (float) $lng)),
			];
		}

		$averaged = (new LocationRepository())->getAverageCoordinates();

		if ($averaged !== null) {
			return self::$resolvedDefaultCenter = [
				'lat' => max(-90, min(90, $averaged['lat'])),
				'lng' => max(-180, min(180, $averaged['lng'])),
			];
		}

		$timezoneString = (string) get_option('timezone_string', '');

		if ($timezoneString !== '') {
			try {
				$location = (new \DateTimeZone($timezoneString))->getLocation();

				if (is_array($location) && is_numeric($location['latitude'] ?? null) && is_numeric($location['longitude'] ?? null)) {
					return self::$resolvedDefaultCenter = [
						'lat' => max(-90, min(90, (float) $location['latitude'])),
						'lng' => max(-180, min(180, (float) $location['longitude'])),
					];
				}
			} catch (\Exception $e) {
				// Invalid timezone string, fall through to the hardcoded default below.
			}
		}

		return self::$resolvedDefaultCenter = [
			'lat' => 39.8283,
			'lng' => -98.5795,
		];
	}

	/**
	 * Gets the default map zoom level.
	 *
	 * @return int  Zoom level, clamped between 1 and 20.
	 */
	public static function getDefaultZoom(): int {
		return max(1, min(20, (int) self::getOption('default_zoom', 11)));
	}

	/**
	 * Gets the number of results to show per page in search results.
	 *
	 * @return int  Results per page, clamped between 1 and 100.
	 */
	public static function getResultsPerPage(): int {
		return max(1, min(100, (int) self::getOption('results_per_page', 12)));
	}

	/**
	 * Gets the configured target for result links.
	 *
	 * @return string  'same' or 'new'.
	 */
	public static function getResultLinkTarget(): string {
		$value = (string) self::getOption('result_link_target', 'same');
		return in_array($value, ['same', 'new'], true) ? $value : 'same';
	}

	/**
	 * Gets the configured results list layout.
	 *
	 * @return string  'grid' or 'list'.
	 */
	public static function getResultsLayout(): string {
		$value = (string) self::getOption('results_layout', 'grid');
		return in_array($value, ['grid', 'list'], true) ? $value : 'grid';
	}

	/**
	 * Gets the configured results position relative to the map.
	 *
	 * @return string  'bottom', 'left', or 'right'.
	 */
	public static function getResultsPosition(): string {
		$value = (string) self::getOption('results_position', 'left');
		return in_array($value, ['bottom', 'left', 'right'], true) ? $value : 'left';
	}

	/**
	 * Gets the configured number of result columns used when Results Position
	 * is 'left' or 'right'. Has no effect when Results Position is 'bottom'.
	 *
	 * @return int  1 or 2.
	 */
	public static function getResultsSideColumns(): int {
		$value = (int) self::getOption('results_side_columns', 2);
		return in_array($value, [1, 2], true) ? $value : 2;
	}

	/**
	 * Determines whether at least one location has been published.
	 *
	 * Used to distinguish an empty site from a search with no matches.
	 *
	 * @return bool True if at least one published location exists.
	 */
	public static function hasAnyLocations(): bool {
		return (new LocationRepository())->countPublishedLocations() > 0;
	}

	/**
	 * Determines whether a given taxonomy is allowed for filtering locations.
	 *
	 * @param  string $taxonomy  The taxonomy to check.
	 * @return bool              True if the taxonomy is allowed.
	 */
	public static function isTaxonomyAllowed(string $taxonomy): bool {
		return $taxonomy === 'locfinder_category';
	}

	/**
	 * Determines whether the Open Now/Closed Now badge is displayed.
	 *
	 * Controls badge visibility only; badge content can be supplied by Pro.
	 *
	 * @return bool True if the badge is enabled.
	 */
	public static function getShowOpenNowBadge(): bool {
		return (bool) self::getOption('enable_open_now_badge', true);
	}

	/**
	 * Gets the custom HTML template used to render each result item.
	 *
	 * This feature is delegated entirely to the Pro plugin through the
	 * `locfinder/pro/result_item_template` filter.
	 *
	 * @return string  Custom HTML template supplied by the Pro add-on, or empty string if Pro is not active.
	 */
	public static function getResultItemTemplate(): string {
		return (string) apply_filters('locfinder/pro/result_item_template', '');
	}

	/**
	 * Gets the configured sorting method for search results.
	 *
	 * @return string  'distance', 'title', or 'date'.
	 */
	public static function getSortBy(): string {
		$value = (string) self::getOption('sort_by', 'title');
		return in_array($value, ['distance', 'title', 'date'], true) ? $value : 'title';
	}

	/**
	 * Gets the default search radius.
	 *
	 * A value of 0 represents no distance cutoff.
	 *
	 * @return int Radius in the configured distance unit.
	 */
	public static function getDefaultRadius(): int {
		return max(0, (int) self::getOption('default_radius', 0));
	}

	/**
	 * Gets the comma-separated list of radius values for the search form dropdown.
	 *
	 * The built-in list is fixed. Any plugin may supply its own values through
	 * the `locfinder_radius_values` filter.
	 *
	 * @return string  Comma-separated distances, e.g. '10,25,50,75'.
	 */
	public static function getRadiusValues(): string {
		return (string) apply_filters('locfinder_radius_values', '10,25,50,75');
	}

	/**
	 * Gets the configured radius values as positive integers.
	 *
	 * Invalid and non-positive values are ignored.
	 *
	 * @return int[] Positive radius values in configured order.
	 */
	public static function getRadiusValueList(): array {
		$values = array_map('trim', explode(',', self::getRadiusValues()));

		return array_values(array_filter(
			array_map(
				static fn ($value) => is_numeric($value) ? (int) $value : 0,
				$values
			),
			static fn ($value) => $value > 0
		));
	}

	/**
	 * Gets the placeholder text for the keyword search input.
	 *
	 * @return string  Placeholder text, falls back to 'Keyword' if not configured.
	 */
	public static function getKeywordPlaceholder(): string {
		$value = (string) self::getOption('keyword_placeholder', '');
		return $value !== '' ? $value : __('Keyword', 'locfinder');
	}

	/**
	 * Gets the placeholder text for the address search input.
	 *
	 * @return string  Placeholder text, falls back to 'Address or Zip Code' if not configured.
	 */
	public static function getAddressPlaceholder(): string {
		$value = (string) self::getOption('address_placeholder', '');
		return $value !== '' ? $value : __('Address or Zip Code', 'locfinder');
	}

	/**
	 * Gets the label for the search submit button.
	 *
	 * @return string  Button label, falls back to 'Search' if not configured.
	 */
	public static function getSearchButtonLabel(): string {
		$value = (string) self::getOption('search_button_label', '');
		return $value !== '' ? $value : __('Search', 'locfinder');
	}

	/**
	 * Gets the sr-only label for the radius select in the search form.
	 *
	 * @return string  Field label, falls back to 'Select Radius' if not configured.
	 */
	public static function getRadiusLabel(): string {
		$value = (string) self::getOption('radius_label', '');
		return $value !== '' ? $value : __('Select Radius', 'locfinder');
	}

	/**
	 * Gets the sr-only label for the category select in the search form.
	 *
	 * @return string  Field label, falls back to 'Select Category' if not configured.
	 */
	public static function getCategoryLabel(): string {
		$value = (string) self::getOption('category_label', '');
		return $value !== '' ? $value : __('Select Category', 'locfinder');
	}

	/**
	 * Gets the text for the "Any Distance" first option in the radius dropdown.
	 *
	 * @return string  First option text, falls back to 'Any Distance' if not configured.
	 */
	public static function getAnyDistanceText(): string {
		$value = (string) self::getOption('any_distance_text', '');
		return $value !== '' ? $value : __('Any Distance', 'locfinder');
	}

	/**
	 * Gets the text for the "All Categories" first option in the category dropdown.
	 *
	 * @return string  First option text, falls back to 'All Categories' if not configured.
	 */
	public static function getAllCategoriesText(): string {
		$value = (string) self::getOption('all_categories_text', '');
		return $value !== '' ? $value : __('All Categories', 'locfinder');
	}

	/**
	 * Gets the configured pin color for map markers.
	 *
	 * @return string  Hex color, falls back to self::DEFAULT_COLOR if not configured.
	 */
	public static function getPinColor(): string {
		return self::getColorOption('pin_color');
	}

	/**
	 * Gets the configured primary color.
	 *
	 * @return string  Hex color, falls back to self::DEFAULT_COLOR if not configured.
	 */
	public static function getPrimaryColor(): string {
		return self::getColorOption('primary_color');
	}

	/**
	 * Shared fallback logic for the two hex color options above.
	 *
	 * @param  string $optionKey  The option key to read.
	 * @return string             Hex color, falls back to self::DEFAULT_COLOR if not configured.
	 */
	private static function getColorOption(string $optionKey): string {
		$value = (string) self::getOption($optionKey, '');
		return $value !== '' ? $value : self::DEFAULT_COLOR;
	}

	/**
	 * Gets the custom pin icon URL used in place of the default SVG teardrop pin.
	 *
	 * Filtered through the `locfinder/pro/pin_icon_url` filter.
	 *
	 * @return string  Custom pin icon URL supplied by the Pro add-on, or empty string if Pro is not active.
	 */
	public static function getPinIconUrl(): string {
		return (string) apply_filters('locfinder/pro/pin_icon_url', '');
	}

	/**
	 * Returns per-term pin style overrides (color and/or icon URL) for a
	 * given taxonomy, keyed by term ID.
	 *
	 * Each shortcode and block instance passes its own resolved taxonomy here,
	 * so two instances on the same page using different taxonomies (e.g. one
	 * filtering by category, another by region) each get the correct colors
	 * for their own terms.
	 *
	 * Filtered through the `locfinder/pro/term_pin_styles` filter.
	 *
	 * @param  string $taxonomy  The taxonomy to get pin styles for.
	 * @return array<int,array{color?:string,iconUrl?:string}>
	 */
	public static function getTermPinStyles(string $taxonomy): array {
		$styles = apply_filters('locfinder/pro/term_pin_styles', [], $taxonomy);
		return is_array($styles) ? $styles : [];
	}

	/**
	 * Gets where the plugin should enqueue its assets.
	 *
	 * @return string  'shortcode_only' or 'all'.
	 */
	public static function getEnqueueOn(): string {
		$value = (string) self::getOption('enqueue_on', 'shortcode_only');
		return in_array($value, ['shortcode_only', 'all'], true) ? $value : 'shortcode_only';
	}

	/**
	 * Gets the taxonomy used for filtering locations in the search form.
	 *
	 * Allows the Pro plugin to override the taxonomy through the `locfinder_taxonomy_filter` filter.
	 *
	 * @return string  Taxonomy slug, falls back to 'locfinder_category'.
	 */
	public static function getTaxonomyFilter(): string {
		$value = (string) self::getOption('taxonomy_filter', '');

		return (string) apply_filters('locfinder_taxonomy_filter', $value !== '' ? $value : 'locfinder_category');
	}

	/**
	 * Gets the configured map height.
	 *
	 * @return string  CSS height value, falls back to '60vh' if not configured.
	 */
	public static function getMapHeight(): string {
		$value = (string) self::getOption('map_height', '');
		return $value !== '' ? $value : '60vh';
	}

	/**
	 * Gets the configured map minimum height.
	 *
	 * @return string  CSS min-height value, falls back to '300px' if not configured.
	 */
	public static function getMapMinHeight(): string {
		$value = (string) self::getOption('map_min_height', '');
		return $value !== '' ? $value : '300px';
	}

	/**
	 * Gets the configured map width.
	 *
	 * Empty means "not configured." The front end applies its own
	 * per-layout default in that case, full width for the "bottom"
	 * layout, a 50/50 split with the results panel for "left" and
	 * "right" layouts.
	 *
	 * @return string  CSS width value, or '' if not configured.
	 */
	public static function getMapWidth(): string {
		return (string) self::getOption('map_width', '');
	}

	/**
	 * Gets whether auto-fit is enabled, which makes the map automatically
	 * adjust its bounds to fit all visible markers.
	 *
	 * @return bool  True if auto-fit is enabled.
	 */
	public static function getAutoFit(): bool {
		return (bool) self::getOption('enable_auto_fit', true);
	}

	/**
	 * Gets whether marker clustering is enabled for the map.
	 *
	 * @return bool  True if marker clustering is enabled.
	 */
	public static function getClustering(): bool {
		return (bool) self::getOption('enable_clustering', true);
	}

	/**
	 * Gets the maximum zoom level at which marker clustering occurs.
	 *
	 * @return int  Max zoom level for clustering, clamped to 1–20.
	 */
	public static function getClusterMaxZoom(): int {
		return max(1, min(20, (int) self::getOption('cluster_max_zoom', 14)));
	}

	/**
	 * Gets the decimal precision used for stored coordinates.
	 *
	 * Six decimal places provides approximately 11 cm of precision.
	 *
	 * @return int Decimal places, clamped between 4 and 7.
	 */
	public static function getCoordinatePrecision(): int {
		return max(4, min(7, (int) self::getOption('coordinate_precision', 6)));
	}

	/**
	 * Gets the configured cache TTL for geocoding results.
	 *
	 * @return int  Cache TTL in seconds, clamped between 0 and YEAR_IN_SECONDS.
	 */
	public static function getGeocodeCacheTtl(): int {
		return max(0, min(YEAR_IN_SECONDS, (int) self::getOption('geocode_cache_ttl', WEEK_IN_SECONDS)));
	}

	/**
	 * Gets whether the zoom control is shown on the map.
	 *
	 * @return bool  True if the zoom control is shown.
	 */
	public static function getZoomControl(): bool {
		return (bool) self::getOption('enable_zoom_control', true);
	}

	/**
	 * Gets whether the fullscreen control is shown on the map.
	 *
	 * @return bool  True if the fullscreen control is shown.
	 */
	public static function getFullscreenControl(): bool {
		return (bool) self::getOption('enable_fullscreen_control', true);
	}

	/**
	 * Gets whether the Street View pegman control is shown on the map.
	 *
	 * @return bool  True if the Street View control is shown.
	 */
	public static function getStreetViewControl(): bool {
		return (bool) self::getOption('enable_streetview_control', true);
	}

	/**
	 * Gets whether the map type control (satellite/terrain toggle) is shown.
	 *
	 * @return bool  True if the map type control is shown.
	 */
	public static function getMapTypeControl(): bool {
		return (bool) self::getOption('enable_maptype_control', true);
	}

	/**
	 * Gets the map styles array, decoded from the stored JSON.
	 *
	 * Filtered through the `locfinder/pro/map_styles` filter.
	 *
	 * @return array<int,array<string,mixed>>  Styles array supplied by the Pro add-on, empty if Pro is not active.
	 */
	public static function getMapStyles(): array {
		$styles = apply_filters('locfinder/pro/map_styles', []);
		return is_array($styles) ? $styles : [];
	}

	/**
	 * Gets whether debug mode is enabled.
	 *
	 * When true, the plugin outputs diagnostic information to the browser console.
	 * Only passed to the frontend for users with manage_options capability.
	 *
	 * @return bool  True if debug logging is enabled.
	 */
	public static function getDebugMode(): bool {
		return (bool) self::getOption('enable_debug_mode', false);
	}

	/**
	 * Gets the message shown when no search results are found.
	 *
	 * @return string  Message string, falls back to 'No locations found.' if not configured.
	 */
	public static function getNoResultsMessage(): string {
		$value = (string) self::getOption('no_results_message', '');
		return $value !== '' ? $value : __('No locations found.', 'locfinder');
	}

	/**
	 * Gets the focus outline style applied to interactive elements.
	 *
	 * @return string  'browser' or 'high'.
	 */
	public static function getFocusStyle(): string {
		$value = (string) self::getOption('focus_style', 'browser');
		return in_array($value, ['browser', 'high'], true) ? $value : 'browser';
	}

	/**
	 * Gets the ARIA live-region politeness level.
	 *
	 * Used during server-side markup rendering rather than runtime map configuration.
	 *
	 * @return string 'polite', 'assertive', or 'off'.
	 */
	public static function getAriaLiveMode(): string {
		$value = (string) self::getOption('aria_live_mode', 'polite');
		return in_array($value, ['polite', 'assertive', 'off'], true) ? $value : 'polite';
	}

	/**
	 * Gets whether the results count is announced to screen readers after a search.
	 *
	 * @return bool  True if the results count is announced.
	 */
	public static function getAnnounceCount(): bool {
		return (bool) self::getOption('announce_count', true);
	}

	/**
	 * Gets whether high-contrast pin styles are used.
	 *
	 * @return bool  True if high-contrast pin styles are used.
	 */
	public static function getHighContrastMode(): bool {
		return (bool) self::getOption('high_contrast_mode', false);
	}

	/**
	 * Gets whether marker drop animation and other motion is reduced.
	 *
	 * @return bool  True if motion is reduced.
	 */
	public static function getReducedMotion(): bool {
		return (bool) self::getOption('reduced_motion', false);
	}

	/**
	 * Gets whether the map is deferred until the user clicks a button to load it.
	 *
	 * @return bool  True if the map is deferred until clicked.
	 */
	public static function getLoadMapOnClick(): bool {
		return (bool) self::getOption('load_map_on_click', false);
	}

	/**
	 * Gets the button label shown when the map is deferred via getLoadMapOnClick().
	 *
	 * @return string  Button label, falls back to 'Load Map' if not configured.
	 */
	public static function getLoadMapButtonLabel(): string {
		$value = (string) self::getOption('load_map_button_label', '');
		return $value !== '' ? $value : __('Load Map', 'locfinder');
	}

	/**
	 * Gets the global frontend configuration for Locfinder instances.
	 *
	 * Values are exposed through window.locfinderConfig and can be overridden
	 * per instance by shortcode or block configuration.
	 *
	 * Settings without a frontend requirement are intentionally omitted.
	 *
	 * @return array<string,mixed> Frontend configuration values.
	 */
	public static function getMapConfig(): array {
		return [

			// General.
			'googleMapsApiKey' => self::getApiKey(),
			'mapId'            => self::getMapId(),
			'distanceUnit'     => self::getDistanceUnit(),
			'defaultLat'       => self::getDefaultLat(),
			'defaultLng'       => self::getDefaultLng(),
			'defaultZoom'      => self::getDefaultZoom(),

			// Geocoding.
			'language' => 'en',
			'region'   => 'US',

			// Map Display.
			'pinColor'          => self::getPinColor(),
			'pinIconUrl'        => self::getPinIconUrl(),
			'termPinStyles'     => self::getTermPinStyles(self::getTaxonomyFilter()),
			'mapHeight'         => self::getMapHeight(),
			'minMapHeight'      => self::getMapMinHeight(),
			'mapWidth'          => self::getMapWidth(),
			'autoFit'           => self::getAutoFit(),
			'clustering'        => self::getClustering(),
			'clusterMaxZoom'    => self::getClusterMaxZoom(),
			'zoomControl'       => self::getZoomControl(),
			'fullscreenControl' => self::getFullscreenControl(),
			'streetViewControl' => self::getStreetViewControl(),
			'mapTypeControl'    => self::getMapTypeControl(),
			'mapStyles'         => self::getMapStyles(),

			// Results.
			'resultsLayout'      => self::getResultsLayout(),
			'resultsPosition'    => self::getResultsPosition(),
			'resultLinkTarget'   => self::getResultLinkTarget(),
			'resultItemTemplate' => self::getResultItemTemplate(),
			'noResultsMessage'   => self::getNoResultsMessage(),
			'icons'              => [
				'address' => Helper::getIconSvg('address'),
				'phone'   => Helper::getIconSvg('phone'),
				'email'   => Helper::getIconSvg('email'),
				'website' => Helper::getIconSvg('website'),
			],

			// Accessibility.
			'focusStyle'         => self::getFocusStyle(),
			'announceCount'      => self::getAnnounceCount(),
			'highContrastMode'   => self::getHighContrastMode(),
			'reducedMotion'      => self::getReducedMotion(),
			'loadMapOnClick'     => self::getLoadMapOnClick(),
			'loadMapButtonLabel' => self::getLoadMapButtonLabel(),

			// Fixed and computed values, not owned by any settings page.
			'addressWarningText' => __('Please select an address from the dropdown', 'locfinder'),
			/* translators: %d: number of results. Used for both the singular (%d = 1) and plural (%d = 0 or 2+) count — the number itself distinguishes the two, so the surrounding text is identical. */
			'resultsCountSingular' => __('Results returned (%d)', 'locfinder'),
			/* translators: %d: number of results. Used for both the singular (%d = 1) and plural (%d = 0 or 2+) count — the number itself distinguishes the two, so the surrounding text is identical. */
			'resultsCountPlural'  => __('Results returned (%d)', 'locfinder'),
			'paginationPrevLabel' => __('Prev', 'locfinder'),
			'paginationNextLabel' => __('Next', 'locfinder'),
			'showOnMapLabel'      => __('Show on map', 'locfinder'),
			/* translators: %s: location title */
			'showOnMapAriaLabel'            => __('Show %s on map', 'locfinder'),
			'singleMapNotConfiguredMessage' => __('Map is not configured.', 'locfinder'),
			'singleMapNoCoordsMessage'      => __('No coordinates are set for this location.', 'locfinder'),
			'singleMapLoadErrorMessage'     => __('The map could not be loaded.', 'locfinder'),
			'viewDetailsLabel'              => __('View Details', 'locfinder'),
			'visitWebsiteLabel'             => __('Visit Website', 'locfinder'),
			'sendEmailLabel'                => __('Send Email', 'locfinder'),
			'loadingDetailsMessage'         => __('Loading…', 'locfinder'),
			'ajaxUrl'                       => admin_url('admin-ajax.php'),
		];
	}
}
