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
			'locfinder_directory_section',
			__('Directory', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'item_label',
			__('Your directory lists', 'locfinder'),
			[$this, 'renderItemLabel'],
			$this->getSlug(),
			'locfinder_directory_section',
			['label_for' => 'item_label']
		);

		add_settings_section(
			'locfinder_branding_section',
			__('Branding', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_locations_page_section',
			/* translators: %s: plural name chosen in settings, e.g. "Locations" or "Programs" */
			sprintf(__('%s Page', 'locfinder'), Options::getItemLabels()['plural']),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_performance_section',
			__('Performance', 'locfinder'),
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
			'locfinder_branding_section'
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
			__('Page', 'locfinder'),
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
	 * Renders the field for what the directory lists.
	 *
	 * Presets are grouped in the dropdown. Choosing "Other" reveals singular
	 * and plural fields through CSS, so no script is needed.
	 *
	 * @return void
	 */
	public function renderItemLabel(): void {
		$name     = $this->getOptionName();
		$current  = Options::getItemLabelKey();
		$presets  = Options::getItemLabelPresets();
		$singular = (string) $this->getPageOption('item_label_singular', '');
		$plural   = (string) $this->getPageOption('item_label_plural', '');
		$maxLen   = (string) Options::ITEM_LABEL_MAX_LENGTH;
		?>
		<div class="locfinder-item-label">
			<select id="item_label" name="<?php echo esc_attr($name . '[item_label]'); ?>" aria-describedby="item_label_help">
				<?php foreach (Options::getItemLabelPresetGroups() as $group) : ?>
					<optgroup label="<?php echo esc_attr($group['label']); ?>">
						<?php foreach ($group['keys'] as $key) : ?>
							<?php if (isset($presets[$key])) : ?>
								<option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>><?php echo esc_html($presets[$key]['plural']); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
				<option value="<?php echo esc_attr(Options::ITEM_LABEL_CUSTOM); ?>" <?php selected($current, Options::ITEM_LABEL_CUSTOM); ?>><?php esc_html_e('Other', 'locfinder'); ?></option>
			</select>

			<div class="locfinder-item-label__custom">
				<p class="locfinder-item-label__field">
					<label for="item_label_singular"><?php esc_html_e('Singular', 'locfinder'); ?></label>
					<input id="item_label_singular" class="regular-text" type="text" name="<?php echo esc_attr($name . '[item_label_singular]'); ?>" value="<?php echo esc_attr($singular); ?>" maxlength="<?php echo esc_attr($maxLen); ?>" placeholder="<?php /* translators: Example singular name for a custom directory. */ esc_attr_e('Food Bank', 'locfinder'); ?>">
				</p>
				<p class="locfinder-item-label__field">
					<label for="item_label_plural"><?php esc_html_e('Plural', 'locfinder'); ?></label>
					<input id="item_label_plural" class="regular-text" type="text" name="<?php echo esc_attr($name . '[item_label_plural]'); ?>" value="<?php echo esc_attr($plural); ?>" maxlength="<?php echo esc_attr($maxLen); ?>" placeholder="<?php /* translators: Example plural name for a custom directory. */ esc_attr_e('Food Banks', 'locfinder'); ?>">
				</p>
			</div>

			<p id="item_label_help" class="description"><?php esc_html_e('Used in the admin menu, buttons, and messages, for example "Add Program" and "No programs found." Web addresses stay the same.', 'locfinder'); ?></p>
		</div>
		<?php
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
	 * Renders the Google Maps API key field, a link to create a key, and the
	 * Google Cloud APIs the key must have enabled, each linked to its page in
	 * the Google Cloud console.
	 *
	 * @return void
	 */
	public function renderGoogleMapsApiKey(): void {
		$val  = $this->getPageOption('google_maps_api_key', '');
		$name = $this->getOptionName() . '[google_maps_api_key]';

		// Google product names aren't translated; keys are Google Cloud service IDs.
		$apis = [
			'maps-backend.googleapis.com'      => 'Maps JavaScript API',
			'places.googleapis.com'            => 'Places API (New)',
			'geocoding-backend.googleapis.com' => 'Geocoding API',
		];

		$items = '';

		foreach ($apis as $service => $label) {
			$items .= sprintf(
				'<li class="locfinder-api-requirements__item"><a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a></li>',
				esc_url('https://console.cloud.google.com/apis/library/' . $service),
				esc_html($label),
				esc_html__('(opens in a new tab)', 'locfinder')
			);
		}

		$getKey = sprintf(
			/* translators: 1: opening <a> tag, 2: screen reader text "(opens in a new tab)", 3: closing </a> tag */
			__('%1$sGet an API key%2$s%3$s in Google Cloud, then paste it above.', 'locfinder'),
			'<a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank" rel="noopener">',
			'<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'locfinder') . '</span>',
			'</a>'
		);

		printf(
			'<input id="google_maps_api_key" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" aria-describedby="google_maps_api_key_description google_maps_api_key_help" />
			<p id="google_maps_api_key_description" class="description">%4$s</p>
			<div id="google_maps_api_key_help" class="locfinder-api-requirements">
				<p class="locfinder-api-requirements__intro">%5$s</p>
				<ul class="locfinder-api-requirements__list">%6$s</ul>
				<p class="locfinder-api-requirements__note"><strong>%7$s</strong> %8$s</p>
			</div>',
			esc_attr($name),
			esc_attr($val),
			esc_attr__('Enter your Google Maps API key', 'locfinder'),
			wp_kses($getKey, [
				'a'    => ['href' => true, 'target' => true, 'rel' => true],
				'span' => ['class' => true],
			]),
			esc_html__('Enable these APIs for your key:', 'locfinder'),
			wp_kses($items, [
				'li'   => ['class' => true],
				'a'    => ['href' => true, 'target' => true, 'rel' => true],
				'span' => ['class' => true],
			]),
			esc_html__('Using an older key?', 'locfinder'),
			esc_html__('Places API (New) is recommended, but legacy keys still work.', 'locfinder')
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
			/* translators: 1: opening <a> tag, 2: screen reader text "(opens in a new tab)", 3: closing </a> tag */
			__('For the best map experience, %1$screate a free Map ID%2$s%3$s. Choose <strong>JavaScript</strong> as the platform and <strong>Raster</strong> as the map type, then paste the ID above.', 'locfinder'),
			'<a href="https://console.cloud.google.com/google/maps-apis/studio/maps" target="_blank" rel="noopener">',
			'<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'locfinder') . '</span>',
			'</a>'
		);

		printf(
			'<input id="google_maps_map_id" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" aria-describedby="google_maps_map_id_description" />
			<p id="google_maps_map_id_description" class="description">%4$s</p>',
			esc_attr($name),
			esc_attr($val),
			esc_attr__('Enter Map ID', 'locfinder'),
			wp_kses($description, [
				'a'      => ['href' => true, 'target' => true, 'rel' => true],
				'span'   => ['class' => true],
				'strong' => [],
			])
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
			esc_html__('Leave blank to center the map on the average position of your saved addresses.', 'locfinder')
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
			esc_html(sprintf(
				/* translators: %s: plural name chosen in settings, mid-sentence, e.g. "locations" or "programs" */
				__('A page listing your %s was created automatically when the plugin was activated. You can select a different page here.', 'locfinder'),
				Options::getItemLabels()['pluralLower']
			))
		);
	}

	/**
	 * Renders the enqueue on field.
	 *
	 * Controls whether the locator's assets load only where the locator is
	 * rendered or on every front-end page.
	 *
	 * @return void
	 */
	public function renderEnqueueOn(): void {
		$val  = $this->getPageOption('enqueue_on', 'shortcode_only');
		$name = $this->getOptionName() . '[enqueue_on]';

		printf(
			'<select id="enqueue_on" name="%1$s" aria-describedby="enqueue_on_help">
				<option value="shortcode_only" %2$s>%3$s</option>
				<option value="all" %4$s>%5$s</option>
			</select>
			<p id="enqueue_on_help" class="description">%6$s</p>',
			esc_attr($name),
			selected($val, 'shortcode_only', false),
			esc_html__('Only where the locator appears (recommended)', 'locfinder'),
			selected($val, 'all', false),
			esc_html__('Every page', 'locfinder'),
			esc_html__('Location Finder loads its files automatically wherever the shortcode or block is used, including templates, widgets, and page builders. Choose "Every page" only if the locator briefly appears unstyled while a page loads.', 'locfinder')
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

		$out['item_label'] = isset($options['item_label'])
			? Sanitizer::select(
				$options['item_label'],
				[...array_keys(Options::getItemLabelPresets()), Options::ITEM_LABEL_CUSTOM],
				Options::ITEM_LABEL_DEFAULT
			)
			: (string) ($existing['item_label'] ?? Options::ITEM_LABEL_DEFAULT);

		$out['item_label_singular'] = isset($options['item_label_singular'])
			? Sanitizer::text($options['item_label_singular'], Options::ITEM_LABEL_MAX_LENGTH)
			: (string) ($existing['item_label_singular'] ?? '');

		$out['item_label_plural'] = isset($options['item_label_plural'])
			? Sanitizer::text($options['item_label_plural'], Options::ITEM_LABEL_MAX_LENGTH)
			: (string) ($existing['item_label_plural'] ?? '');

		if (
			$out['item_label'] === Options::ITEM_LABEL_CUSTOM
			&& ($out['item_label_singular'] === '' || $out['item_label_plural'] === '')
		) {
			$out['item_label']          = (string) ($existing['item_label'] ?? Options::ITEM_LABEL_DEFAULT);
			$out['item_label_singular'] = (string) ($existing['item_label_singular'] ?? '');
			$out['item_label_plural']   = (string) ($existing['item_label_plural'] ?? '');

			add_settings_error(
				$this->getOptionName(),
				'locfinder_item_label_incomplete',
				__('To use your own name, enter both the singular and the plural. Your previous choice was kept.', 'locfinder')
			);
		}

		return $out;
	}
}
