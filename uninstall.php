<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Handles single-site and multisite installs. On multisite, uninstall fires
 * once for the whole network, so every site is visited individually.
 *
 * @package Locfinder
 * @link    https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/**
 * Removes the plugin's data for the current site.
 *
 * Location content, custom tables, settings, and the generated Locations page
 * are only removed when that site has opted in via the "Delete data on uninstall"
 * setting, which is read per-site since each site sets it independently.
 *
 * @return void
 */
function locfinder_uninstall_site(): void {
	global $wpdb;

	$capabilities = [
		'edit_locfinder_location', 'read_locfinder_location', 'delete_locfinder_location',
		'edit_locfinder_locations', 'edit_others_locfinder_locations', 'publish_locfinder_locations',
		'read_private_locfinder_locations', 'delete_locfinder_locations', 'delete_private_locfinder_locations',
		'delete_published_locfinder_locations', 'delete_others_locfinder_locations',
		'edit_private_locfinder_locations', 'edit_published_locfinder_locations',
	];

	$roles = apply_filters('locfinder/capable_roles', ['administrator', 'editor']);

	foreach ($roles as $roleSlug) {
		$role = get_role($roleSlug);

		if (!$role) {
			continue;
		}

		foreach ($capabilities as $cap) {
			$role->remove_cap($cap);
		}
	}

	$tools = get_option('locfinder_tools', []);

	if (empty($tools['delete_data_on_uninstall'])) {
		return;
	}

	// Delete all location posts and their associated meta.
	$posts = get_posts([
		'post_type'      => 'locfinder_location',
		'post_status'    => ['any', 'trash'],
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);

	foreach ($posts as $postId) {
		wp_delete_post($postId, true);
	}

	if (!taxonomy_exists('locfinder_category')) {
		register_taxonomy('locfinder_category', 'locfinder_location');
	}

	// Delete all location terms.
	$terms = get_terms(['taxonomy' => 'locfinder_category', 'hide_empty' => false]);

	if (!is_wp_error($terms)) {
		foreach ($terms as $term) {
			wp_delete_term($term->term_id, 'locfinder_category');
		}
	}

	// Drop custom tables.
	foreach ([
		$wpdb->prefix . 'locfinder_location_hours',
		$wpdb->prefix . 'locfinder_location_contacts',
		$wpdb->prefix . 'locfinder_locations',
	] as $table) {
		$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
	}

	// Resolve the auto-created Locations page before its option is deleted.
	$general = get_option('locfinder_general', []);
	$pageId  = is_array($general) ? (int) ($general['locations_page_id'] ?? 0) : 0;

	foreach ([
		'locfinder_general',
		'locfinder_map_display',
		'locfinder_search_filters',
		'locfinder_results',
		'locfinder_data',
		'locfinder_a11y',
		'locfinder_tools',
		'locfinder_geo_cache_gen',
		'locfinder_db_version',
	] as $option) {
		delete_option($option);
	}

	if ($pageId > 0 && get_post_type($pageId) === 'page') {
		wp_delete_post($pageId, true);
	}

	// Delete all plugin transients, which are stored in the options table.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like('_transient_locfinder_') . '%',
			$wpdb->esc_like('_transient_timeout_locfinder_') . '%'
		)
	);
}

if (is_multisite()) {
	// Sites are processed in batches so a large network doesn't load every
	// site object into memory at once.
	$batchSize = 100;
	$offset    = 0;

	do {
		$siteIds = get_sites([
			'fields' => 'ids',
			'number' => $batchSize,
			'offset' => $offset,
		]);

		foreach ($siteIds as $siteId) {
			switch_to_blog((int) $siteId);
			locfinder_uninstall_site();
			restore_current_blog();
		}

		$offset += $batchSize;
	} while (count($siteIds) === $batchSize);
} else {
	locfinder_uninstall_site();
}
