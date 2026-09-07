<?php
/**
 * Shortcode documentation for the Location Finder.
 *
 * Output is escaped inline at each echo rather than pre-escaped into
 * variables, so the escaping is verifiable at the point of output.
 *
 * @package Locfinder
 */

if (!defined('ABSPATH')) {
	exit;
}

// Repeated cell values, stored unescaped and escaped at output below.
$siteSetting = __('Site setting', 'locfinder');
$proRequired = __('Available with the Pro add-on', 'locfinder');
?>

<div class="wrap locfinder-shortcode-docs">
	<p class="description">
		<?php esc_html_e('Use the shortcode below to display the search form, map, and results on any page or post. Attributes can override the settings you saved in the admin.', 'locfinder'); ?>
	</p>

	<h2><?php esc_html_e('Basic shortcode', 'locfinder'); ?></h2>
	<p><code>[locfinder]</code></p>

	<h2><?php esc_html_e('Examples with attributes', 'locfinder'); ?></h2>
	<div class="locfinder-shortcode-docs__examples">
		<p class="locfinder-shortcode-docs__example"><code>[locfinder results_layout="grid" grid_cols="2" show_hours="1" posts_per_page="12"]</code></p>
		<p class="locfinder-shortcode-docs__example">
			<code>[locfinder taxonomy="your_custom_taxonomy"]</code>
			<em>(<?php echo esc_html($proRequired); ?>)</em>
		</p>
	</div>

	<h2 class="locfinder-shortcode-docs__attr-heading"><?php esc_html_e('Attribute Reference', 'locfinder'); ?></h2>
	<p><em><?php esc_html_e('Booleans accept 1/0, true/false, or yes/no. Any attribute left out falls back to the matching site-wide setting.', 'locfinder'); ?></em></p>

	<div class="locfinder-table__wrap">
		<table class="locfinder-table locfinder-table--striped locfinder-table--bordered">
			<thead>
				<tr>
					<th><?php esc_html_e('Attribute', 'locfinder'); ?></th>
					<th><?php esc_html_e('Default', 'locfinder'); ?></th>
					<th><?php esc_html_e('Description', 'locfinder'); ?></th>
					<th><?php esc_html_e('Example', 'locfinder'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>keyword_search</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the keyword search input.', 'locfinder'); ?></td>
					<td><code>keyword_search="0"</code></td>
				</tr>
				<tr>
					<td><code>address_search</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the address search input.', 'locfinder'); ?></td>
					<td><code>address_search="0"</code></td>
				</tr>
				<tr>
					<td><code>radius_search</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the radius dropdown.', 'locfinder'); ?></td>
					<td><code>radius_search="0"</code></td>
				</tr>
				<tr>
					<td><code>category_search</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the category dropdown.', 'locfinder'); ?></td>
					<td><code>category_search="0"</code></td>
				</tr>
				<tr>
					<td><code>keyword_placeholder</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Placeholder text for the keyword search input.', 'locfinder'); ?></td>
					<td><code>keyword_placeholder="Search services..."</code></td>
				</tr>
				<tr>
					<td><code>address_placeholder</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Placeholder text for the address search input.', 'locfinder'); ?></td>
					<td><code>address_placeholder="Enter your city or ZIP"</code></td>
				</tr>
				<tr>
					<td><code>search_button_label</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Label for the search form\'s submit button.', 'locfinder'); ?></td>
					<td><code>search_button_label="Find Locations"</code></td>
				</tr>
				<tr>
					<td><code>radius_label</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Screen-reader label for the radius dropdown.', 'locfinder'); ?></td>
					<td><code>radius_label="Search radius"</code></td>
				</tr>
				<tr>
					<td><code>category_label</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Screen-reader label for the category dropdown.', 'locfinder'); ?></td>
					<td><code>category_label="Filter by category"</code></td>
				</tr>
				<tr>
					<td><code>any_distance_text</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Text shown as the first option in the radius dropdown when no distance is selected.', 'locfinder'); ?></td>
					<td><code>any_distance_text="Any Distance"</code></td>
				</tr>
				<tr>
					<td><code>all_categories_text</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Text shown as the first option in the category dropdown when no category is selected.', 'locfinder'); ?></td>
					<td><code>all_categories_text="All Categories"</code></td>
				</tr>
				<tr>
					<td><code>default_radius</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Pre-selects a radius in the dropdown on page load (0 = "Any distance"). If the value isn\'t one of the configured radius options, the closest available option is used instead.', 'locfinder'); ?></td>
					<td><code>default_radius="25"</code></td>
				</tr>
				<tr>
					<td><code>results_layout</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Results layout: "grid" or "list". Any other value (including omitting the attribute) uses the site-wide setting.', 'locfinder'); ?></td>
					<td><code>results_layout="list"</code></td>
				</tr>
				<tr>
					<td><code>results_position</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Where results appear relative to the map: "bottom", "left", or "right". Any other value (including omitting the attribute) uses the site-wide setting.', 'locfinder'); ?></td>
					<td><code>results_position="left"</code></td>
				</tr>
				<tr>
					<td><code>grid_cols</code></td>
					<td>3</td>
					<td><?php esc_html_e('Number of columns in the results grid. Has no effect when results_layout resolves to "list".', 'locfinder'); ?></td>
					<td><code>grid_cols="3"</code></td>
				</tr>
				<tr>
					<td><code>results_columns</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Number of result columns when results_position is "left" or "right" (1 or 2). Has no effect when Position is "bottom".', 'locfinder'); ?></td>
					<td><code>results_columns="1"</code></td>
				</tr>
				<tr>
					<td><code>posts_per_page</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Number of results shown per page.', 'locfinder'); ?></td>
					<td><code>posts_per_page="12"</code></td>
				</tr>
				<tr>
					<td><code>show_excerpt</code></td>
					<td>0</td>
					<td><?php esc_html_e('Show a short excerpt for each result.', 'locfinder'); ?></td>
					<td><code>show_excerpt="1"</code></td>
				</tr>
				<tr>
					<td><code>show_image</code></td>
					<td>0</td>
					<td><?php esc_html_e('Show or hide the thumbnail image for each result.', 'locfinder'); ?></td>
					<td><code>show_image="1"</code></td>
				</tr>
				<tr>
					<td><code>show_address</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the address for each result.', 'locfinder'); ?></td>
					<td><code>show_address="0"</code></td>
				</tr>
				<tr>
					<td><code>show_directions</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the directions link for each result.', 'locfinder'); ?></td>
					<td><code>show_directions="0"</code></td>
				</tr>
				<tr>
					<td><code>show_phone</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the phone number for each result.', 'locfinder'); ?></td>
					<td><code>show_phone="0"</code></td>
				</tr>
				<tr>
					<td><code>show_email</code></td>
					<td>0</td>
					<td><?php esc_html_e('Show or hide the email address for each result.', 'locfinder'); ?></td>
					<td><code>show_email="1"</code></td>
				</tr>
				<tr>
					<td><code>show_website</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the website link for each result.', 'locfinder'); ?></td>
					<td><code>show_website="0"</code></td>
				</tr>
				<tr>
					<td><code>show_hours</code></td>
					<td>0</td>
					<td><?php esc_html_e('Show or hide the hours for each result.', 'locfinder'); ?></td>
					<td><code>show_hours="0"</code></td>
				</tr>
				<tr>
					<td><code>show_categories</code></td>
					<td>1</td>
					<td><?php esc_html_e('Show or hide the category list for each result.', 'locfinder'); ?></td>
					<td><code>show_categories="0"</code></td>
				</tr>
				<tr>
					<td><code>show_open_badge</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td>
						<?php esc_html_e('Show or hide the open/closed status badge for each result.', 'locfinder'); ?>
						<em>(<?php echo esc_html($proRequired); ?>)</em>
					</td>
					<td><code>show_open_badge="0"</code></td>
				</tr>
				<tr>
					<td><code>taxonomy</code></td>
					<td>locfinder_category</td>
					<td>
						<?php esc_html_e('Taxonomy used for the category dropdown and filtering.', 'locfinder'); ?>
						<em>(<?php esc_html_e('Values other than locfinder_category are available with the Pro add-on, and the taxonomy must be registered against the locfinder_location post type. To also appear in the block editor, register it with show_in_rest set to true.', 'locfinder'); ?>)</em>
					</td>
					<td><code>taxonomy="your_custom_taxonomy"</code></td>
				</tr>
				<tr>
					<td><code>order</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Sort direction, used together with orderby.', 'locfinder'); ?></td>
					<td><code>order="DESC"</code></td>
				</tr>
				<tr>
					<td><code>orderby</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Field to sort results by: post_title, date, meta_value, or rand.', 'locfinder'); ?></td>
					<td><code>orderby="date"</code></td>
				</tr>
				<tr>
					<td><code>pin_color</code></td>
					<td><?php echo esc_html($siteSetting); ?></td>
					<td><?php esc_html_e('Sets the map pin color for this instance.', 'locfinder'); ?></td>
					<td><code>pin_color="#FF5733"</code></td>
				</tr>
			</tbody>
		</table>
	</div>

	<p class="description locfinder-shortcode-docs__tip">
		<?php esc_html_e('Tip: When placing shortcodes inside block editors or attributes, wrap with double quotes and escape inner quotes as needed.', 'locfinder'); ?>
	</p>
</div>
