<?php
/**
 * First-run setup for new installs.
 *
 * Asks what the directory lists and whether to show a map, then stores the
 * answers in the General settings.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin;

use Locfinder\Utilities\Options;
use Locfinder\Utilities\Sanitizer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * First-run setup for Locfinder.
 *
 * Handles the initial configuration steps for new installations.
 */
class Setup {
	/**
	 * Option that marks setup as pending.
	 *
	 * Kept separate from locfinder_general because General::sanitize() only
	 * copies keys it knows, so a flag stored there could never be cleared.
	 * Autoloaded, so checking it costs no database query.
	 *
	 * @var string
	 */
	public const PENDING_OPTION = 'locfinder_setup_pending';

	/**
	 * Admin page slug.
	 *
	 * Starts with "locfinder_" so Admin::enqueueFiles() loads the admin styles.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'locfinder_setup';

	/**
	 * The admin-post action and nonce action for saving setup.
	 *
	 * @var string
	 */
	public const SAVE_ACTION = 'locfinder_save_setup';

	/**
	 * The admin-post action and nonce action for skipping setup.
	 *
	 * @var string
	 */
	public const SKIP_ACTION = 'locfinder_skip_setup';

	/**
	 * Option holding the General settings, and the slug of their page.
	 *
	 * @var string
	 */
	private const GENERAL = 'locfinder_general';

	/**
	 * Query argument carrying a setup error back to the Setup page.
	 *
	 * @var string
	 */
	private const ERROR_ARG = 'locfinder_setup_error';

	/**
	 * Query argument added once setup is complete.
	 *
	 * @var string
	 */
	private const DONE_ARG = 'locfinder_setup';

	/**
	 * Marks setup as pending. Called on activation for new installs only.
	 *
	 * @return void
	 */
	public static function markPending(): void {
		add_option(self::PENDING_OPTION, 1, '', true);
	}

	/**
	 * Checks whether setup is still pending.
	 *
	 * @return bool  True until setup is completed or skipped.
	 */
	public static function isPending(): bool {
		return (bool) get_option(self::PENDING_OPTION, false);
	}

	/**
	 * Adds the Setup page under the Location Finder menu while setup is pending.
	 *
	 * Runs after Admin::addAdminMenu() so the parent menu exists.
	 *
	 * @return void
	 */
	public function registerPage(): void {
		if (!self::isPending()) {
			return;
		}

		add_submenu_page(
			self::GENERAL,
			__('Set Up Location Finder', 'locfinder'),
			__('Setup', 'locfinder'),
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'render']
		);
	}

	/**
	 * Shows the setup prompt, or the confirmation after setup completes.
	 *
	 * @return void
	 */
	public function renderNotice(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (!self::isPending()) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display state.
			if (isset($_GET[self::DONE_ARG]) && sanitize_key(wp_unslash($_GET[self::DONE_ARG])) === 'done') {
				$this->renderDoneNotice();
			}

			return;
		}

		if (!$this->isBannerScreen()) {
			return;
		}

		$setupUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
		$skipUrl  = self::getSkipUrl();

		require LOCFINDER_DIR . 'includes/Admin/views/partials/setup-banner.php';
	}

	/**
	 * Loads the setup banner styles on screens that show the banner.
	 *
	 * @return void
	 */
	public function enqueueBannerStyles(): void {
		if (!self::isPending() || !current_user_can('manage_options') || !$this->isBannerScreen()) {
			return;
		}

		wp_enqueue_style(
			LOCFINDER_NAME . '-setup-banner',
			LOCFINDER_URL . 'build/assets/admin/css/setup-banner.css',
			[],
			LOCFINDER_VERSION
		);
	}

	/**
	 * Checks whether the current screen shows the setup banner.
	 *
	 * The banner appears on the Dashboard, the Plugins screen, and Location
	 * Finder's own screens, except the Setup page itself.
	 *
	 * @return bool  True if the banner belongs on this screen.
	 */
	private function isBannerScreen(): bool {
		$screen = get_current_screen();

		if (!$screen) {
			return false;
		}

		if (in_array($screen->id, ['dashboard', 'plugins', 'edit-locfinder_location'], true)) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen value.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

		return $page !== self::PAGE_SLUG && str_starts_with($page, 'locfinder_');
	}

	/**
	 * Renders the Setup page.
	 *
	 * @return void
	 */
	public function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$presets    = Options::getItemLabelPresets();
		$current    = Options::getItemLabelKey();
		$customKey  = Options::ITEM_LABEL_CUSTOM;
		$singular   = (string) Options::getOption('item_label_singular', '');
		$plural     = (string) Options::getOption('item_label_plural', '');
		$mapEnabled = Options::getMapEnabled();
		$maxLength  = Options::ITEM_LABEL_MAX_LENGTH;
		$saveAction = self::SAVE_ACTION;
		$skipUrl    = self::getSkipUrl();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display state.
		$error = isset($_GET[self::ERROR_ARG]) ? sanitize_key(wp_unslash($_GET[self::ERROR_ARG])) : '';

		require LOCFINDER_DIR . 'includes/Admin/views/setup.php';
	}

	/**
	 * Saves the Setup answers.
	 *
	 * Saves through update_option() so General::sanitize() validates the
	 * values exactly as the settings page does.
	 *
	 * @return void
	 */
	public function handleSave(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do this.', 'locfinder'), '', ['response' => 403]);
		}

		check_admin_referer(self::SAVE_ACTION);

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Sanitizer::select(), Sanitizer::text(), and Sanitizer::checkbox().
		$itemLabel = Sanitizer::select(
			(string) wp_unslash($_POST['item_label'] ?? ''),
			[...array_keys(Options::getItemLabelPresets()), Options::ITEM_LABEL_CUSTOM],
			Options::ITEM_LABEL_DEFAULT
		);
		$singular  = Sanitizer::text((string) wp_unslash($_POST['item_label_singular'] ?? ''), Options::ITEM_LABEL_MAX_LENGTH);
		$plural    = Sanitizer::text((string) wp_unslash($_POST['item_label_plural'] ?? ''), Options::ITEM_LABEL_MAX_LENGTH);
		$enableMap = Sanitizer::checkbox(wp_unslash($_POST['enable_map'] ?? '1'));
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ($itemLabel === Options::ITEM_LABEL_CUSTOM && ($singular === '' || $plural === '')) {
			wp_safe_redirect(add_query_arg(self::ERROR_ARG, 'incomplete', admin_url('admin.php?page=' . self::PAGE_SLUG)));
			exit;
		}

		$general = get_option(self::GENERAL, []);
		$general = is_array($general) ? $general : [];

		update_option(self::GENERAL, array_merge($general, [
			'item_label'          => $itemLabel,
			'item_label_singular' => $singular,
			'item_label_plural'   => $plural,
			'enable_map'          => $enableMap,
		]));

		Options::flush();
		delete_option(self::PENDING_OPTION);

		$next = Options::getMapEnabled() && Options::getApiKey() === ''
			? admin_url('admin.php?page=' . self::GENERAL)
			: admin_url('edit.php?post_type=locfinder_location');

		wp_safe_redirect(add_query_arg(self::DONE_ARG, 'done', $next));
		exit;
	}

	/**
	 * Skips setup and keeps the defaults.
	 *
	 * @return void
	 */
	public function handleSkip(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to do this.', 'locfinder'), '', ['response' => 403]);
		}

		check_admin_referer(self::SKIP_ACTION);

		delete_option(self::PENDING_OPTION);

		wp_safe_redirect(admin_url('admin.php?page=' . self::GENERAL));
		exit;
	}

	/**
	 * Shows what to do next after setup completes.
	 *
	 * @return void
	 */
	private function renderDoneNotice(): void {
		$next = Options::getMapEnabled() && Options::getApiKey() === ''
			? __('Next, add your Google Maps API key below.', 'locfinder')
			/* translators: %s: singular name chosen in settings, mid-sentence, e.g. "location" or "program" */
			: sprintf(__('Next, add your first %s.', 'locfinder'), Options::getItemLabels()['singularLower']);

		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__('Setup complete.', 'locfinder'),
			esc_html($next)
		);
	}

	/**
	 * Gets the nonce-protected URL that skips setup.
	 *
	 * @return string  Skip URL.
	 */
	private static function getSkipUrl(): string {
		return wp_nonce_url(admin_url('admin-post.php?action=' . self::SKIP_ACTION), self::SKIP_ACTION);
	}
}
