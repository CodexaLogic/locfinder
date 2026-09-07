<?php
/**
 * Location Finder map shortcode view.
 *
 * Admin settings provide the defaults, while shortcode attributes can override
 * them per instance. Labels and placeholders can also be customized through
 * shortcode attributes without changing the global settings.
 *
 * @package Locfinder
 */

use Locfinder\Frontend\Frontend;
use Locfinder\Utilities\Options;

if (!defined('ABSPATH')) {
	exit;
}

$atts      = isset($atts) ? $atts : [];
$sortBy    = Options::getSortBy();
$sortByMap = [
	'title'    => 'post_title',
	'date'     => 'date',
	'distance' => 'post_title',
];

$atts = shortcode_atts([

	/* Search options */
	'keyword_search'  => true,
	'address_search'  => true,
	'radius_search'   => true,
	'category_search' => true,

	/* Labels & placeholders */
	'keyword_placeholder' => Options::getKeywordPlaceholder(),
	'address_placeholder' => Options::getAddressPlaceholder(),
	'search_button_label' => Options::getSearchButtonLabel(),
	'radius_label'        => Options::getRadiusLabel(),
	'category_label'      => Options::getCategoryLabel(),

	/* Dropdown defaults */
	'any_distance_text'   => Options::getAnyDistanceText(),
	'all_categories_text' => Options::getAllCategoriesText(),
	'default_radius'      => Options::getDefaultRadius(),

	/* Results */
	'results_layout'   => 'default',
	'results_position' => 'default',
	'grid_cols'        => 3,
	'results_columns'  => 0,
	'posts_per_page'   => Options::getResultsPerPage(),
	'show_excerpt'     => false,
	'show_image'       => false,
	'show_address'     => true,
	'show_directions'  => true,
	'show_phone'       => true,
	'show_email'       => false,
	'show_website'     => true,
	'show_hours'       => false,
	'show_categories'  => true,
	'show_open_badge'  => Options::getShowOpenNowBadge(),
	'order'            => $sortBy === 'date' ? 'DESC' : 'ASC',
	'orderby'          => $sortByMap[$sortBy] ?? 'post_title',

	/* Map */
	'pin_color' => Options::getPinColor(),
	'taxonomy'  => Options::getTaxonomyFilter(),

], $atts);

// Sanitize boolean toggle attributes.
$keywordSearch  = filter_var($atts['keyword_search'], FILTER_VALIDATE_BOOLEAN);
$addressSearch  = filter_var($atts['address_search'], FILTER_VALIDATE_BOOLEAN);
$radiusSearch   = filter_var($atts['radius_search'], FILTER_VALIDATE_BOOLEAN);
$categorySearch = filter_var($atts['category_search'], FILTER_VALIDATE_BOOLEAN);

$radiusSearch    = $radiusSearch && $addressSearch;
$hasSearchFields = $keywordSearch || $addressSearch || $radiusSearch || $categorySearch;

// Sanitize label and placeholder attributes, pulled from $atts so shortcode
// overrides are respected. Options getters already provide translated defaults.
$keywordPlaceholder = sanitize_text_field($atts['keyword_placeholder']);
$addressPlaceholder = sanitize_text_field($atts['address_placeholder']);
$searchButtonLabel  = sanitize_text_field($atts['search_button_label']);
$radiusLabel        = sanitize_text_field($atts['radius_label']);
$categoryLabel      = sanitize_text_field($atts['category_label']);
$anyDistanceText    = sanitize_text_field($atts['any_distance_text']);
$allCategoriesText  = sanitize_text_field($atts['all_categories_text']);

// Sanitize remaining attributes.
$resultsLayout = in_array($atts['results_layout'], ['grid', 'list'], true)
	? $atts['results_layout']
	: Options::getResultsLayout();

$resultsPosition = in_array($atts['results_position'], ['bottom', 'left', 'right'], true)
	? $atts['results_position']
	: Options::getResultsPosition();

$resultsColumns = in_array((int) $atts['results_columns'], [1, 2], true)
	? (int) $atts['results_columns']
	: Options::getResultsSideColumns();

$gridCols          = absint($atts['grid_cols']);
$order             = in_array(strtoupper($atts['order']), ['ASC', 'DESC'], true) ? strtoupper($atts['order']) : 'ASC';
$orderby           = in_array($atts['orderby'], ['date', 'meta_value', 'post_title', 'rand'], true) ? sanitize_key($atts['orderby']) : 'post_title';
$pinColor          = sanitize_hex_color($atts['pin_color']) ?: Options::getPinColor();
$postsPerPage      = absint($atts['posts_per_page']);
$defaultRadius     = absint($atts['default_radius']);
$requestedTaxonomy = sanitize_key($atts['taxonomy']);
$showAddress       = filter_var($atts['show_address'], FILTER_VALIDATE_BOOLEAN);
$showDirections    = filter_var($atts['show_directions'], FILTER_VALIDATE_BOOLEAN);
$showPhone         = filter_var($atts['show_phone'], FILTER_VALIDATE_BOOLEAN);
$showEmail         = filter_var($atts['show_email'], FILTER_VALIDATE_BOOLEAN);
$showWebsite       = filter_var($atts['show_website'], FILTER_VALIDATE_BOOLEAN);
$showHours         = filter_var($atts['show_hours'], FILTER_VALIDATE_BOOLEAN);
$showCategories    = filter_var($atts['show_categories'], FILTER_VALIDATE_BOOLEAN);
$showOpenBadge     = filter_var($atts['show_open_badge'], FILTER_VALIDATE_BOOLEAN);

// Unique ID per shortcode instance on each page.
$id = wp_unique_id('locfinder-');

$config = [
	'instanceId'      => $id,
	'resultsLayout'   => $resultsLayout,
	'resultsPosition' => $resultsPosition,
	'resultsColumns'  => $resultsColumns,
	'gridCols'        => $gridCols,
	'order'           => $order,
	'orderby'         => $orderby,
	'pinColor'        => $pinColor,
	'postsPerPage'    => $postsPerPage,
	'showExcerpt'     => filter_var($atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN),
	'showImage'       => filter_var($atts['show_image'], FILTER_VALIDATE_BOOLEAN),
	'showAddress'     => $showAddress,
	'showDirections'  => $showDirections,
	'showPhone'       => $showPhone,
	'showEmail'       => $showEmail,
	'showWebsite'     => $showWebsite,
	'showHours'       => $showHours,
	'showCategories'  => $showCategories,
	'showOpenBadge'   => $showOpenBadge,
	'taxonomy'        => $requestedTaxonomy,
	'termPinStyles'   => Options::getTermPinStyles($requestedTaxonomy),
];
?>

<div
	id="<?php echo esc_attr($id); ?>"
	class="locfinder"
	data-locfinder
	data-results-position="<?php echo esc_attr($resultsPosition); ?>"
>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderSetupNotice() escapes every dynamic value internally (esc_attr/esc_html/esc_url/esc_html__); the rest is static markup, including inline SVG icons that wp_kses_post() would strip.
	echo Frontend::renderSetupNotice();
	?>

	<script type="application/json" class="locfinder-config">
		<?php echo wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>
	</script>

	<div class="locfinder__search">
		<form class="locfinder-form<?php echo $hasSearchFields ? '' : ' locfinder-form--empty'; ?>" role="search" aria-label="<?php /* translators: %s: unique instance ID, used to distinguish multiple search forms on the same page for screen readers. */ echo esc_attr(sprintf(__('Location search %s', 'locfinder'), $id)); ?>">
			<?php if ($keywordSearch) : ?>
				<label for="<?php echo esc_attr($id . '-locfinder_keyword'); ?>" class="locfinder-form__label">
					<?php echo esc_html($keywordPlaceholder); ?>
				</label>
				<input
					id="<?php echo esc_attr($id . '-locfinder_keyword'); ?>"
					class="locfinder-form__field locfinder-form__keyword"
					type="text"
					name="filter_post_title"
					placeholder="<?php echo esc_attr($keywordPlaceholder); ?>"
					aria-label="<?php echo esc_attr($keywordPlaceholder); ?>"
				>
			<?php endif; ?>

			<?php if ($addressSearch) : ?>
				<label for="<?php echo esc_attr($id . '-locfinder_address'); ?>" class="locfinder-form__label">
					<?php echo esc_html($addressPlaceholder); ?>
				</label>
				<div class="locfinder-form__address-group">
					<input
						id="<?php echo esc_attr($id . '-locfinder_address'); ?>"
						class="locfinder-form__field locfinder-form__address"
						type="text"
						name="filter_address"
						placeholder="<?php echo esc_attr($addressPlaceholder); ?>"
						aria-label="<?php echo esc_attr($addressPlaceholder); ?>"
					>
					<button
						type="button"
						class="locfinder-form__geolocate"
						aria-label="<?php esc_attr_e('Use my location', 'locfinder'); ?>"
					>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="12" r="3"/>
							<path d="M12 2v3"/>
							<path d="M12 19v3"/>
							<path d="M2 12h3"/>
							<path d="M19 12h3"/>
						</svg>
					</button>
				</div>
			<?php endif; ?>

			<?php if ($radiusSearch) : ?>
				<label for="<?php echo esc_attr($id . '-locfinder_radius'); ?>" class="locfinder-form__label">
					<?php echo esc_html($radiusLabel); ?>
				</label>
				<select
					id="<?php echo esc_attr($id . '-locfinder_radius'); ?>"
					class="locfinder-form__field locfinder-form__radius"
					name="filter_radius"
					aria-label="<?php echo esc_attr($radiusLabel); ?>"
				>
					<?php
					$distanceUnit   = Options::getDistanceUnit();
					$unitLabel      = $distanceUnit === 'km' ? __('km', 'locfinder') : __('mi', 'locfinder');
					$availableRadii = Options::getRadiusValueList();

					if ($defaultRadius !== 0 && !in_array($defaultRadius, $availableRadii, true)) {
						if (empty($availableRadii)) {
							$defaultRadius = 0;
						} else {
							$closest = $availableRadii[0];

							foreach ($availableRadii as $radiusOption) {
								if (abs($defaultRadius - $radiusOption) < abs($defaultRadius - $closest)) {
									$closest = $radiusOption;
								}
							}

							$defaultRadius = $closest;
						}
					}

					echo '<option value="0"' . selected($defaultRadius, 0, false) . '>' . esc_html($anyDistanceText) . '</option>';

					foreach ($availableRadii as $radiusVal) {
						echo '<option value="' . esc_attr($radiusVal) . '"';
						selected($defaultRadius, $radiusVal);
						echo '>' . esc_html($radiusVal . ' ' . $unitLabel) . '</option>';
					}
					?>
				</select>
			<?php endif; ?>

			<?php if ($categorySearch) :
				$terms = get_terms(['taxonomy' => 'locfinder_category', 'hide_empty' => true]);

				/**
				 * Filters the terms populating the search form's category/taxonomy
				 * dropdown.
				 *
				 * Locfinder always builds this from locfinder_category. Pro replaces
				 * the array with its own get_terms() call against $requestedTaxonomy
				 * when the license is active and the taxonomy is valid.
				 *
				 * @param WP_Term[]|WP_Error $terms              Category terms.
				 * @param string             $requestedTaxonomy  Raw taxonomy slug requested for this instance.
				 */
				$terms = apply_filters('locfinder_category_dropdown_terms', $terms, $requestedTaxonomy);

				if ($terms && !is_wp_error($terms)) : ?>
					<label for="<?php echo esc_attr($id . '-locfinder_category'); ?>" class="locfinder-form__label">
						<?php echo esc_html($categoryLabel); ?>
					</label>
					<select
						id="<?php echo esc_attr($id . '-locfinder_category'); ?>"
						class="locfinder-form__field locfinder-form__category"
						name="filter_category"
						aria-label="<?php echo esc_attr($categoryLabel); ?>"
					>
						<option value="0"><?php echo esc_html($allCategoriesText); ?></option>
						<?php foreach ($terms as $term) : ?>
							<option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif;
			endif; ?>

			<?php if ($hasSearchFields) : ?>
				<button type="submit" class="locfinder-form__submit" aria-label="<?php echo esc_attr($searchButtonLabel); ?>">
					<?php echo esc_html($searchButtonLabel); ?>
				</button>
			<?php endif; ?>

			<input id="<?php echo esc_attr($id . '-locfinder_lat'); ?>" type="hidden" name="filter_lat">
			<input id="<?php echo esc_attr($id . '-locfinder_lng'); ?>" type="hidden" name="filter_lng">
		</form>
	</div>

	<div class="locfinder__body">
		<div class="locfinder__results-panel" tabindex="0">
			<div class="locfinder__total" role="status" aria-live="<?php echo esc_attr(Options::getAriaLiveMode()); ?>" aria-atomic="true"></div>

			<ul
				class="locfinder__results"
				role="list"
				aria-label="<?php esc_attr_e('Location search results', 'locfinder'); ?>"
			></ul>

			<div
				class="locfinder-pagination"
				role="navigation"
				aria-label="<?php esc_attr_e('Search results pagination', 'locfinder'); ?>"
			></div>
		</div>

		<?php /* translators: %s: unique instance ID for this map */ ?>
		<div class="locfinder__map" role="region" aria-label="<?php echo esc_attr(sprintf(__('Location map %s', 'locfinder'), $id)); ?>"></div>
	</div>
</div>
