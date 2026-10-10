<?php
/**
 * Reusable settings layout for Location Finder.
 *
 * Expects variables set by Base::render():
 * - $currentPageSlug     (string)
 * - $pageTitle           (string)
 * - $tabs                (array slug => label)
 * - $settingsGroup       (string)
 * - $hasSaveableSettings (bool)
 *
 * @package Locfinder
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap locfinder-admin">
	<h1 class="screen-reader-text"><?php echo esc_html($pageTitle); ?></h1>

	<div class="locfinder-admin__toolbar">
		<?php if (!empty($tabs) && is_array($tabs)) : ?>
			<nav class="nav-tab-wrapper locfinder-admin__tabs" aria-label="<?php esc_attr_e('Location Finder settings', 'locfinder'); ?>">
				<?php foreach ($tabs as $slug => $label) :
					$active = ($slug === $currentPageSlug);
				?>
					<a class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url(menu_page_url($slug, false)); ?>"
						<?php echo $active ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<?php if ($hasSaveableSettings) : ?>
			<button type="submit" form="locfinder-settings-form" class="button button-primary"><?php esc_html_e('Save changes', 'locfinder'); ?></button>
		<?php endif; ?>
	</div>

	<hr class="wp-header-end">

	<div class="locfinder-admin__content">
		<?php if ($hasSaveableSettings) : ?>
			<form id="locfinder-settings-form" class="locfinder-admin__form" method="post" action="options.php">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag, not a state change.
				if (isset($_GET['settings-updated'])) {
					add_settings_error($settingsGroup, 'locfinder_settings_updated', __('Settings saved.', 'locfinder'), 'success');
				}
				settings_errors($settingsGroup);
				settings_fields($settingsGroup);
				?>
				<div class="locfinder-admin__sections">
					<?php $this->renderSections(); ?>
				</div>
				<div class="locfinder-admin__actions">
					<?php submit_button(__('Save changes', 'locfinder'), 'primary', 'submit', false); ?>
				</div>
			</form>
		<?php else : ?>
			<div class="locfinder-admin__sections">
				<?php $this->renderSections(); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
