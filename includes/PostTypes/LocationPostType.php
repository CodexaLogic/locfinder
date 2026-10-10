<?php
/**
 * Registers the Location Finder custom post type and its taxonomies.
 *
 * @package Locfinder
 */

namespace Locfinder\PostTypes;

use Locfinder\Utilities\Options;

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
	 * Labels are built from the name chosen in settings (Location by
	 * default), so the admin reads "Add Program" on a program directory.
	 *
	 * @return void
	 */
	private function registerPostType(): void {
		$names = Options::getItemLabels();

		$singular      = $names['singular'];
		$plural        = $names['plural'];
		$singularLower = $names['singularLower'];
		$pluralLower   = $names['pluralLower'];

		$labels = [
			'name'           => $plural,
			'singular_name'  => $singular,
			'menu_name'      => $plural,
			'name_admin_bar' => $singular,

			'add_new' => _x('Add', 'post type label', 'locfinder'),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'add_new_item' => sprintf(_x('Add %s', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'new_item' => sprintf(_x('New %s', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'edit_item' => sprintf(_x('Edit %s', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'view_item' => sprintf(_x('View %s', 'post type label', 'locfinder'), $singular),
			/* translators: %s: plural name chosen in settings, e.g. "Locations" or "Programs" */
			'view_items' => sprintf(_x('View %s', 'post type plural label', 'locfinder'), $plural),
			/* translators: %s: plural name chosen in settings, e.g. "Locations" or "Programs" */
			'all_items' => sprintf(_x('All %s', 'post type label', 'locfinder'), $plural),
			/* translators: %s: plural name chosen in settings, e.g. "Locations" or "Programs" */
			'search_items' => sprintf(_x('Search %s', 'post type label', 'locfinder'), $plural),

			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'parent_item_colon' => sprintf(_x('Parent %s:', 'post type label', 'locfinder'), $singular),

			/* translators: %s: plural name chosen in settings, mid-sentence, e.g. "locations" or "programs" */
			'not_found' => sprintf(_x('No %s found.', 'post type label', 'locfinder'), $pluralLower),
			/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "locations" or "programs" */
			'not_found_in_trash' => sprintf(_x('No %s found in Trash.', 'post type label', 'locfinder'), $pluralLower),

			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'archives' => sprintf(_x('%s Archives', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'attributes' => sprintf(_x('%s Attributes', 'post type label', 'locfinder'), $singular),

			'featured_image'        => __('Featured Image', 'locfinder'),
			'set_featured_image'    => __('Set featured image', 'locfinder'),
			'remove_featured_image' => __('Remove featured image', 'locfinder'),
			'use_featured_image'    => __('Use as featured image', 'locfinder'),

			/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "location" or "program" */
			'insert_into_item' => sprintf(_x('Insert into %s', 'post type label', 'locfinder'), $singularLower),
			/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "location" or "program" */
			'uploaded_to_this_item' => sprintf(_x('Uploaded to this %s', 'post type label', 'locfinder'), $singularLower),

			/* translators: %s: plural name chosen in settings, e.g. "Locations" or "Programs" */
			'items_list' => sprintf(_x('%s list', 'post type label', 'locfinder'), $plural),
			/* translators: %s: plural name chosen in settings, e.g. "Locations" or "Programs" */
			'items_list_navigation' => sprintf(_x('%s list navigation', 'post type label', 'locfinder'), $plural),
			/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "locations" or "programs" */
			'filter_items_list' => sprintf(_x('Filter %s list', 'post type label', 'locfinder'), $pluralLower),

			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'item_published' => sprintf(_x('%s published.', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'item_published_privately' => sprintf(_x('%s published privately.', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'item_reverted_to_draft' => sprintf(_x('%s reverted to draft.', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'item_scheduled' => sprintf(_x('%s scheduled.', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'item_updated' => sprintf(_x('%s updated.', 'post type label', 'locfinder'), $singular),

			/* translators: %s: singular name chosen in settings, e.g. "Location" or "Program" */
			'item_link' => sprintf(_x('%s Link', 'post type label', 'locfinder'), $singular),
			/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "location" or "program" */
			'item_link_description' => sprintf(_x('A link to a single %s.', 'post type label', 'locfinder'), $singularLower),
		];

		register_post_type('locfinder_location', [
			'label'               => $plural,
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
				'add_new_item' => sprintf(__('Add %s', 'locfinder'), $args['singular_name']),
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
