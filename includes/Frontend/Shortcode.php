<?php
/**
 * Registers shortcodes for the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the registration and rendering of the location finder shortcode.
 */
class Shortcode {

	/**
	 * Registers all plugin shortcodes with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode('locfinder', [$this, 'render']);
	}

	/**
	 * Renders the location finder shortcode.
	 *
	 * Buffers the map view template and returns it as a string so WordPress
	 * can insert it inline into the post content.
	 *
	 * @param  array|string $atts     Shortcode attributes or empty string when none provided.
	 * @param  string|null  $content  Inner content (unused).
	 * @return string                 Rendered HTML output.
	 */
	public function render(array|string $atts, ?string $content = null): string {
		ob_start();
		include LOCFINDER_DIR . 'includes/Frontend/views/map.php';
		return (string) ob_get_clean();
	}
}
