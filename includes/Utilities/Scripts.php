<?php
/**
 * Helpers for modifying script tags before they are printed to the page.
 *
 * @package Locfinder
 */

namespace Locfinder\Utilities;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Utility class for handling script tag modifications.
 */
class Scripts {
	/**
	 * Converts a script tag to an ES module script tag.
	 *
	 * Vite's build output uses import/export statements, which browsers
	 * only allow inside a <script type="module"> tag. Without this, the
	 * script fails to load with a SyntaxError rather than simply degrading.
	 *
	 * @param  string $tag  The original script tag HTML.
	 * @return string       The script tag with type="module" injected.
	 */
	public static function moduleScriptTag(string $tag): string {
		return preg_replace('/^<script /', '<script type="module" ', $tag, 1);
	}
}
