<?php
/**
 * Defines the accessibility & privacy settings page.
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
 * Settings and fields for the accessibility & privacy settings page.
 */
class Accessibility extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_a11y';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Accessibility & Privacy', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_keyboard',
			__('Keyboard & Focus', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_announcements',
			__('Screen Reader Announcements', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_visual_aids',
			__('Visual Aids', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_privacy',
			__('Map Loading & Privacy', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'focus_style',
			__('Focus Style', 'locfinder'),
			[$this, 'renderFocusStyle'],
			$this->getSlug(),
			'locfinder_keyboard',
			['label_for' => 'focus_style']
		);

		add_settings_field(
			'aria_live_mode',
			__('Results Announcement (ARIA live)', 'locfinder'),
			[$this, 'renderAriaLiveMode'],
			$this->getSlug(),
			'locfinder_announcements',
			['label_for' => 'aria_live_mode']
		);

		add_settings_field(
			'announce_count',
			__('Announce Results Count', 'locfinder'),
			[$this, 'renderAnnounceCount'],
			$this->getSlug(),
			'locfinder_announcements'
		);

		add_settings_field(
			'high_contrast_mode',
			__('High-Contrast Pins', 'locfinder'),
			[$this, 'renderHighContrastMode'],
			$this->getSlug(),
			'locfinder_visual_aids',
			['label_for' => 'high_contrast_mode']
		);

		add_settings_field(
			'reduced_motion',
			__('Reduce Motion (no marker drop)', 'locfinder'),
			[$this, 'renderReducedMotion'],
			$this->getSlug(),
			'locfinder_visual_aids',
			['label_for' => 'reduced_motion']
		);

		add_settings_field(
			'load_map_on_click',
			__('Load Map on Click (Privacy)', 'locfinder'),
			[$this, 'renderLoadMapOnClick'],
			$this->getSlug(),
			'locfinder_privacy',
			['label_for' => 'load_map_on_click']
		);

		add_settings_field(
			'load_map_button_label',
			__('Load Map Button Label', 'locfinder'),
			[$this, 'renderLoadMapButtonLabel'],
			$this->getSlug(),
			'locfinder_privacy',
			['label_for' => 'load_map_button_label']
		);
	}

	/**
	 * Renders the focus style select field.
	 *
	 * Controls how the focus outline is displayed when navigating via keyboard.
	 *
	 * @return void
	 */
	public function renderFocusStyle(): void {
		$val  = $this->getPageOption('focus_style', 'browser');
		$name = $this->getOptionName() . '[focus_style]';

		printf(
			'<select id="focus_style" name="%1$s">
				<option value="browser" %2$s>%3$s</option>
				<option value="high" %4$s>%5$s</option>
			</select>',
			esc_attr($name),
			selected($val, 'browser', false),
			esc_html__('Browser default', 'locfinder'),
			selected($val, 'high', false),
			esc_html__('High-visibility outline', 'locfinder')
		);
	}

	/**
	 * Renders the ARIA live mode select field.
	 *
	 * Controls how screen readers are notified when results update.
	 * Polite waits for the user to finish their current task before
	 * announcing. Assertive interrupts immediately.
	 *
	 * @return void
	 */
	public function renderAriaLiveMode(): void {
		$val  = $this->getPageOption('aria_live_mode', 'polite');
		$name = $this->getOptionName() . '[aria_live_mode]';

		printf(
			'<select id="aria_live_mode" name="%1$s">
				<option value="polite" %2$s>%3$s</option>
				<option value="assertive" %4$s>%5$s</option>
				<option value="off" %6$s>%7$s</option>
			</select>
			<p class="description">%8$s</p>',
			esc_attr($name),
			selected($val, 'polite', false),
			esc_html__('Polite (recommended)', 'locfinder'),
			selected($val, 'assertive', false),
			esc_html__('Assertive', 'locfinder'),
			selected($val, 'off', false),
			esc_html__('Off', 'locfinder'),
			esc_html__('How screen readers are notified when results update.', 'locfinder')
		);
	}

	/**
	 * Renders the announce results count checkbox.
	 *
	 * When enabled, screen readers will announce the number of results found.
	 *
	 * @return void
	 */
	public function renderAnnounceCount(): void {
		$on   = !empty($this->getPageOption('announce_count', 1));
		$id   = 'announce_count';
		$name = $this->getOptionName() . '[announce_count]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Announce "N results found"', 'locfinder')
		);
	}

	/**
	 * Renders the high contrast mode checkbox.
	 *
	 * When enabled, the map pins and focus styles will use a high-contrast
	 * color scheme for better visibility.
	 *
	 * @return void
	 */
	public function renderHighContrastMode(): void {
		$on   = !empty($this->getPageOption('high_contrast_mode', 0));
		$id   = 'high_contrast_mode';
		$name = $this->getOptionName() . '[high_contrast_mode]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Use high-contrast pin/focus styles', 'locfinder')
		);
	}

	/**
	 * Renders the reduced motion checkbox.
	 *
	 * When enabled, the map will not animate marker drops or other motion effects.
	 *
	 * @return void
	 */
	public function renderReducedMotion(): void {
		$on   = !empty($this->getPageOption('reduced_motion', 0));
		$id   = 'reduced_motion';
		$name = $this->getOptionName() . '[reduced_motion]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Reduce motion (disable marker drop/animations)', 'locfinder')
		);
	}

	/**
	 * Renders the load map on click checkbox.
	 *
	 * When enabled, the Google Maps script is not loaded until the user
	 * explicitly clicks a button. Useful for GDPR compliance and page
	 * performance on map-heavy pages.
	 *
	 * @return void
	 */
	public function renderLoadMapOnClick(): void {
		$on   = !empty($this->getPageOption('load_map_on_click', 0));
		$id   = 'load_map_on_click';
		$name = $this->getOptionName() . '[load_map_on_click]';

		printf(
			'<input type="hidden" name="%1$s" value="0">
			<label for="%2$s">
				<input id="%2$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s
			</label>',
			esc_attr($name),
			esc_attr($id),
			checked($on, true, false),
			esc_html__('Delay Google Maps until user clicks button', 'locfinder')
		);
	}

	/**
	 * Renders the load map button label text field.
	 *
	 * When load map on click is enabled, this label is used for the button
	 * that loads the map.
	 *
	 * @return void
	 */
	public function renderLoadMapButtonLabel(): void {
		$default = __('Load Map', 'locfinder');
		$val     = $this->getPageOption('load_map_button_label', $default);
		$name    = $this->getOptionName() . '[load_map_button_label]';

		printf(
			'<input id="load_map_button_label" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
			esc_attr($name),
			esc_attr($val),
			esc_attr($default)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		foreach (['announce_count', 'high_contrast_mode', 'reduced_motion', 'load_map_on_click'] as $key) {
			$out[$key] = Sanitizer::checkboxKey($options, $existing, $key);
		}

		$out['focus_style'] = isset($options['focus_style'])
			? Sanitizer::select($options['focus_style'], ['browser', 'high'], 'browser')
			: (string) ($existing['focus_style'] ?? 'browser');

		$out['aria_live_mode'] = isset($options['aria_live_mode'])
			? Sanitizer::select($options['aria_live_mode'], ['polite', 'assertive', 'off'], 'polite')
			: (string) ($existing['aria_live_mode'] ?? 'polite');

		$out['load_map_button_label'] = isset($options['load_map_button_label'])
			? Sanitizer::text($options['load_map_button_label'], 60)
			: (string) ($existing['load_map_button_label'] ?? '');

		return $out;
	}
}
