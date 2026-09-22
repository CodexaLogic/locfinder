<?php
/**
 * Defines the general settings page.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Settings\Base;
use Locfinder\Admin\FieldRenderer;
use Locfinder\Core\LocationsPage;
use Locfinder\Utilities\Sanitizer;
use Locfinder\Utilities\Options;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings and fields for the general settings page.
 */
class General extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_general';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('General Settings', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_branding_section',
			__('Branding', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_google_maps_section',
			__('Google Maps', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_map_defaults_section',
			__('Map Defaults', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_locations_page_section',
			__('Locations Page', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_performance_section',
			__('Performance', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'enable_map',
			__('Enable Google Maps', 'locfinder'),
			[$this, 'renderMap'],
			$this->getSlug(),
			'locfinder_google_maps_section',
			['label_for' => 'enable_map']
		);

		add_settings_field(
			'google_maps_api_key',
			__('Google Maps API Key', 'locfinder'),
			[$this, 'renderGoogleMapsApiKey'],
			$this->getSlug(),
			'locfinder_google_maps_section',
			['label_for' => 'google_maps_api_key']
		);

		add_settings_field(
			'google_maps_map_id',
			__('Map ID', 'locfinder'),
			[$this, 'renderGoogleMapsMapId'],
			$this->getSlug(),
			'locfinder_google_maps_section',
			['label_for' => 'google_maps_map_id']
		);

		add_settings_field(
			'primary_color',
			__('Primary Color', 'locfinder'),
			[$this, 'renderPrimaryColor'],
			$this->getSlug(),
			'locfinder_branding_section',
			['label_for' => 'primary_color']
		);

		add_settings_field(
			'distance_unit',
			__('Distance Unit', 'locfinder'),
			[$this, 'renderDistanceUnit'],
			$this->getSlug(),
			'locfinder_map_defaults_section',
			['label_for' => 'distance_unit']
		);

		add_settings_field(
			'default_center',
			__('Default Center (Lat, Lng)', 'locfinder'),
			[$this, 'renderDefaultCenter'],
			$this->getSlug(),
			'locfinder_map_defaults_section'
		);

		add_settings_field(
			'default_zoom',
			__('Default Zoom Level', 'locfinder'),
			[$this, 'renderDefaultZoom'],
			$this->getSlug(),
			'locfinder_map_defaults_section',
			['label_for' => 'default_zoom']
		);

		add_settings_field(
			'locations_page_id',
			__('Locations Page', 'locfinder'),
			[$this, 'renderLocationsPage'],
			$this->getSlug(),
			'locfinder_locations_page_section',
			['label_for' => 'locations_page_id']
		);

		add_settings_field(
			'enqueue_on',
			__('Load Assets', 'locfinder'),
			[$this, 'renderEnqueueOn'],
			$this->getSlug(),
			'locfinder_performance_section',
			['label_for' => 'enqueue_on']
		);
	}

	/**
	 * Renders the map enable/disable checkbox.
	 *
	 * @return void
	 */
	public function renderMap(): void {
		$on   = !empty($this->getPageOption('enable_map', 1));
		$id   = 'enable_map';
		$name = $this->getOptionName() . '[enable_map]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Uncheck if you\'re not using Google Maps. Location Finder still works as a searchable directory or resource library without it.', 'locfinder')
		);
	}

	/**
	 * Renders the Google Maps API key field and a link to retrieve one.
	 *
	 * @return void
	 */
	public function renderGoogleMapsApiKey(): void {
		$val  = $this->getPageOption('google_maps_api_key', '');
		$name = $this->getOptionName() . '[google_maps_api_key]';

		printf(
			'<input id="google_maps_api_key" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />
			&nbsp;&nbsp;<a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank" rel="noopener">%4$s</a>',
			esc_attr($name),
			esc_attr($val),
			esc_attr__('Enter Google Maps API key', 'locfinder'),
			esc_html__('Get a Google Maps API key', 'locfinder')
		);
	}

	/**
	 * Renders the Google Maps Map ID field and a link to create one.
	 *
	 * Required for AdvancedMarkerElement. A Map ID is created in the
	 * Google Cloud Console under Google Maps Platform > Map Management.
	 *
	 * @return void
	 */
	public function renderGoogleMapsMapId(): void {
		$val  = $this->getPageOption('google_maps_map_id', '');
		$name = $this->getOptionName() . '[google_maps_map_id]';

		$description = sprintf(
			/* translators: %1$s opening <a> tag, %2$s closing </a> tag */
			__('For the best map experience, %1$screate a free Map ID%2$s. Choose <strong>JavaScript</strong> as the platform and <strong>Raster</strong> as the map type, then paste the ID above.', 'locfinder'),
			'<a href="https://console.cloud.google.com/google/maps-apis/studio/maps" target="_blank" rel="noopener">',
			'</a>'
		);

		printf(
			'<input id="google_maps_map_id" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />
			<p class="description">%4$s</p>',
			esc_attr($name),
			esc_attr($val),
			esc_attr__('Enter Map ID', 'locfinder'),
			wp_kses($description, ['a' => ['href' => true, 'target' => true, 'rel' => true], 'strong' => []])
		);
	}

	/**
	 * Renders the primary color field.
	 *
	 * Overrides the --locfinder-primary CSS custom property site-wide for
	 * buttons, links, the current pagination page, and every other element
	 * styled off that token. Distinct from Map Display's pin_color, which
	 * only controls the map marker color.
	 *
	 * @return void
	 */
	public function renderPrimaryColor(): void {
		FieldRenderer::colorControl(
			'primary_color',
			__('Primary Color', 'locfinder'),
			Options::DEFAULT_COLOR,
			$this->getOptionName()
		);
	}

	/**
	 * Renders the distance unit field.
	 *
	 * Controls whether distances are displayed in miles or kilometers.
	 *
	 * @return void
	 */
	public function renderDistanceUnit(): void {
		$val  = $this->getPageOption('distance_unit', 'mi');
		$name = $this->getOptionName() . '[distance_unit]';

		printf(
			'<select id="distance_unit" name="%1$s">
				<option value="mi" %2$s>%3$s</option>
				<option value="km" %4$s>%5$s</option>
			</select>',
			esc_attr($name),
			selected($val, 'mi', false),
			esc_html__('Miles', 'locfinder'),
			selected($val, 'km', false),
			esc_html__('Kilometers', 'locfinder')
		);
	}

	/**
	 * Renders the default center latitude and longitude fields.
	 *
	 * Controls the default map center when no locations are found. If left
	 * blank, the average location of all saved locations is used, or the
	 * site's timezone if no locations exist yet, or a US-centered fallback
	 * if neither is available.
	 *
	 * @return void
	 */
	public function renderDefaultCenter(): void {
		$rawLat = $this->getPageOption('default_center_lat', '');
		$rawLng = $this->getPageOption('default_center_lng', '');

		$lat = is_numeric($rawLat) ? (string) $rawLat : '';
		$lng = is_numeric($rawLng) ? (string) $rawLng : '';

		$placeholderLat = number_format(Options::getDefaultLat(), 6, '.', '');
		$placeholderLng = number_format(Options::getDefaultLng(), 6, '.', '');

		$nameLat = $this->getOptionName() . '[default_center_lat]';
		$nameLng = $this->getOptionName() . '[default_center_lng]';

		printf(
			'<div class="locfinder-admin__grid--2col">
				<label for="default_center_lat">%1$s</label>
				<input id="default_center_lat" type="number" name="%2$s" value="%3$s" placeholder="%4$s" min="-90" max="90" step="0.000001" inputmode="decimal" aria-describedby="locfinder_center_help" />
				<label for="default_center_lng">%5$s</label>
				<input id="default_center_lng" type="number" name="%6$s" value="%7$s" placeholder="%8$s" min="-180" max="180" step="0.000001" inputmode="decimal" aria-describedby="locfinder_center_help" />
			</div>
			<p id="locfinder_center_help" class="description">%9$s</p>',
			esc_html__('Latitude', 'locfinder'),
			esc_attr($nameLat),
			esc_attr($lat),
			esc_attr($placeholderLat),
			esc_html__('Longitude', 'locfinder'),
			esc_attr($nameLng),
			esc_attr($lng),
			esc_attr($placeholderLng),
			esc_html__('Leave blank to center the map using the average of your saved locations.', 'locfinder')
		);
	}

	/**
	 * Renders the default zoom field.
	 *
	 * Controls the default map zoom level when no locations are found. If left
	 * blank, the default zoom is 11.
	 *
	 * @return void
	 */
	public function renderDefaultZoom(): void {
		$val  = (int) $this->getPageOption('default_zoom', 11);
		$name = $this->getOptionName() . '[default_zoom]';

		printf(
			'<input id="default_zoom" type="number" min="1" max="20" name="%s" value="%s" />',
			esc_attr($name),
			esc_attr($val)
		);
	}

	/**
	 * Renders the Locations page picker.
	 *
	 * Uses wp_dropdown_pages() to allow any existing page to be selected instead
	 * of the page created automatically on activation.
	 *
	 * @return void
	 */
	public function renderLocationsPage(): void {
		$val  = (int) $this->getPageOption(LocationsPage::OPTION_KEY, 0);
		$name = $this->getOptionName() . '[' . LocationsPage::OPTION_KEY . ']';

		wp_dropdown_pages([
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes 'name' internally via esc_attr() when building the <select> tag; escaping it again here would double-escape.
			'name'              => $name,
			'id'                => 'locations_page_id',
			'selected'          => absint($val),
			'show_option_none'  => esc_html__('— Select a page —', 'locfinder'),
			'option_none_value' => '0',
		]);

		if ($val > 0 && ($url = get_permalink($val))) {
			printf(
				'<p class="description"><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
				esc_url($url),
				esc_html__('View page', 'locfinder')
			);
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__('A "Locations" page was created automatically when the plugin is activated, but you can select a different page here.', 'locfinder')
		);
	}

	/**
	 * Renders the enqueue on field.
	 *
	 * Controls whether the plugin's assets are loaded only on pages
	 * with the shortcode or site-wide.
	 *
	 * @return void
	 */
	public function renderEnqueueOn(): void {
		$val  = $this->getPageOption('enqueue_on', 'shortcode_only');
		$name = $this->getOptionName() . '[enqueue_on]';

		printf(
			'<select id="enqueue_on" name="%1$s">
				<option value="shortcode_only" %2$s>%3$s</option>
				<option value="all" %4$s>%5$s</option>
			</select>',
			esc_attr($name),
			selected($val, 'shortcode_only', false),
			esc_html__('Only on pages with the shortcode', 'locfinder'),
			selected($val, 'all', false),
			esc_html__('Site-wide (not recommended)', 'locfinder')
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		$out['primary_color'] = isset($options['primary_color'])
			? (sanitize_hex_color($options['primary_color']) ?? (string) ($existing['primary_color'] ?? Options::DEFAULT_COLOR))
			: (string) ($existing['primary_color'] ?? Options::DEFAULT_COLOR);

		$out['enable_map'] = Sanitizer::checkboxKey($options, $existing, 'enable_map');

		$out['google_maps_api_key'] = isset($options['google_maps_api_key'])
			? Sanitizer::text($options['google_maps_api_key'], 120)
			: (string) ($existing['google_maps_api_key'] ?? '');

		$out['google_maps_map_id'] = isset($options['google_maps_map_id'])
			? Sanitizer::text($options['google_maps_map_id'], 255)
			: (string) ($existing['google_maps_map_id'] ?? '');

		$out['distance_unit'] = isset($options['distance_unit'])
			? Sanitizer::select($options['distance_unit'], ['mi', 'km'], 'mi')
			: (string) ($existing['distance_unit'] ?? 'mi');

		$rawLat = $options['default_center_lat'] ?? $existing['default_center_lat'] ?? '';
		$rawLng = $options['default_center_lng'] ?? $existing['default_center_lng'] ?? '';

		$out['default_center_lat'] = is_numeric($rawLat)
			? number_format(max(-90.0, min(90.0, (float) $rawLat)), 6, '.', '')
			: '';

		$out['default_center_lng'] = is_numeric($rawLng)
			? number_format(max(-180.0, min(180.0, (float) $rawLng)), 6, '.', '')
			: '';

		$zoom                = isset($options['default_zoom']) ? (int) $options['default_zoom'] : (int) ($existing['default_zoom'] ?? 11);
		$out['default_zoom'] = max(1, min(20, $zoom));

		$out[LocationsPage::OPTION_KEY] = isset($options[LocationsPage::OPTION_KEY])
			? Sanitizer::dropdownPages($options[LocationsPage::OPTION_KEY])
			: (int) ($existing[LocationsPage::OPTION_KEY] ?? 0);

		$out['enqueue_on'] = isset($options['enqueue_on'])
			? Sanitizer::select($options['enqueue_on'], ['shortcode_only', 'all'], 'shortcode_only')
			: (string) ($existing['enqueue_on'] ?? 'shortcode_only');

		return $out;
	}
}
