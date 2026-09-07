<?php
/**
 * Defines the map display settings page.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Admin;
use Locfinder\Admin\FieldRenderer;
use Locfinder\Admin\Settings\Base;
use Locfinder\Utilities\Options;
use Locfinder\Utilities\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings and fields for the map display settings page.
 */
class MapDisplay extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_map_display';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Map Display', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_map_display',
			__('Map Height & Behavior', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_map_pins',
			__('Map Pins', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'map_height',
			__('Map Height', 'locfinder'),
			[$this, 'renderMapHeight'],
			$this->getSlug(),
			'locfinder_map_display',
			['label_for' => 'map_height']
		);

		add_settings_field(
			'map_min_height',
			__('Minimum Map Height', 'locfinder'),
			[$this, 'renderMapMinHeight'],
			$this->getSlug(),
			'locfinder_map_display',
			['label_for' => 'map_min_height']
		);

		add_settings_field(
			'map_width',
			__('Map Width', 'locfinder'),
			[$this, 'renderMapWidth'],
			$this->getSlug(),
			'locfinder_map_display',
			['label_for' => 'map_width']
		);

		add_settings_field(
			'enable_auto_fit',
			__('Auto-Fit Map to Markers', 'locfinder'),
			[$this, 'renderAutoFit'],
			$this->getSlug(),
			'locfinder_map_display'
		);

		add_settings_field(
			'enable_controls',
			__('Map Controls', 'locfinder'),
			[$this, 'renderControls'],
			$this->getSlug(),
			'locfinder_map_display'
		);

		add_settings_field(
			'enable_clustering',
			__('Marker Clustering', 'locfinder'),
			[$this, 'renderClustering'],
			$this->getSlug(),
			'locfinder_map_display'
		);

		add_settings_field(
			'cluster_max_zoom',
			__('Cluster Max Zoom', 'locfinder'),
			[$this, 'renderClusterMaxZoom'],
			$this->getSlug(),
			'locfinder_map_display',
			['label_for' => 'cluster_max_zoom']
		);

		add_settings_field(
			'locfinder_pro_map_style_fields',
			__('Map Style', 'locfinder'),
			[$this, 'renderProMapDisplayFields'],
			$this->getSlug(),
			'locfinder_map_display',
			['context' => 'map_style']
		);

		add_settings_field(
			'pin_color',
			__('Default Pin Color & Icon', 'locfinder'),
			[$this, 'renderPinColor'],
			$this->getSlug(),
			'locfinder_map_pins',
			['label_for' => 'pin_color']
		);

		add_settings_field(
			'term_pins',
			__('Category Pin Colors & Icons', 'locfinder'),
			[$this, 'renderPinsByTerm'],
			$this->getSlug(),
			'locfinder_map_pins'
		);
	}

	/**
	 * Renders the map height field.
	 *
	 * Controls the height of the map on the front-end, e.g. 500px or 60vh.
	 *
	 * @return void
	 */
	public function renderMapHeight(): void {
		$val  = $this->getPageOption('map_height', '60vh');
		$name = $this->getOptionName() . '[map_height]';

		printf(
			'<input id="map_height" type="text" class="regular-text" name="%s" value="%s" placeholder="500px or 60vh" />',
			esc_attr($name),
			esc_attr($val)
		);
	}

	/**
	 * Renders the minimum map height field.
	 *
	 * Controls the minimum height of the map on the front-end, e.g. 300px.
	 *
	 * @return void
	 */
	public function renderMapMinHeight(): void {
		$val  = $this->getPageOption('map_min_height', '300px');
		$name = $this->getOptionName() . '[map_min_height]';

		printf(
			'<input id="map_min_height" type="text" class="regular-text" name="%s" value="%s" placeholder="300px" />',
			esc_attr($name),
			esc_attr($val)
		);
	}

	/**
	 * Renders the map width field.
	 *
	 * Controls the width of the map on the front-end, e.g. 100vw or 100%.
	 *
	 * @return void
	 */
	public function renderMapWidth(): void {
		$val  = $this->getPageOption('map_width', '');
		$name = $this->getOptionName() . '[map_width]';

		printf(
			'<input id="map_width" type="text" class="regular-text" name="%s" value="%s" placeholder="100vw or 100%%" />',
			esc_attr($name),
			esc_attr($val)
		);
	}

	/**
	 * Renders any Map Display fields contributed by the Pro add-on.
	 *
	 * If the Pro add-on is not active, renders an upsell message instead.
	 *
	 * @param  array $args  Field args, expects $args['context'] of 'map_style' or 'pin_icon'.
	 * @return void
	 */
	public function renderProMapDisplayFields(array $args = []): void {
		$context = $args['context'] ?? '';

		if (has_action('locfinder/pro/render_map_display_fields')) {
			do_action('locfinder/pro/render_map_display_fields', $this, $context);
			return;
		}

		$messages = [
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'map_style' => __('Customize the map\'s visual style with the %1$sPro add-on%2$s.', 'locfinder'),
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'pin_icon' => __('Use a custom pin icon with the %1$sPro add-on%2$s.', 'locfinder'),
		];

		Admin::proUpsell([
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'message' => $messages[$context] ?? __('Unlock this option with the %1$sPro add-on%2$s.', 'locfinder'),
		]);
	}

	/**
	 * Renders the auto-fit checkbox.
	 *
	 * Controls whether the map automatically adjusts its bounds to include all visible markers.
	 *
	 * @return void
	 */
	public function renderAutoFit(): void {
		$on   = !empty($this->getPageOption('enable_auto_fit', 1));
		$id   = 'enable_auto_fit';
		$name = $this->getOptionName() . '[enable_auto_fit]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Automatically adjusts the map view to include all visible markers', 'locfinder')
		);
	}

	/**
	 * Renders the map controls checkboxes.
	 *
	 * Controls which map controls are displayed on the front-end map,
	 * e.g. zoom, fullscreen, street view, and map type.
	 *
	 * @return void
	 */
	public function renderControls(): void {
		$controls = [
			'enable_zoom_control'       => __('Zoom Control', 'locfinder'),
			'enable_fullscreen_control' => __('Fullscreen', 'locfinder'),
			'enable_streetview_control' => __('Street View', 'locfinder'),
			'enable_maptype_control'    => __('Map Type', 'locfinder'),
		];

		foreach ($controls as $key => $label) {
			$on   = !empty($this->getPageOption($key, 1));
			$name = $this->getOptionName() . '[' . $key . ']';

			printf(
				'<div class="locfinder-admin__control">
					<input type="hidden" name="%1$s" value="0">
					<label for="%2$s">
						<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
					</label>
				</div>',
				esc_attr($name),
				esc_attr($key),
				checked($on, true, false),
				esc_html($label)
			);
		}
	}

	/**
	 * Renders the enable clustering checkbox.
	 *
	 * Controls whether nearby markers are grouped into clusters to reduce map clutter.
	 *
	 * @return void
	 */
	public function renderClustering(): void {
		$on   = !empty($this->getPageOption('enable_clustering', 1));
		$id   = 'enable_clustering';
		$name = $this->getOptionName() . '[enable_clustering]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<div class="locfinder-admin__control">
				<label for="%2$s">
					<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
				</label>
			</div>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Groups nearby markers into clusters to reduce map clutter', 'locfinder')
		);
	}

	/**
	 * Renders the cluster max zoom field.
	 *
	 * Controls the maximum zoom level at which markers are clustered.
	 * At higher zoom levels, markers will be displayed individually.
	 *
	 * @return void
	 */
	public function renderClusterMaxZoom(): void {
		$val  = (int) $this->getPageOption('cluster_max_zoom', 14);
		$name = $this->getOptionName() . '[cluster_max_zoom]';

		printf(
			'<input id="cluster_max_zoom" type="number" min="1" max="20" name="%s" value="%s" />',
			esc_attr($name),
			esc_attr($val)
		);
	}

	/**
	 * Renders the pin color field.
	 *
	 * Controls the default color of map pins on the front-end map.
	 *
	 * @return void
	 */
	public function renderPinColor(): void {
		FieldRenderer::colorControl(
			'pin_color',
			__('Pin Color', 'locfinder'),
			Options::DEFAULT_COLOR,
			$this->getOptionName()
		);

		$this->renderProMapDisplayFields(['context' => 'pin_icon']);
	}

	/**
	 * Renders pins by term field.
	 *
	 * If the Pro add-on is active, this field allows users to color code pins
	 * or use custom icons for each location taxonomy term.
	 *
	 * @return void
	 */
	public function renderPinsByTerm(): void {
		if (has_action('locfinder/pro/render_term_pins_ui')) {
			echo '<p>' . esc_html__('Make your map more visual by color coding pins or using custom icons for each location taxonomy term.', 'locfinder') . '</p>';

			$taxonomy = (string) $this->getPageOption('term_pins_taxonomy', 'locfinder_category');

			if (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy('locfinder_location', $taxonomy)) {
				$taxonomy = 'locfinder_category';
			}

			do_action('locfinder/pro/render_term_pins_ui', [
				'taxonomy' => $taxonomy,
			]);
			return;
		}

		Admin::proUpsell([
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'message' => __('Make your map more visual with color-coded pins or use custom icons for each location taxonomy term with the %1$sPro add-on%2$s.', 'locfinder'),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		$out['map_height'] = isset($options['map_height'])
			? Sanitizer::height($options['map_height'])
			: (string) ($existing['map_height'] ?? '60vh');

		$out['map_min_height'] = isset($options['map_min_height'])
			? Sanitizer::minHeight($options['map_min_height'])
			: (string) ($existing['map_min_height'] ?? '300px');

		$out['map_width'] = isset($options['map_width'])
			? Sanitizer::width($options['map_width'])
			: (string) ($existing['map_width'] ?? '');

		foreach (['enable_auto_fit', 'enable_zoom_control', 'enable_fullscreen_control', 'enable_streetview_control', 'enable_maptype_control', 'enable_clustering'] as $key) {
			$out[$key] = Sanitizer::checkboxKey($options, $existing, $key);
		}

		$out['pin_color'] = isset($options['pin_color'])
			? (sanitize_hex_color($options['pin_color']) ?? (string) ($existing['pin_color'] ?? Options::DEFAULT_COLOR))
			: (string) ($existing['pin_color'] ?? Options::DEFAULT_COLOR);

		$clusterMaxZoom = isset($options['cluster_max_zoom'])
			? (int) $options['cluster_max_zoom']
			: (int) ($existing['cluster_max_zoom'] ?? 14);
		$out['cluster_max_zoom'] = max(1, min(20, $clusterMaxZoom));

		$out = apply_filters('locfinder/pro/sanitize_map_display_fields', $out, $options, $existing);

		return $out;
	}
}
