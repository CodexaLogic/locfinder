<?php
/**
 * Admin functionality for Locfinder.
 *
 * Registers settings pages, admin assets, menus, and shared admin behavior.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin;

use Locfinder\Admin\Settings\Base;
use Locfinder\Admin\Settings\Pages\Accessibility;
use Locfinder\Admin\Settings\Pages\Data;
use Locfinder\Admin\Settings\Pages\General;
use Locfinder\Admin\Settings\Pages\MapDisplay;
use Locfinder\Admin\Settings\Pages\Results;
use Locfinder\Admin\Settings\Pages\SearchFilters;
use Locfinder\Admin\Settings\Pages\Shortcodes;
use Locfinder\Admin\Settings\Pages\Tools;
use Locfinder\Utilities\Options;
use Locfinder\Utilities\Scripts;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Admin bootstrap for Locfinder.
 */
class Admin {

	/**
	 * Ordered settings pages.
	 *
	 * @var array<int,Base>
	 */
	private array $pages = [];

	/**
	 * Ensures the settings_pages filter runs exactly once.
	 *
	 * @var bool
	 */
	private bool $pagesFiltered = false;

	/**
	 * Initializes the admin handler.
	 */
	public function __construct() {
		$this->registerPages();
	}

	/**
	 * Registers settings pages in display order.
	 *
	 * @return void
	 */
	private function registerPages(): void {
		$this->pages = [
			new General(),
			new MapDisplay(),
			new SearchFilters(),
			new Results(),
			new Data(),
			new Accessibility(),
			new Shortcodes(),
			new Tools(),
		];
	}

	/**
	 * Gets the registered settings pages.
	 *
	 * @return array<int,Base> Ordered settings pages.
	 */
	private function getPages(): array {
		if (!$this->pagesFiltered) {
			$this->pages = array_values(array_filter(
				(array) apply_filters('locfinder/settings_pages', $this->pages),
				static fn ($page) => $page instanceof Base
			));

			$this->pagesFiltered = true;
		}

		return $this->pages;
	}

	/**
	 * Adds the top-level admin menu and submenus for each settings page.
	 *
	 * @return void
	 */
	public function addAdminMenu(): void {
		$pages = $this->getPages();

		if (empty($pages)) {
			return;
		}

		$first     = $pages[0];
		$parent    = $first->getSlug();
		$postType  = 'locfinder_location';
		$pageCount = count($pages);

		add_menu_page(
			__('Location Finder', 'locfinder'),
			__('Location Finder', 'locfinder'),
			$first->getCapability(),
			$first->getSlug(),
			[$this, 'renderRouter'],
			$this->getMenuIcon(),
			80
		);

		add_submenu_page(
			$first->getSlug(),
			$first->getTitle(),
			$first->getTitle(),
			$first->getCapability(),
			$first->getSlug(),
			[$this, 'renderRouter']
		);

		for ($i = 1; $i < $pageCount; $i++) {
			$page = $pages[$i];
			add_submenu_page(
				$first->getSlug(),
				$page->getTitle(),
				$page->getTitle(),
				$page->getCapability(),
				$page->getSlug(),
				[$this, 'renderRouter']
			);
		}

		/**
		 * Filters which taxonomies get their own submenu link under Location Finder.
		 *
		 * Free only ever filters locations by the built-in Category taxonomy, so
		 * this returns just that by default. The Pro add-on hooks in to add a
		 * submenu link for every additional taxonomy it lets you filter by.
		 *
		 * @param string[] $taxonomies Existing taxonomy slugs.
		 */
		$taxonomies = (array) apply_filters('locfinder/taxonomy_submenus', ['locfinder_category']);
		$taxonomies = array_values(array_unique(array_filter(array_map('sanitize_key', $taxonomies))));
		$taxSlugs   = [];

		foreach ($taxonomies as $taxonomy) {
			$taxObj = get_taxonomy($taxonomy);
			if (!$taxObj) {
				continue;
			}

			$cap      = $taxObj->cap->manage_terms ?? 'manage_categories';
			$menuText = $taxObj->labels->menu_name ?? ($taxObj->labels->name ?? ucfirst(str_replace('_', ' ', $taxonomy)));
			$slug     = 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $postType;

			$taxSlugs[$taxonomy] = $slug;

			add_submenu_page($parent, $menuText, $menuText, $cap, $slug, null);
		}

		// Keep the Location Finder menu active on CPT and taxonomy screens.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin menu state.
		add_filter('parent_file', function ($parentFile) use ($parent, $postType, $taxonomies) {
			if (
				isset($_GET['post_type']) &&
				sanitize_key(wp_unslash($_GET['post_type'])) === $postType
			) {
				return $parent;
			}

			if (
				isset($_GET['taxonomy'], $_GET['post_type']) &&
				sanitize_key(wp_unslash($_GET['post_type'])) === $postType &&
				in_array(
					sanitize_key(wp_unslash($_GET['taxonomy'])),
					$taxonomies,
					true
				)
			) {
				return $parent;
			}

			return $parentFile;
		});

		// Highlight the active CPT or taxonomy submenu.
		add_filter('submenu_file', function ($submenuFile) use ($postType, $taxSlugs) {
			if (
				isset($_GET['post_type']) &&
				sanitize_key(wp_unslash($_GET['post_type'])) === $postType &&
				empty($_GET['taxonomy'])
			) {
				return 'edit.php?post_type=' . $postType;
			}

			if (
				isset($_GET['taxonomy'], $_GET['post_type']) &&
				sanitize_key(wp_unslash($_GET['post_type'])) === $postType
			) {
				$tax = sanitize_key(wp_unslash($_GET['taxonomy']));

				if (isset($taxSlugs[$tax])) {
					return $taxSlugs[$tax];
				}
			}

			return $submenuFile;
		});
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Gets the admin menu icon as an SVG data URI.
	 *
	 * WordPress recolors the icon to match the admin color scheme.
	 *
	 * @return string  Base64-encoded SVG data URI.
	 */
	private function getMenuIcon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="16.5 31 236 236"><path fill="black" d="M118.245,49.069a81.1,81.1,0,1,0,39.387,156.35l39.349,39.349A13.516,13.516,0,0,0,216.1,225.653l-51.212-51.212a3.862,3.862,0,0,0-4.914-.454,54.065,54.065,0,1,1-40.485-97.739,3.862,3.862,0,0,0,3.154-3.8V52.894a3.862,3.862,0,0,0-4.393-3.825ZM140.547,49.069a81.1,81.1,0,0,1,47.281,24.091,3.862,3.862,0,0,1-.513,5.8l-15.822,11.5a3.862,3.862,0,0,1-4.925-.32,54.065,54.065,0,0,0-27.26-13.89,3.862,3.862,0,0,1-3.154-3.8V52.894a3.862,3.862,0,0,1,4.393-3.825ZM200.936,91.2a81.1,81.1,0,0,1,9.1,46.807,3.862,3.862,0,0,1-4.774,3.337l-18.976-4.731a3.862,3.862,0,0,1-2.921-3.979,54.065,54.065,0,0,0-5.144-26.461,3.862,3.862,0,0,1,1.218-4.783l15.822-11.5a3.862,3.862,0,0,1,5.677,1.305ZM204.639,159.648a81.1,81.1,0,0,1-10.559,18.662,3.862,3.862,0,0,1-5.811.4l-13.829-13.829a3.862,3.862,0,0,1-.454-4.914,54.067,54.067,0,0,0,4.581-8.1,3.862,3.862,0,0,1,4.447-2.142l18.976,4.731a3.862,3.862,0,0,1,2.649,5.188Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	/**
	 * Registers settings and their page-specific sanitizers.
	 *
	 * @return void
	 */
	public function settingsInit(): void {
		foreach ($this->getPages() as $page) {
			if ($page->shouldAutoRegister()) {
				register_setting($page->getSettingsGroup(), $page->getOptionName(), [
					'sanitize_callback' => $this->makeSanitizeCallback($page),
					'type'              => 'array',
					'show_in_rest'      => false,
					'default'           => [],
				]);
			}

			$page->register();
		}
	}

	/**
	 * Builds a sanitize callback for a settings page.
	 *
	 * Supplies both submitted and existing values to the page sanitizer.
	 *
	 * @param  Base $page  Settings page.
	 * @return callable    Sanitization callback.
	 */
	private function makeSanitizeCallback(Base $page): callable {
		return static function ($input) use ($page) {
			$input    = is_array($input) ? $input : [];
			$existing = get_option($page->getOptionName(), []);
			$existing = is_array($existing) ? $existing : [];

			$sanitized = $page->sanitize($input, $existing);

			Options::flush();

			return $sanitized;
		};
	}

	/**
	 * Renders the settings page matching the current admin request.
	 *
	 * @return void
	 */
	public function renderRouter(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing value.
		$slug  = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$pages = $this->getPages();

		foreach ($pages as $page) {
			if ($slug === $page->getSlug()) {
				$page->render();
				return;
			}
		}

		if (!empty($pages)) {
			$pages[0]->render();
		}
	}

	/**
	 * Gets the slug of the Location Finder admin page being viewed.
	 *
	 * @return string  Page slug, e.g. "locfinder_general", or an empty string on other screens.
	 */
	private function getCurrentPage(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen value.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

		return str_starts_with($page, 'locfinder_') ? $page : '';
	}

	/**
	 * Adds a body class to Location Finder settings and Setup pages.
	 *
	 * @param  string $classes  Space-separated admin body classes.
	 * @return string           Classes with "locfinder-settings-screen" added on Location Finder pages.
	 */
	public function addBodyClass(string $classes): string {
		return $this->getCurrentPage() !== '' ? $classes . ' locfinder-settings-screen' : $classes;
	}

	/**
	 * Prints the top bar on Location Finder settings and Setup pages, above all admin notices.
	 *
	 * @return void
	 */
	public function renderTopbar(): void {
		if ($this->getCurrentPage() === '') {
			return;
		}

		require LOCFINDER_DIR . 'includes/Admin/views/partials/topbar.php';
	}

	/**
	 * Enqueues Locfinder admin assets where needed.
	 *
	 * @return void
	 */
	public function enqueueFiles(): void {
		$screen                  = get_current_screen();
		$page                    = $this->getCurrentPage();
		$isLocfinderSettingsPage = $page !== '';
		$isLocationEditor        = ($screen && $screen->base === 'post' && $screen->post_type === 'locfinder_location');

		if (!$isLocfinderSettingsPage && !$isLocationEditor) {
			return;
		}

		wp_enqueue_style(
			LOCFINDER_NAME . '-admin',
			LOCFINDER_URL . 'build/assets/admin/css/admin.css',
			[],
			LOCFINDER_VERSION,
			'all'
		);

		wp_enqueue_script(
			LOCFINDER_NAME . '-admin',
			LOCFINDER_URL . 'build/assets/admin/js/admin.js',
			[],
			LOCFINDER_VERSION,
			true
		);

		if ($page === 'locfinder_tools') {
			wp_enqueue_script(
				LOCFINDER_NAME . '-clear-cache',
				LOCFINDER_URL . 'build/assets/admin/js/clear-cache.js',
				[],
				LOCFINDER_VERSION,
				true
			);

			wp_localize_script(LOCFINDER_NAME . '-clear-cache', 'locfinderClearCache', [
				'translations' => [
					'clearing'      => __('Clearing cache…', 'locfinder'),
					'success'       => __('Cache cleared.', 'locfinder'),
					'error'         => __('An error occurred.', 'locfinder'),
					'requestFailed' => __('Request failed. Check your connection.', 'locfinder'),
				],
			]);
		}

		wp_localize_script(LOCFINDER_NAME . '-admin', 'locfinderConfig', array_merge(
			Options::getMapConfig(),
			[
				'mediaField' => [
					'frameTitle'  => __('Select Icon', 'locfinder'),
					'frameButton' => __('Use this icon', 'locfinder'),
					'noIconSet'   => __('No icon set.', 'locfinder'),
				],
			]
		));
	}

	/**
	 * Adds Locfinder settings pages to the tab list.
	 *
	 * @param  array $tabs  Existing tabs.
	 * @return array        Updated tabs.
	 */
	public function filterSettingsTabs(array $tabs): array {
		$out = [];
		foreach ($this->getPages() as $page) {
			$out[$page->getSlug()] = $page->getTitle();
		}
		return array_merge($out, $tabs);
	}

	/**
	 * Adds type="module" to Locfinder admin script tags.
	 *
	 * @param  string $tag     Original script tag.
	 * @param  string $handle  Registered script handle.
	 * @return string          Updated script tag.
	 */
	public function updateScriptTag(string $tag, string $handle): string {
		$handlesToModify = [
			LOCFINDER_NAME . '-admin',
			LOCFINDER_NAME . '-clear-cache',
		];

		if (!in_array($handle, $handlesToModify, true)) {
			return $tag;
		}

		return Scripts::moduleScriptTag($tag);
	}

	/**
	 * Renders a Pro upsell for a locked feature.
	 *
	 * Shows an optional description, then the Pro badge, a short title, and a
	 * "Learn more" link on one line.
	 *
	 * @param array $args {
	 *     Optional. Display arguments.
	 *
	 *     @type string $title       Short phrase next to the badge, e.g. "Style your map".
	 *     @type string $description Longer explanation of the feature, shown above the badge. Default empty.
	 *     @type string $url         Upgrade URL. Empty hides the link.
	 *     @type string $badgeLabel  Badge text. Default 'Pro'.
	 *     @type string $linkText    Link text before the arrow. Default 'Learn more'.
	 * }
	 * @return void
	 */
	public static function proUpsell(array $args = []): void {
		$args = array_merge([
			'title'       => __('Available in Pro', 'locfinder'),
			'description' => '',
			'url'         => defined('LOCFINDER_PRO_URL') ? LOCFINDER_PRO_URL : '',
			'badgeLabel'  => __('Pro', 'locfinder'),
			'linkText'    => __('Learn more', 'locfinder'),
		], $args);

		$description = (string) $args['description'];
		$url         = (string) $args['url'];

		$html = $description !== ''
			? sprintf('<p class="description">%s</p>', esc_html($description))
			: '';

		$link = $url !== ''
			? sprintf(
				'<a class="locfinder-pro-note__link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span><span aria-hidden="true">&rarr;</span></a>',
				esc_url($url),
				esc_html((string) $args['linkText']),
				esc_html__('(opens in a new tab)', 'locfinder')
			)
			: '';

		$html .= sprintf(
			'<div class="locfinder-pro-note"><span class="locfinder-pro-badge"><span class="dashicons dashicons-lock" aria-hidden="true"></span>%1$s</span><span class="locfinder-pro-note__text">%2$s</span>%3$s</div>',
			esc_html((string) $args['badgeLabel']),
			esc_html((string) $args['title']),
			$link
		);

		echo wp_kses($html, [
			'p'    => ['class' => true],
			'div'  => ['class' => true],
			'span' => ['class' => true, 'aria-hidden' => true],
			'a'    => [
				'href'   => true,
				'class'  => true,
				'target' => true,
				'rel'    => true,
			],
		]);
	}
}
