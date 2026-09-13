<?php
/**
 * Registers Gutenberg blocks for the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Blocks;

use Locfinder\Frontend\Frontend;
use Locfinder\Utilities\Options;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers and renders Gutenberg blocks for the Locfinder plugin.
 */
class BlockRegistrar {

	/**
	 * Registers all Gutenberg blocks for the plugin.
	 *
	 * @return void
	 */
	public function registerBlocks(): void {
		if (!function_exists('register_block_type')) {
			return;
		}

		register_block_type(LOCFINDER_DIR . 'build/blocks/locfinder-map', [
			'render_callback' => [$this, 'renderLocationMapBlock'],
		]);

		register_block_type(LOCFINDER_DIR . 'build/blocks/location-details', [
			'render_callback' => [$this, 'renderLocationDetailsBlock'],
		]);

		wp_localize_script('locfinder-locfinder-map-editor-script', 'locfinderBlockEditor', [
			'allowedTaxonomies' => (array) apply_filters(
				'locfinder_block_editor_allowed_taxonomies',
				array_values(array_filter(
					get_object_taxonomies('locfinder_location', 'names'),
					static fn ($slug) => Options::isTaxonomyAllowed($slug)
				))
			),
			'openBadgeAvailable'        => has_filter('locfinder/pro/open_now_badge'),
			'proUrl'                    => defined('LOCFINDER_PRO_URL') ? LOCFINDER_PRO_URL : '',
			'defaultResultsLayout'      => Options::getResultsLayout(),
			'defaultResultsPosition'    => Options::getResultsPosition(),
			'defaultResultsSideColumns' => Options::getResultsSideColumns(),
		]);
	}

	/**
	 * Renders the Location Map block on the front end.
	 *
	 * Converts block attributes to shortcode attributes and delegates rendering
     * to the existing shortcode handler so block and shortcode output stay in sync.
	 *
	 * @param  array  $attributes  Block attributes from the editor.
	 * @param  string $content     Inner block content (unused).
	 * @return string              Rendered HTML.
	 */
	public function renderLocationMapBlock(array $attributes, string $content = ''): string {
		$sortMap = [
			'title_asc' => ['orderby' => 'post_title', 'order' => 'ASC'],
			'date_desc' => ['orderby' => 'date', 'order' => 'DESC'],
			'random'    => ['orderby' => 'rand', 'order' => 'ASC'],
		];

		$atts = [];

		if (isset($attributes['sortBy'], $sortMap[$attributes['sortBy']])) {
			$atts = array_merge($atts, $sortMap[$attributes['sortBy']]);
		}

		unset($attributes['sortBy']);

		if (isset($attributes['postsPerPage']) && (int) $attributes['postsPerPage'] === 0) {
			unset($attributes['postsPerPage']);
		}

		// A value of 0 inherits the site default.
		if (isset($attributes['resultsColumns']) && (int) $attributes['resultsColumns'] === 0) {
			unset($attributes['resultsColumns']);
		}

		// -1 inherits the site default; 0 is a valid value for "Any distance".
		if (isset($attributes['defaultRadius']) && (int) $attributes['defaultRadius'] < 0) {
			unset($attributes['defaultRadius']);
		}

		foreach ($attributes as $key => $value) {
			if ($value === null || $value === '') {
				continue;
			}

			$shortcodeKey = $this->camelToSnake((string) $key);

			if (is_bool($value)) {
				$atts[$shortcodeKey] = $value ? '1' : '0';
			} elseif (is_scalar($value)) {
				$atts[$shortcodeKey] = (string) $value;
			}
		}

		global $shortcode_tags;

		if (isset($shortcode_tags['locfinder']) && is_callable($shortcode_tags['locfinder'])) {
			return (string) call_user_func($shortcode_tags['locfinder'], $atts, '', 'locfinder');
		}

		$shortcode = '[locfinder';

		foreach ($atts as $key => $value) {
			$shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
		}

		$shortcode .= ']';

		return do_shortcode($shortcode);
	}

	/**
	 * Renders the locfinder/location-details block.
	 *
	 * Delegates to Frontend::renderSingleLocationBody(), the same method used by
	 * the classic single-location template, keeping block-theme and classic-theme
	 * output in sync. The singular check prevents rendering in generic preview
  	 * contexts that are not tied to a Location post.
	 *
	 * @return string  Rendered HTML, or '' outside a singular Location context.
	 */
	public function renderLocationDetailsBlock(): string {
		if (!is_singular('locfinder_location')) {
			return '';
		}

		return (new Frontend())->renderSingleLocationBody();
	}

	/**
	 * Converts a camelCase block attribute name to snake_case for shortcode_atts().
	 *
	 * @param  string $input  camelCase string, e.g. 'gridCols'.
	 * @return string         snake_case string, e.g. 'grid_cols'.
	 */
	private function camelToSnake(string $input): string {
		return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
	}
}
