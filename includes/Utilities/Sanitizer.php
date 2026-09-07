<?php
/**
 * Sanitization utilities for validating and cleaning plugin data.
 *
 * @package Locfinder
 */

namespace Locfinder\Utilities;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Utility class for validating and cleaning plugin data.
 */
class Sanitizer {

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Normalizes whitespace by converting line endings to \n, trimming,
	 * and collapsing internal runs of spaces and tabs to a single space.
	 *
	 * @param  string $text
	 * @return string  Whitespace-normalized string.
	 */
	protected static function normalizeWhitespace(string $text): string {
		$text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
		$text = trim($text);
		return preg_replace('/[ \t]+/', ' ', $text) ?? $text;
	}

	/**
	 * Limits text to a maximum character length.
	 *
	 * Uses multibyte-safe truncation when available.
	 *
	 * @param  string $text  Text to truncate.
	 * @param  int    $max   Maximum characters. 0 disables truncation.
	 * @return string        Truncated text.
	 */
	protected static function limit(string $text, int $max): string {
		if ($max <= 0) {
			return $text;
		}

		return function_exists('mb_substr')
			? mb_substr($text, 0, $max, 'UTF-8')
			: substr($text, 0, $max);
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Sanitizes plain text and optionally limits its length.
	 *
	 * @param  string $input   Raw text.
	 * @param  int    $maxLen  Maximum characters. 0 disables truncation.
	 * @return string          Sanitized text.
	 */
	public static function text(string $input, int $maxLen = 0): string {
		$value = sanitize_text_field($input);
		$value = self::normalizeWhitespace($value);
		return $maxLen ? self::limit($value, $maxLen) : $value;
	}

	/**
	 * Normalizes a checkbox value to 0 or 1.
	 *
	 * Accepts common truthy values including "1", "true", "on", and "yes".
	 *
	 * @param  mixed $input  Checkbox value.
	 * @return int           1 if truthy, 0 otherwise.
	 */
	public static function checkbox(mixed $input): int {
		return in_array(strtolower(trim((string) $input)), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
	}

	/**
	 * Sanitizes a checkbox within a submitted options array.
	 *
	 * Preserves the existing value when the key is not submitted.
	 *
	 * @param  array  $options   Submitted options.
	 * @param  array  $existing  Previously saved options.
	 * @param  string $key       Checkbox option key.
	 * @return int               1 if enabled, 0 otherwise.
	 */
	public static function checkboxKey(array $options, array $existing, string $key): int {
		return array_key_exists($key, $options)
			? self::checkbox($options[$key])
			: self::checkbox($existing[$key] ?? 0);
	}

	/**
	 * Validates a value against an allowed list.
	 *
	 * Supports indexed arrays of values and associative value-to-label maps.
	 *
	 * @param  string $input    Submitted value.
	 * @param  array  $choices  Allowed values or value-to-label map.
	 * @param  string $default  Fallback when the value is not allowed.
	 * @return string           Validated value or fallback.
	 */
	public static function select(string $input, array $choices, string $default = ''): string {
		$in = sanitize_key($input);

		$allowed = wp_is_numeric_array($choices)
			? array_map('sanitize_key', array_map('strval', $choices))
			: array_map('sanitize_key', array_keys($choices));

		return in_array($in, $allowed, true) ? $in : $default;
	}

	/**
	 * Validates a published page ID.
	 *
	 * @param  mixed $pageId  Page ID to validate.
	 * @return int            Valid page ID, or 0 if invalid.
	 */
	public static function dropdownPages(mixed $pageId): int {
		$pageId = absint($pageId);
		$post   = get_post($pageId);
		return ($post && $post->post_type === 'page' && $post->post_status === 'publish') ? $pageId : 0;
	}

	/**
	 * Sanitizes a CSS height value with a unit (px, em, rem, vh, vw, %).
	 *
	 * Clamps viewport and percentage units to 1-100, pixel-based units to 100-2000.
	 * Defaults to '60vh' for invalid input.
	 *
	 * @param  string $input
	 * @return string  CSS height value, falls back to '60vh' if invalid.
	 */
	public static function height(string $input): string {
		$input = trim($input);

		if (preg_match('/^\s*(\d+)\s*(px|em|rem|vh|vw|%)?\s*$/i', $input, $matches)) {
			$unit  = (($matches[2] ?? '') !== '') ? strtolower($matches[2]) : 'px';
			$value = match($unit) {
				'vh', 'vw', '%' => max(1, min(100, (int) $matches[1])),
				default         => max(100, min(2000, (int) $matches[1])),
			};
			return $value . $unit;
		}

		return '60vh';
	}

	/**
	 * Sanitizes a CSS height value.
	 *
	 * Supports px, em, rem, vh, vw, and %. Viewport and percentage values are
	 * clamped to 1-100; other supported units are clamped to 100-2000.
	 *
	 * @param  string $input  CSS height value.
	 * @return string         Sanitized height, or '60vh' if invalid.
	 */
	public static function minHeight(string $input): string {
		$input = trim($input);

		if (preg_match('/^\s*(\d+)\s*(px|rem)?\s*$/i', $input, $matches)) {
			$unit  = (($matches[2] ?? '') !== '') ? strtolower($matches[2]) : 'px';
			$value = max(100, min(1000, (int) $matches[1]));
			return $value . $unit;
		}

		return '300px';
	}

	/**
	 * Sanitizes a CSS width value.
	 *
	 * Accepts numeric values using px, em, rem, vh, vw, or %. Unitless values
	 * default to px. Viewport and percentage values are clamped to 1–100;
	 * all other supported units are clamped to 10–2000.
	 *
	 * Values are rounded to a maximum of two decimal places.
	 *
	 * @param string $input CSS width value.
	 * @return string Sanitized width value, or an empty string if invalid.
	 */
	public static function width(string $input): string {
		$input = trim($input);

		if ($input === '') {
			return '';
		}

		if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(px|em|rem|vh|vw|%)?\s*$/i', $input, $matches)) {
			$unit = (($matches[2] ?? '') !== '') ? strtolower($matches[2]) : 'px';

			$value = match ($unit) {
				'vh', 'vw', '%' => max(1, min(100, (float) $matches[1])),
				default         => max(10, min(2000, (float) $matches[1])),
			};

			// Round to two decimal places and remove insignificant trailing zeros.
			$formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

			return $formatted . $unit;
		}

		return '';
	}
}
