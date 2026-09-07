<?php
/**
 * Registers the Location Finder custom post type and its taxonomies.
 *
 * @package Locfinder
 */

namespace Locfinder\PostTypes;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the registration of the Location Finder
 * custom post type and its taxonomies.
 */
class LocationPostType {

	/**
	 * Registers the Location Finder post type and its taxonomies.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->registerPostType();
		$this->registerTaxonomies();
	}

	/**
	 * Registers the Location Finder post type.
	 *
	 * @return void
	 */
	private function registerPostType(): void {
		$labels = [
			'name'           => __('Locations', 'locfinder'),
			'singular_name'  => __('Location', 'locfinder'),
			'menu_name'      => __('Locations', 'locfinder'),
			'name_admin_bar' => __('Location', 'locfinder'),

			'add_new'      => __('Add New Location', 'locfinder'),
			'add_new_item' => __('Add New Location', 'locfinder'),
			'new_item'     => __('New Location', 'locfinder'),
			'edit_item'    => __('Edit Location', 'locfinder'),
			'view_item'    => __('View Location', 'locfinder'),
			'view_items'   => __('View Locations', 'locfinder'),
			'all_items'    => __('All Locations', 'locfinder'),
			'search_items' => __('Search Locations', 'locfinder'),

			'parent_item_colon' => __('Parent Location:', 'locfinder'),

			'not_found'          => __('No locations found', 'locfinder'),
			'not_found_in_trash' => __('No locations found in Trash', 'locfinder'),

			'archives'   => __('Location Archives', 'locfinder'),
			'attributes' => __('Location Attributes', 'locfinder'),

			'featured_image'        => __('Featured Image', 'locfinder'),
			'set_featured_image'    => __('Set featured image', 'locfinder'),
			'remove_featured_image' => __('Remove featured image', 'locfinder'),
			'use_featured_image'    => __('Use as featured image', 'locfinder'),

			'insert_into_item'      => __('Insert into location', 'locfinder'),
			'uploaded_to_this_item' => __('Uploaded to this location', 'locfinder'),

			'items_list'            => __('Locations list', 'locfinder'),
			'items_list_navigation' => __('Locations list navigation', 'locfinder'),
			'filter_items_list'     => __('Filter locations list', 'locfinder'),

			'item_published'           => __('Location published.', 'locfinder'),
			'item_published_privately' => __('Location published privately.', 'locfinder'),
			'item_reverted_to_draft'   => __('Location reverted to draft.', 'locfinder'),
			'item_scheduled'           => __('Location scheduled.', 'locfinder'),
			'item_updated'             => __('Location updated.', 'locfinder'),

			'item_link'             => __('Location Link', 'locfinder'),
			'item_link_description' => __('A link to a location.', 'locfinder'),
		];

		register_post_type('locfinder_location', [
			'label'               => __('Locations', 'locfinder'),
			'labels'              => $labels,
			'description'         => '',
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'locfinder_general',
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'rest_base'           => 'locations',
			'hierarchical'        => false,
			'rewrite'             => ['slug' => 'locations', 'with_front' => false],
			'supports'            => ['title', 'editor', 'excerpt', 'thumbnail'],
			'has_archive'         => false,
			'query_var'           => true,
			'capability_type'     => 'locfinder_location',
			'map_meta_cap'        => true,
			'exclude_from_search' => false,
			'menu_position'       => 24,
			'menu_icon'           => 'dashicons-location-alt',
		]);
	}

	/**
	 * Registers the Location Finder taxonomies.
	 *
	 * @return void
	 */
	private function registerTaxonomies(): void {
		$taxonomies = [
			'locfinder_category' => [
				'name'          => __('Categories', 'locfinder'),
				'singular_name' => __('Category', 'locfinder'),
				'hierarchical'  => true,
				'rewrite'       => ['slug' => 'location-category', 'with_front' => false],
			]
		];

		foreach ($taxonomies as $slug => $args) {
			$labels = [
				/* translators: %s: taxonomy plural name, e.g. "Categories" */
				'all_items' => sprintf(__('All %s', 'locfinder'), $args['name']),
				/* translators: %s: taxonomy singular name, e.g. "Category" */
				'edit_item' => sprintf(__('Edit %s', 'locfinder'), $args['singular_name']),
				/* translators: %s: taxonomy singular name, e.g. "Category" */
				'view_item' => sprintf(__('View %s', 'locfinder'), $args['singular_name']),
				/* translators: %s: taxonomy singular name, e.g. "Category" */
				'update_item' => sprintf(__('Update %s', 'locfinder'), $args['singular_name']),
				/* translators: %s: taxonomy singular name, e.g. "Category" */
				'add_new_item' => sprintf(__('Add New %s', 'locfinder'), $args['singular_name']),
				/* translators: %s: taxonomy singular name, e.g. "Category" */
				'new_item_name' => sprintf(__('New %s Name', 'locfinder'), $args['singular_name']),
				/* translators: %s: taxonomy plural name, e.g. "Categories" */
				'search_items' => sprintf(__('Search %s', 'locfinder'), $args['name']),
			];

			register_taxonomy($slug, 'locfinder_location', [
				'labels'             => $labels,
				'public'             => true,
				'hierarchical'       => $args['hierarchical'],
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => $slug,
				'show_in_quick_edit' => true,
				'query_var'          => true,
				'rewrite'            => $args['rewrite'],
			]);
		}
	}
}
