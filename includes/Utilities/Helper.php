<?php
/**
 * Utility helper methods for the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Utilities;

use DateTime;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Utility class providing various helper methods for the Locfinder plugin.
 */
class Helper {

	/**
	 * Minimum digit count for a valid E.164 international number.
	 *
	 * @var int
	 */
	private const MIN_INTL_DIGITS = 7;

	/**
	 * Maximum digit count allowed by the E.164 standard.
	 *
	 * @var int
	 */
	private const MAX_INTL_DIGITS = 15;

	/**
	 * Trims input and strips all non-digit characters.
	 *
	 * Shared by normalizePhoneNumber() and formatPhoneForTel() so both
	 * methods extract digits the same way from a single source of truth.
	 *
	 * @param  string $input  Raw phone input.
	 * @return string         Digits only, or '' if no digits remain.
	 */
	private static function extractDigits(string $input): string {
		$input = trim($input);

		if ($input === '') {
			return '';
		}

		return preg_replace('/\D+/', '', $input) ?? '';
	}

	/**
	 * Normalizes and validates a phone number for storage.
	 *
	 * Numbers beginning with '+' are treated as international and stored in
	 * E.164-style format. Other numbers are treated as US numbers and normalized
	 * to a short code or 10-digit formatted number.
	 *
	 * @param  string $rawPhone  Raw phone number.
	 * @return string            Normalized phone number, or an empty string if invalid.
	 */
	public static function normalizePhoneNumber(string $rawPhone): string {
		$trimmed = trim($rawPhone);

		if ($trimmed === '') {
			return '';
		}

		$isInternational = str_starts_with($trimmed, '+');
		$digits          = self::extractDigits($trimmed);

		if ($digits === '') {
			return '';
		}

		if ($isInternational) {
			$len = strlen($digits);
			return ($len >= self::MIN_INTL_DIGITS && $len <= self::MAX_INTL_DIGITS)
				? '+' . $digits
				: '';
		}

		$len = strlen($digits);

		if ($len >= 3 && $len <= 5) {
			return $digits;
		}

		if ($len === 11 && $digits[0] === '1') {
			$digits = substr($digits, 1);
			$len    = strlen($digits);
		}

		if ($len !== 10) {
			return '';
		}

		return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
	}

	/**
	 * Formats a stored phone number for use in a tel: link.
	 *
	 * International numbers retain their leading '+'. US numbers are converted
	 * to +1 format, while short codes are returned unchanged.
	 *
	 * @param  string $phone  Stored phone number.
	 * @return string         Tel-ready phone number, or an empty string if invalid.
	 */
	public static function formatPhoneForTel(string $phone): string {
		$trimmed = trim($phone);

		if ($trimmed === '') {
			return '';
		}

		if (str_starts_with($trimmed, '+')) {
			$digits = self::extractDigits($trimmed);
			$len    = strlen($digits);

			return ($digits !== '' && $len >= self::MIN_INTL_DIGITS && $len <= self::MAX_INTL_DIGITS)
				? '+' . $digits
				: '';
		}

		$digits = self::extractDigits($trimmed);

		if ($digits === '') {
			return '';
		}

		$len = strlen($digits);

		if ($len >= 3 && $len <= 5) {
			return $digits;
		}

		if ($len === 10) {
			return '+1' . $digits;
		}

		if ($len === 11 && $digits[0] === '1') {
			return '+1' . substr($digits, 1);
		}

		return '';
	}

	/**
	 * Builds the display address for a location.
	 *
	 * Google Places may return a full address while city, state, and postal code
	 * are also stored separately. Duplicate city and state values are not appended.
	 *
	 * @param  array $row  Location row containing address components.
	 * @return string      Formatted display address.
	 */
	public static function formatAddress(array $row): string {
		$address  = sanitize_text_field((string) ($row['address'] ?? ''));
		$address2 = sanitize_text_field((string) ($row['address_2'] ?? ''));
		$city     = sanitize_text_field((string) ($row['city'] ?? ''));
		$state    = sanitize_text_field((string) ($row['state'] ?? ''));
		$zip      = sanitize_text_field((string) ($row['postal_code'] ?? ''));

		$addressLine = $address2 !== '' ? trim($address . ', ' . $address2, ', ') : $address;

		if ($address !== '' && self::containsWholeWord($address, $city) && self::containsWholeWord($address, $state)) {
			return $addressLine;
		}

		$stateZip = trim($state . ' ' . $zip);
		$cityLine = ($city !== '' && $stateZip !== '')
			? $city . ', ' . $stateZip
			: ($city !== '' ? $city : $stateZip);

		return implode(', ', array_filter([$addressLine, $cityLine], static fn ($part) => $part !== ''));
	}

	/**
	 * Checks whether a value appears in text as a whole word.
	 *
	 * Used by formatAddress() to avoid partial matches, such as matching a state
	 * code within an unrelated word. An empty needle always returns false.
	 *
	 * @param  string $haystack  Text to search.
	 * @param  string $needle    Value to find.
	 * @return bool              True if a whole-word match is found.
	 */
	private static function containsWholeWord(string $haystack, string $needle): bool {
		if ($needle === '') {
			return false;
		}

		return preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $haystack) === 1;
	}

	/**
	 * Formats a MySQL TIME value as a 12-hour display string.
	 *
	 * @param  string|null $time  MySQL TIME value ("09:00:00" or "09:00"), or null.
	 * @return string             Formatted time ("9:00 AM"), or '' if invalid.
	 */
	public static function formatTime12Hour(?string $time): string {
		if (empty($time)) {
			return '';
		}

		$parsed = DateTime::createFromFormat('H:i:s', $time);

		if (!$parsed) {
			$parsed = DateTime::createFromFormat('H:i', $time);
		}

		if (!$parsed) {
			return '';
		}

		return $parsed->format('g:i A');
	}

	/**
	 * Gets translated weekday labels keyed by ISO weekday number, Monday first.
	 *
	 * Provides the shared day order and labels used throughout the plugin.
	 *
	 * @return array<int,string> Day number (1-7) mapped to translated day name.
	 */
	public static function getWeekdayLabels(): array {
		return [
			1 => __('Monday', 'locfinder'),
			2 => __('Tuesday', 'locfinder'),
			3 => __('Wednesday', 'locfinder'),
			4 => __('Thursday', 'locfinder'),
			5 => __('Friday', 'locfinder'),
			6 => __('Saturday', 'locfinder'),
			7 => __('Sunday', 'locfinder'),
		];
	}

	/**
	 * Converts each character to an HTML numeric character entity.
	 *
	 * Used to obfuscate email addresses in rendered HTML while preserving normal
	 * display and mailto: behavior without requiring JavaScript.
	 *
	 * @param  string $value  Plain text to obfuscate.
	 * @return string         HTML entity-encoded string.
	 */
	public static function obfuscateForHtml(string $value): string {
		$hasMbstring = function_exists('mb_str_split') && function_exists('mb_ord');
		$chars       = $hasMbstring ? mb_str_split($value, 1, 'UTF-8') : str_split($value);

		$encoded = '';

		foreach ($chars as $char) {
			$encoded .= '&#' . ($hasMbstring ? mb_ord($char, 'UTF-8') : ord($char)) . ';';
		}

		return $encoded;
	}

	/**
	 * Gets an inline SVG icon for a location info field.
	 *
	 * Icons are hardcoded, trusted markup and do not require escaping here.
	 *
	 * @param  string $name  Icon name.
	 * @return string        Inline SVG markup, or an empty string if unknown.
	 */
	public static function getIconSvg(string $name): string {
		$icons = [
			'address'    => '<svg class="locfinder-location__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
			'phone'      => '<svg class="locfinder-location__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
			'email'      => '<svg class="locfinder-location__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
			'website'    => '<svg class="locfinder-location__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
			'hours'      => '<svg class="locfinder-location__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
			'directions' => '<svg class="locfinder-location__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>',
		];

		return $icons[$name] ?? '';
	}
}
