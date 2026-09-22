<?php
/**
 * Defines the search & filters settings page.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Admin;
use Locfinder\Admin\Settings\Base;
use Locfinder\Utilities\Options;
use Locfinder\Utilities\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings and fields for the search & filters settings page.
 */
class SearchFilters extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_search_filters';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Search & Filters', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_radius_settings',
			__('Radius Settings', 'locfinder'),
			function () {
				echo '<p class="description">' . esc_html__('Configure the radius filter shown in the search form.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_taxonomy_filters',
			__('Category Settings', 'locfinder'),
			function () {
				echo '<p class="description">' . esc_html__('Configure the category filter shown in the search form.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_labels_placeholders',
			__('Labels & Placeholders', 'locfinder'),
			function () {
				echo '<p class="description">' . esc_html__('Customize the labels and placeholders shown in the search form. Labels are read by screen readers for accessibility.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_dropdown_defaults',
			__('Dropdown Defaults', 'locfinder'),
			function () {
				echo '<p class="description">' . esc_html__('Customize the text shown as the first option in each dropdown when no selection has been made.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_results_settings',
			__('Results Settings', 'locfinder'),
			null,
			$this->getSlug()
		);

		add_settings_field(
			'default_radius',
			__('Default Radius', 'locfinder'),
			[$this, 'renderDefaultRadius'],
			$this->getSlug(),
			'locfinder_radius_settings',
			['label_for' => 'default_radius']
		);

		add_settings_field(
			'radius_values',
			__('Radius Options', 'locfinder'),
			[$this, 'renderProSearchFiltersFields'],
			$this->getSlug(),
			'locfinder_radius_settings',
			['label_for' => 'radius_values']
		);

		add_settings_field(
			'taxonomy_filter',
			__('Category Filter', 'locfinder'),
			[$this, 'renderTaxonomyFilter'],
			$this->getSlug(),
			'locfinder_taxonomy_filters',
		);

		add_settings_field(
			'labeling',
			__('Labels and Placeholders', 'locfinder'),
			[$this, 'renderLabelsGroup'],
			$this->getSlug(),
			'locfinder_labels_placeholders'
		);

		add_settings_field(
			'any_distance_text',
			__('Radius Default Option', 'locfinder'),
			[$this, 'renderAnyDistanceText'],
			$this->getSlug(),
			'locfinder_dropdown_defaults',
			['label_for' => 'any_distance_text']
		);

		add_settings_field(
			'all_categories_text',
			__('Category Default Option', 'locfinder'),
			[$this, 'renderAllCategoriesText'],
			$this->getSlug(),
			'locfinder_dropdown_defaults',
			['label_for' => 'all_categories_text']
		);

		add_settings_field(
			'sort_by',
			__('Sort Results By', 'locfinder'),
			[$this, 'renderSortBy'],
			$this->getSlug(),
			'locfinder_results_settings',
			['label_for' => 'sort_by']
		);
	}

	/**
	 * Renders the default radius select field.
	 *
	 * Controls which option is pre-selected in the search form's radius
	 * dropdown when no radius has been chosen yet.
	 *
	 * @return void
	 */
	public function renderDefaultRadius(): void {
		$val   = (int) $this->getPageOption('default_radius', 0);
		$name  = $this->getOptionName() . '[default_radius]';
		$unit  = Options::getDistanceUnit() === 'km' ? __('km', 'locfinder') : __('mi', 'locfinder');
		$radii = Options::getRadiusValueList();

		$options = sprintf(
			'<option value="0" %s>%s</option>',
			selected($val, 0, false),
			esc_html__('Any Distance', 'locfinder')
		);

		foreach ($radii as $radiusVal) {
			$options .= sprintf(
				'<option value="%s" %s>%s %s</option>',
				esc_attr($radiusVal),
				selected($val, $radiusVal, false),
				esc_html($radiusVal),
				esc_html($unit)
			);
		}

		printf(
			'<select id="default_radius" name="%s">%s</select>',
			esc_attr($name),
			wp_kses($options, ['option' => ['value' => true, 'selected' => true]])
		);
	}

	/**
	 * Renders any Search & Filters fields contributed by the Pro add-on.
	 *
	 * If the Pro add-on is active, its own fields render via the
	 * 'locfinder/pro/render_search_filters_fields' action; otherwise this
	 * renders a generic proUpsell() notice for the locked custom-radius field.
	 *
	 * @return void
	 */
	public function renderProSearchFiltersFields(): void {
		if (has_action('locfinder/pro/render_search_filters_fields')) {
			do_action('locfinder/pro/render_search_filters_fields', $this);
			return;
		}

		Admin::proUpsell([
			'message' => __('Customize search radius distances beyond the standard 10, 25, 50, and 75 options.', 'locfinder'),
		]);
	}

	/**
	 * Renders label and placeholder text inputs for all search form fields.
	 *
	 * These fields allow the admin to customize the labels and placeholders
	 * for the search form inputs.
	 *
	 * @return void
	 */
	public function renderLabelsGroup(): void {
		$fields = [
			'keyword_placeholder' => __('Keyword Placeholder', 'locfinder'),
			'address_placeholder' => __('Address Placeholder', 'locfinder'),
			'search_button_label' => __('Search Button Label', 'locfinder'),
			'radius_label'        => __('Radius Label', 'locfinder'),
			'category_label'      => __('Category Label', 'locfinder'),
		];

		foreach ($fields as $key => $label) {
			$val  = $this->getPageOption($key, '');
			$name = $this->getOptionName() . '[' . $key . ']';

			printf(
				'<div class="locfinder-admin__field-group">
					<label for="%1$s">%2$s</label>
					<input id="%1$s" type="text" class="regular-text" name="%3$s" value="%4$s" />
				</div>',
				esc_attr($key),
				esc_html($label),
				esc_attr($name),
				esc_attr($val)
			);
		}
	}

	/**
	 * Renders the "Any Distance" first option text field for the radius dropdown.
	 *
	 * Allows the admin to customize the text for the "Any Distance" option in
	 * the radius dropdown.
	 *
	 * @return void
	 */
	public function renderAnyDistanceText(): void {
		$val  = $this->getPageOption('any_distance_text', '');
		$name = $this->getOptionName() . '[any_distance_text]';

		printf(
			'<input id="any_distance_text" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />
			<p class="description">%4$s</p>',
			esc_attr($name),
			esc_attr($val),
			esc_attr__('Any Distance', 'locfinder'),
			esc_html__('Shown as the first option in the radius dropdown when no distance is selected.', 'locfinder')
		);
	}

	/**
	 * Renders the "All Categories" first option text field for the category dropdown.
	 *
	 * Allows the admin to customize the text for the "All Categories" option in
	 * the category dropdown.
	 *
	 * @return void
	 */
	public function renderAllCategoriesText(): void {
		$val  = $this->getPageOption('all_categories_text', '');
		$name = $this->getOptionName() . '[all_categories_text]';

		printf(
			'<input id="all_categories_text" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />
			<p class="description">%4$s</p>',
			esc_attr($name),
			esc_attr($val),
			esc_attr__('All Categories', 'locfinder'),
			esc_html__('Shown as the first option in the category dropdown when no category is selected.', 'locfinder')
		);
	}

	/**
	 * Renders the taxonomy filter select field.
	 *
	 * Always shows a note on which taxonomy the free version filters by. If
	 * the Pro add-on is active, its own field renders via the
	 * 'locfinder/pro/render_taxonomy_filter_field' action; otherwise this
	 * renders a generic proUpsell() notice for filtering by any taxonomy.
	 *
	 * @return void
	 */
	public function renderTaxonomyFilter(): void {
		printf(
			'<p>%s</p>',
			esc_html__('The free version filters by the built-in Category taxonomy.', 'locfinder')
		);

		if (has_action('locfinder/pro/render_taxonomy_filter_field')) {
			do_action('locfinder/pro/render_taxonomy_filter_field', $this);
			return;
		}

		Admin::proUpsell([
			'message' => __('Let visitors filter by any registered taxonomy.', 'locfinder'),
		]);
	}

	/**
	 * Renders the sort order select field.
	 *
	 * Controls the default sort order for search results.
	 *
	 * @return void
	 */
	public function renderSortBy(): void {
		$val  = $this->getPageOption('sort_by', 'title');
		$name = $this->getOptionName() . '[sort_by]';

		$choices = [
			'title'    => __('Title (A-Z)', 'locfinder'),
			'date'     => __('Date (Newest)', 'locfinder'),
			'distance' => __('Distance (Nearest)', 'locfinder'),
		];

		$options = '';

		foreach ($choices as $value => $label) {
			$options .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr($value),
				selected($val, $value, false),
				esc_html($label)
			);
		}

		printf(
			'<select id="sort_by" name="%1$s">%2$s</select>
			<p class="description">%3$s</p>',
			esc_attr($name),
			wp_kses($options, ['option' => ['value' => true, 'selected' => true]]),
			esc_html__('Distance sorting only applies when a radius search is performed.', 'locfinder')
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		$submittedRadius = isset($options['default_radius'])
			? (int) $options['default_radius']
			: (int) ($existing['default_radius'] ?? 0);

		$out['default_radius'] = ($submittedRadius === 0 || in_array($submittedRadius, Options::getRadiusValueList(), true))
			? $submittedRadius
			: 0;

		$out['taxonomy_filter'] = 'locfinder_category';

		// Sanitize and store the text fields for labels and placeholders.
		foreach (['keyword_placeholder', 'address_placeholder', 'search_button_label', 'radius_label', 'category_label', 'any_distance_text', 'all_categories_text'] as $key) {
			$out[$key] = isset($options[$key])
				? Sanitizer::text($options[$key], 80)
				: (string) ($existing[$key] ?? '');
		}

		// Validate and store the default sort order for search results.
		$sort           = isset($options['sort_by']) ? sanitize_key((string) $options['sort_by']) : (string) ($existing['sort_by'] ?? 'title');
		$out['sort_by'] = in_array($sort, ['distance', 'title', 'date'], true) ? $sort : 'title';

		// Allow third-party code to modify the sanitized search filter options.
		$out = apply_filters('locfinder/pro/sanitize_search_filters_fields', $out, $options, $existing);

		return $out;
	}
}
