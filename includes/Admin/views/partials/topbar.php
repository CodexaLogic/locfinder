<?php
/**
 * Top bar shown on Location Finder admin pages: logo, plugin name, and version.
 *
 * @package Locfinder
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<header class="locfinder-admin__topbar">
	<img class="locfinder-admin__logo" src="<?php echo esc_url(LOCFINDER_URL . 'assets/images/location-finder-icon.svg'); ?>" width="24" height="28" alt="">
	<span class="locfinder-admin__brand"><?php esc_html_e('Location Finder', 'locfinder'); ?></span>
	<?php if (defined('LOCFINDER_VERSION')) : ?>
		<?php /* translators: %s: plugin version number, e.g. "1.4.0" */ ?>
		<span class="locfinder-admin__version"><?php echo esc_html(sprintf(__('v%s', 'locfinder'), LOCFINDER_VERSION)); ?></span>
	<?php endif; ?>
</header>
