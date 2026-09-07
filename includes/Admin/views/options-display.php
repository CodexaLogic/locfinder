<?php
/**
 * Reusable settings layout for Location Finder.
 *
 * Expects variables set by Base::render():
 * - $currentPageSlug     (string)
 * - $pageTitle           (string)
 * - $version             (string)
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
	<div class="locfinder-admin__header">
		<div class="locfinder-admin__header-inner">
			<h1 class="locfinder-admin__title"><?php echo esc_html($pageTitle); ?></h1>
			<?php if (!empty($version)) : ?>
				<?php /* translators: %s: plugin version number, e.g. "1.0.0" */ ?>
				<span class="locfinder-admin__version"><?php echo esc_html(sprintf(__('Version: %s', 'locfinder'), $version)); ?></span>
			<?php endif; ?>
		</div>

		<?php if (!empty($tabs) && is_array($tabs)) : ?>
			<nav class="nav-tab-wrapper locfinder-admin__tabs" aria-label="<?php echo esc_attr__('Locfinder settings tabs', 'locfinder'); ?>">
				<?php foreach ($tabs as $slug => $label) :
					$url    = menu_page_url($slug, false);
					$active = ($slug === $currentPageSlug);
				?>
					<a class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url($url); ?>"
						aria-current="<?php echo $active ? 'page' : 'false'; ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>

	<div class="locfinder-admin__content">
		<?php if ($hasSaveableSettings) : ?>
			<form class="locfinder-admin__form" method="post" action="options.php">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag, not a state change.
				if (isset($_GET['settings-updated'])) {
					add_settings_error($settingsGroup, 'locfinder_settings_updated', __('Settings saved.', 'locfinder'), 'success');
				}
				settings_errors($settingsGroup);
				settings_fields($settingsGroup);
				do_settings_sections($currentPageSlug);
				submit_button(esc_html__('Save Settings', 'locfinder'));
				?>
			</form>
		<?php else : ?>
			<?php
			do_settings_sections($currentPageSlug);
			?>
		<?php endif; ?>
	</div>
</div>
