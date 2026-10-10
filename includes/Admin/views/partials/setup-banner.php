<?php
/**
 * Sets up the admin setup banner for the Location Finder plugin.
 *
 * Displays a banner in the admin area prompting the user to complete the setup process.
 *
 * Expects variables set by Setup::renderNotice():
 * - $setupUrl (string) Setup page URL.
 * - $skipUrl (string)  Nonce-protected URL to skip the setup process.
 *
 * @package Locfinder
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="notice locfinder-setup-banner">
	<div class="locfinder-setup-banner__content">
		<p class="locfinder-setup-banner__brand">
			<img src="<?php echo esc_url(LOCFINDER_URL . 'assets/images/location-finder-icon.svg'); ?>" width="24" height="28" alt="">
			<?php esc_html_e('Location Finder', 'locfinder'); ?>
		</p>
		<h2 class="locfinder-setup-banner__title"><?php esc_html_e('Complete your setup and bring your directory to life.', 'locfinder'); ?></h2>
		<p class="locfinder-setup-banner__text"><?php esc_html_e('Turn your locations, services, or resources into a searchable directory. Let’s make it yours!', 'locfinder'); ?></p>
		<p class="locfinder-setup-banner__actions">
			<a class="button button-primary locfinder-setup-banner__button" href="<?php echo esc_url($setupUrl); ?>">
				<?php esc_html_e('Get started', 'locfinder'); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
			<a class="locfinder-setup-banner__skip" href="<?php echo esc_url($skipUrl); ?>"><?php esc_html_e('Skip setup', 'locfinder'); ?></a>
		</p>
	</div>
	<img class="locfinder-setup-banner__image" src="<?php echo esc_url(LOCFINDER_URL . 'assets/images/setup-illustration.webp'); ?>" width="440" height="238" alt="">
	<a class="locfinder-setup-banner__dismiss" href="<?php echo esc_url($skipUrl); ?>"><span class="screen-reader-text"><?php esc_html_e('Dismiss', 'locfinder'); ?></span></a>
</div>
