<?php
/**
 * Defines the data & geocoding settings page.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Settings\Base;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings and fields for the data & geocoding settings page.
 */
class Data extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_data';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Data & Geocoding', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_geocoding_performance',
			__('Geocoding Performance', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'geocode_cache_ttl',
			__('Geocode Cache TTL (seconds)', 'locfinder'),
			[$this, 'renderGeocodeCacheTtl'],
			$this->getSlug(),
			'locfinder_geocoding_performance',
			['label_for' => 'geocode_cache_ttl']
		);

		add_settings_field(
			'coordinate_precision',
			__('Coordinate Precision', 'locfinder'),
			[$this, 'renderCoordPrecision'],
			$this->getSlug(),
			'locfinder_geocoding_performance',
			['label_for' => 'coordinate_precision']
		);
	}

	/**
	 * Renders the geocode cache TTL field.
	 *
	 * Controls how long geocoding results are cached in seconds.
	 * Minimum is 60 seconds, default is one week (604800 seconds).
	 *
	 * @return void
	 */
	public function renderGeocodeCacheTtl(): void {
		$val  = (int) $this->getPageOption('geocode_cache_ttl', WEEK_IN_SECONDS);
		$name = $this->getOptionName() . '[geocode_cache_ttl]';

		printf(
			'<input id="geocode_cache_ttl" type="number" min="60" step="60" name="%1$s" value="%2$s" />
			<p class="description">%3$s</p>',
			esc_attr($name),
			esc_attr($val),
			esc_html__('Controls how long geocoding results are cached. Minimum 60. Default is one week (604800).', 'locfinder')
		);
	}

	/**
	 * Renders the coordinate precision field.
	 *
	 * Controls how many decimal places are stored for latitude and
	 * longitude coordinates.
	 *
	 * @return void
	 */
	public function renderCoordPrecision(): void {
		$val  = (int) $this->getPageOption('coordinate_precision', 6);
		$name = $this->getOptionName() . '[coordinate_precision]';

		printf(
			'<input id="coordinate_precision" type="number" min="4" max="7" name="%1$s" value="%2$s" />
			<p class="description">%3$s</p>',
			esc_attr($name),
			esc_attr($val),
			esc_html__('Decimal places stored for lat/lng coordinates. 4–7. Default is 6 (~11cm accuracy).', 'locfinder')
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		$out['geocode_cache_ttl'] = isset($options['geocode_cache_ttl'])
			? max(60, min(YEAR_IN_SECONDS, (int) $options['geocode_cache_ttl']))
			: (int) ($existing['geocode_cache_ttl'] ?? WEEK_IN_SECONDS);

		$out['coordinate_precision'] = isset($options['coordinate_precision'])
			? max(4, min(7, (int) $options['coordinate_precision']))
			: (int) ($existing['coordinate_precision'] ?? 6);

		return $out;
	}
}
