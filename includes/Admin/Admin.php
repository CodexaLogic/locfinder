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
			'dashicons-location-alt',
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
	 * Enqueues Locfinder admin assets where needed.
	 *
	 * @return void
	 */
	public function enqueueFiles(): void {
		$screen = get_current_screen();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen value.
		$page                    = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$isLocfinderSettingsPage = ($page !== '' && str_starts_with($page, 'locfinder_'));
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
	 * Renders a Pro upsell message.
	 *
	 * @param array $args {
	 *     Optional. Display arguments.
	 *
	 *     @type string $message    Plain-text description of the locked feature.
	 *     @type string $url        Upgrade URL. Empty disables the link.
	 *     @type string $badgeLabel Pill text. Default 'Pro'.
	 *     @type string $linkText   Link text before the arrow. Default 'Learn more'.
	 * }
	 * @return void
	 */
	public static function proUpsell(array $args = []): void {
		$defaults = [
			'message'    => __('This feature is available in the Pro version of Locfinder.', 'locfinder'),
			'url'        => defined('LOCFINDER_PRO_URL') ? LOCFINDER_PRO_URL : '',
			'badgeLabel' => __('Pro', 'locfinder'),
			'linkText'   => __('Learn more', 'locfinder'),
		];

		$args = array_merge($defaults, $args);

		$message    = (string) $args['message'];
		$url        = (string) $args['url'];
		$badgeLabel = (string) $args['badgeLabel'];
		$linkText   = (string) $args['linkText'];

		$link = $url !== ''
			? sprintf(
				'<a class="locfinder-pro-note__link" href="%s" target="_blank" rel="noopener noreferrer">%s <span aria-hidden="true">&rarr;</span></a>',
				esc_url($url),
				esc_html($linkText)
			)
			: '';

		$html = sprintf(
			'<div class="locfinder-pro-note"><span class="locfinder-pro-badge"><span class="dashicons dashicons-lock" aria-hidden="true"></span>%s</span><span class="locfinder-pro-note__text">%s</span>%s</div>',
			esc_html($badgeLabel),
			esc_html($message),
			$link
		);

		echo wp_kses($html, [
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
