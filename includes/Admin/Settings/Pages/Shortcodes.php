<?php
/**
 * Documentation-only page for embedding options.
 *
 * @package Locfinder
 */

namespace Locfinder\Admin\Settings\Pages;

use Locfinder\Admin\Settings\Base;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Documentation-only settings page that renders shortcode reference material.
 */
class Shortcodes extends Base {

	/** @inheritDoc */
	public function getSlug(): string {
		return 'locfinder_shortcodes';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return __('Shortcodes', 'locfinder');
	}

	/**
	 * Skips auto-registration since this page has no fields and saves nothing.
	 *
	 * @return bool  False to prevent Admin::settingsInit() from registering an unused option.
	 */
	public function shouldAutoRegister(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * This page is documentation only and does not render a Save form.
	 * Without this override, options-display.php would submit to an
	 * unregistered settings group and WordPress would reject the request.
	 *
	 * @return bool  Always false; this page never saves anything.
	 */
	public function hasSaveableSettings(): bool {
		return false;
	}

	/** @inheritDoc */
	public function register(): void {
		add_settings_section(
			'locfinder_shortcodes',
			'',
			[$this, 'renderDocumentation'],
			$this->getSlug()
		);
	}

	/**
	 * Renders the shortcode documentation partial.
	 *
	 * This page is purely informational and does not save any settings. It provides
	 * a reference for users on how to use the available shortcodes.
	 *
	 * @return void
	 */
	public function renderDocumentation(): void {
		include LOCFINDER_DIR . 'includes/Admin/views/shortcode-documentation.php';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Since this page has no fields, it always returns an empty array.
	 */
	public function sanitize(array $options, array $existing): array {
		return [];
	}
}
