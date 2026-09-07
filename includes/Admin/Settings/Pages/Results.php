<?php
/**
 * Defines the results list settings page.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Admin;
use Locfinder\Admin\Settings\Base;
use Locfinder\Utilities\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Settings and fields for the results list settings page.
 */
class Results extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_results';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Results List', 'locfinder');
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_results_layout',
			__('Layout Settings', 'locfinder'),
			function () {
				echo '<p>' . esc_html__('Choose how location results are displayed beneath the map and which fields are shown for each entry.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_results_position',
			__('Results Position', 'locfinder'),
			function () {
				echo '<p>' . esc_html__('Choose where location results appear relative to the map.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_results_distance',
			__('Distance Settings', 'locfinder'),
			function () {
				echo '<p>' . esc_html__('Control how distance appears between the address entered and each location.', 'locfinder') . '</p>';

				if (!has_action('locfinder/pro/render_results_fields')) {
					$this->renderProResultsFields(['context' => 'distance']);
				}
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_results_behavior',
			__('Behavior Settings', 'locfinder'),
			function () {
				echo '<p>' . esc_html__('Configure how result links behave when clicked.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_results_templates',
			__('Template Customization', 'locfinder'),
			function () {
				echo '<p>' . esc_html__('Customize the HTML structure used to render each result item. Use available tokens to dynamically insert data.', 'locfinder') . '</p>';

				if (!has_action('locfinder/pro/render_results_fields')) {
					$this->renderProResultsFields(['context' => 'templates']);
				}
			},
			$this->getSlug()
		);

		add_settings_section(
			'locfinder_no_results',
			__('No Results Message', 'locfinder'),
			function () {
				echo '<p>' . esc_html__('Set the message displayed when no locations are found for a given search.', 'locfinder') . '</p>';
			},
			$this->getSlug()
		);

		add_settings_field(
			'results_layout',
			__('Layout', 'locfinder'),
			[$this, 'renderResultsLayout'],
			$this->getSlug(),
			'locfinder_results_layout',
			['label_for' => 'results_layout']
		);

		add_settings_field(
			'results_per_page',
			__('Results Per Page', 'locfinder'),
			[$this, 'renderResultsPerPage'],
			$this->getSlug(),
			'locfinder_results_layout',
			['label_for' => 'results_per_page']
		);

		add_settings_field(
			'results_position',
			__('Position', 'locfinder'),
			[$this, 'renderResultsPosition'],
			$this->getSlug(),
			'locfinder_results_position',
			['label_for' => 'results_position']
		);

		add_settings_field(
			'results_side_columns',
			__('Columns (Left or Right Only)', 'locfinder'),
			[$this, 'renderResultsSideColumns'],
			$this->getSlug(),
			'locfinder_results_position',
			['label_for' => 'results_side_columns']
		);

		if (has_action('locfinder/pro/render_results_fields')) {
			add_settings_field(
				'locfinder_pro_results_fields',
				'',
				[$this, 'renderProResultsFields'],
				$this->getSlug(),
				'locfinder_results_distance',
				['context' => 'distance']
			);
		}

		add_settings_field(
			'enable_open_now_badge',
			__('Open Now Badge', 'locfinder'),
			[$this, 'renderShowOpenNowBadge'],
			$this->getSlug(),
			'locfinder_results_behavior'
		);

		add_settings_field(
			'result_link_target',
			__('Link Behavior', 'locfinder'),
			[$this, 'renderResultLinkTarget'],
			$this->getSlug(),
			'locfinder_results_behavior',
			['label_for' => 'result_link_target']
		);

		if (has_action('locfinder/pro/render_results_fields')) {
			add_settings_field(
				'locfinder_pro_results_template_fields',
				'',
				[$this, 'renderProResultsFields'],
				$this->getSlug(),
				'locfinder_results_templates',
				['context' => 'templates']
			);
		}

		add_settings_field(
			'no_results_message',
			__('No Results Message', 'locfinder'),
			[$this, 'renderNoResultsMessage'],
			$this->getSlug(),
			'locfinder_no_results',
			['label_for' => 'no_results_message']
		);
	}

	/**
	 * Renders any Results-related fields contributed by the Pro add-on.
	 *
	 * If the Pro add-on is active, this method fires the
	 * locfinder/pro/render_results_fields action to render its own fields
	 * in the Results settings page; otherwise it renders an upsell message.
	 *
	 * @param  array $args  Field args, expects $args['context'] of 'distance' or 'templates'.
	 * @return void
	 */
	public function renderProResultsFields(array $args = []): void {
		$context = $args['context'] ?? '';

		if (has_action('locfinder/pro/render_results_fields')) {
			do_action('locfinder/pro/render_results_fields', $this, $context);
			return;
		}

		$messages = [
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'distance' => __('Unlock distance display with the %1$sPro add-on%2$s.', 'locfinder'),
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'templates' => __('Customize the result item template with the %1$sPro add-on%2$s.', 'locfinder'),
		];

		Admin::proUpsell([
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'message' => $messages[$context] ?? __('Unlock this option with the %1$sPro add-on%2$s.', 'locfinder'),
		]);
	}

	/**
	 * Renders the results per page field.
	 *
	 * Controls how many results are shown per page in the results list.
	 *
	 * @return void
	 */
	public function renderResultsPerPage(): void {
		$val  = (int) $this->getPageOption('results_per_page', 12);
		$name = $this->getOptionName() . '[results_per_page]';

		printf(
			'<input id="results_per_page" type="number" min="1" max="100" placeholder="12" name="%s" value="%s" />',
			esc_attr($name),
			esc_attr($val)
		);
	}

	/**
	 * Renders the results layout select field.
	 *
	 * Controls whether results appear as a single-column list or a
	 * multi-column grid.
	 *
	 * @return void
	 */
	public function renderResultsLayout(): void {
		$val  = $this->getPageOption('results_layout', 'grid');
		$name = $this->getOptionName() . '[results_layout]';

		printf(
			'<select id="results_layout" name="%1$s">
				<option value="grid" %2$s>%3$s</option>
				<option value="list" %4$s>%5$s</option>
			</select>',
			esc_attr($name),
			selected($val, 'grid', false),
			esc_html__('Grid', 'locfinder'),
			selected($val, 'list', false),
			esc_html__('List', 'locfinder')
		);
	}

	/**
	 * Renders the results position select field.
	 *
	 * Controls whether results appear below the map (default), or beside it
	 * on the left or right, similar to a Zillow-style split layout.
	 *
	 * @return void
	 */
	public function renderResultsPosition(): void {
		$val  = $this->getPageOption('results_position', 'left');
		$name = $this->getOptionName() . '[results_position]';

		printf(
			'<select id="results_position" name="%1$s">
				<option value="bottom" %2$s>%3$s</option>
				<option value="left" %4$s>%5$s</option>
				<option value="right" %6$s>%7$s</option>
			</select>',
			esc_attr($name),
			selected($val, 'bottom', false),
			esc_html__('Bottom (below the map)', 'locfinder'),
			selected($val, 'left', false),
			esc_html__('Left of the map', 'locfinder'),
			selected($val, 'right', false),
			esc_html__('Right of the map', 'locfinder')
		);
	}

	/**
	 * Renders the results side columns select field.
	 *
	 * Controls how many columns of results are shown when Position is set
	 * to Left or Right. Has no effect when Position is Bottom.
	 *
	 * @return void
	 */
	public function renderResultsSideColumns(): void {
		$val  = (int) $this->getPageOption('results_side_columns', 2);
		$name = $this->getOptionName() . '[results_side_columns]';

		printf(
			'<select id="results_side_columns" name="%1$s">
				<option value="1" %2$s>%3$s</option>
				<option value="2" %4$s>%5$s</option>
			</select>
			<p class="description">%6$s</p>',
			esc_attr($name),
			selected($val, 1, false),
			esc_html__('1 column', 'locfinder'),
			selected($val, 2, false),
			esc_html__('2 columns', 'locfinder'),
			esc_html__('Only applies when Position above is Left or Right.', 'locfinder')
		);
	}

	/**
	 * Renders the show open now badge checkbox.
	 *
	 * If the Pro add-on is active, this field allows users to show a live "Open Now" / "Closed Now"
	 * badge next to each location's hours; otherwise it renders an upsell message.
	 *
	 * @return void
	 */
	public function renderShowOpenNowBadge(): void {
		if (has_action('locfinder/pro/render_results_fields')) {
			do_action('locfinder/pro/render_results_fields', $this, 'open_now_badge');
			return;
		}

		Admin::proUpsell([
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag */
			'message' => __('Unlock the live "Open Now" / "Closed Now" badge with the %1$sPro add-on%2$s.', 'locfinder'),
		]);
	}

	/**
	 * Renders the result link target select field.
	 *
	 * Controls whether result links open in the same tab or a new tab.
	 *
	 * @return void
	 */
	public function renderResultLinkTarget(): void {
		$val  = $this->getPageOption('result_link_target', 'same');
		$name = $this->getOptionName() . '[result_link_target]';

		printf(
			'<select id="result_link_target" name="%1$s">
				<option value="same" %2$s>%3$s</option>
				<option value="new" %4$s>%5$s</option>
			</select>',
			esc_attr($name),
			selected($val, 'same', false),
			esc_html__('Open in same tab', 'locfinder'),
			selected($val, 'new', false),
			esc_html__('Open in new tab', 'locfinder')
		);
	}

	/**
	 * Renders the no results message text field.
	 *
	 * Controls the message displayed when no locations are found for a search.
	 * If left blank, the default message "No locations found." is used.
	 *
	 * @return void
	 */
	public function renderNoResultsMessage(): void {
		$default = __('No locations found.', 'locfinder');
		$val     = $this->getPageOption('no_results_message', $default);
		printf(
			'<input id="no_results_message" type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
			esc_attr($this->getOptionName() . '[no_results_message]'),
			esc_attr($val),
			esc_attr($default)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize(array $options, array $existing): array {
		$out = $existing;

		$out['results_layout'] = isset($options['results_layout'])
			? Sanitizer::select($options['results_layout'], ['grid', 'list'], 'grid')
			: (string) ($existing['results_layout'] ?? 'grid');

		$out['results_position'] = isset($options['results_position'])
			? Sanitizer::select($options['results_position'], ['bottom', 'left', 'right'], 'left')
			: (string) ($existing['results_position'] ?? 'left');

		$out['results_side_columns'] = isset($options['results_side_columns'])
			? (in_array((int) $options['results_side_columns'], [1, 2], true) ? (int) $options['results_side_columns'] : 2)
			: (int) ($existing['results_side_columns'] ?? 2);

		$out['result_link_target'] = isset($options['result_link_target'])
			? Sanitizer::select($options['result_link_target'], ['same', 'new'], 'same')
			: (string) ($existing['result_link_target'] ?? 'same');

		// Results per page (min 1, practical cap at 100 matches AjaxController::MAX_PER_PAGE).
		$perPage                 = isset($options['results_per_page']) ? (int) $options['results_per_page'] : (int) ($existing['results_per_page'] ?? 12);
		$out['results_per_page'] = max(1, min(100, $perPage));

		$out['no_results_message'] = isset($options['no_results_message'])
			? Sanitizer::text($options['no_results_message'], 120)
			: (string) ($existing['no_results_message'] ?? '');

		$out = apply_filters('locfinder/pro/sanitize_results_fields', $out, $options, $existing);

		return $out;
	}
}
