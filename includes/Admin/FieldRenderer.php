<?php
/**
 * UI renderers for settings fields.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Renders various types of settings fields for the admin UI.
 */
class FieldRenderer {

	/**
	 * Renders a color swatch input paired with a hex text input.
	 *
	 * The hex text input carries the real name= attribute submitted with
	 * the form. The swatch and hex field are kept in sync by field-controls.js.
	 *
	 * @param  string $optionKey   The option key.
	 * @param  string $label       Label for screen readers.
	 * @param  string $default     Default hex color.
	 * @param  string $optionName  The calling page's option name from $this->getOptionName().
	 * @return void
	 */
	public static function colorControl(string $optionKey, string $label, string $default, string $optionName): void {
		$options = get_option($optionName, []);
		$options = is_array($options) ? $options : [];
		$name    = $optionName . '[' . $optionKey . ']';
		$value   = (string) ($options[$optionKey] ?? $default);

		printf(
			'<div class="locfinder-color-field">
				<label class="screen-reader-text" for="%1$s_text">%2$s</label>
				<input id="%1$s_swatch" type="color" class="locfinder-color-swatch" value="%3$s" aria-hidden="true" tabindex="-1" />
				<input id="%1$s_text" type="text" class="regular-text locfinder-color-text" name="%4$s" value="%3$s" placeholder="%3$s" />
			</div>',
			esc_attr($optionKey),
			esc_html($label),
			esc_attr($value),
			esc_attr($name)
		);
	}

	/**
	 * Renders a media preview with Upload/Select and Clear buttons.
	 *
	 * Both the attachment ID and URL are stored in hidden inputs carrying
	 * the real name= attributes submitted with the form. The upload button
	 * and preview are wired to the WordPress media library by field-controls.js.
	 *
	 * @param  string $urlOptionKey  The option key for the media URL.
	 * @param  string $idOptionKey   The option key for the media attachment ID.
	 * @param  string $label         Label for screen readers.
	 * @param  string $optionName    The calling page's option name from $this->getOptionName().
	 * @return void
	 */
	public static function mediaControl(string $urlOptionKey, string $idOptionKey, string $label, string $optionName): void {
		$options  = get_option($optionName, []);
		$options  = is_array($options) ? $options : [];
		$savedUrl = isset($options[$urlOptionKey]) ? (string) $options[$urlOptionKey] : '';
		$savedId  = isset($options[$idOptionKey])  ? absint($options[$idOptionKey])   : 0;
		$nameUrl  = $optionName . '[' . $urlOptionKey . ']';
		$nameId   = $optionName . '[' . $idOptionKey  . ']';

		// Only seed the preview URL if we have a valid attachment ID and a valid URL.
		$displayUrl = ($savedId > 0 && preg_match('#^https?://#i', $savedUrl)) ? $savedUrl : '';

		$previewHtml = $displayUrl
			? '<img src="' . esc_url($displayUrl) . '" alt="" />'
			: '<span class="description">' . esc_html__('No icon set.', 'locfinder') . '</span>';

		printf(
			'<div class="locfinder-media-field">
				<label class="screen-reader-text">%1$s</label>
				<input type="hidden" class="locfinder-media-id" name="%2$s" value="%3$s">
				<input type="hidden" class="locfinder-media-url" name="%4$s" value="%5$s">
				<div class="locfinder-media-preview">%6$s</div>
				<button type="button" class="button locfinder-media-upload">%7$s</button>
				<button type="button" class="button locfinder-media-clear">%8$s</button>
			</div>',
			esc_html($label),
			esc_attr($nameId),
			esc_attr((string) $savedId),
			esc_attr($nameUrl),
			esc_attr($savedUrl),
			wp_kses_post($previewHtml),
			esc_html__('Upload / Select', 'locfinder'),
			esc_html__('Clear', 'locfinder')
		);
	}
}
