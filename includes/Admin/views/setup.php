<?php
/**
 * First-run setup page.
 *
 * Expects variables set by Setup::render():
 * - $presets    (array)  Preset key => names, from Options::getItemLabelPresets().
 * - $current    (string) Chosen preset key, or the custom key.
 * - $customKey  (string) Value meaning a custom name.
 * - $singular   (string) Saved custom singular name.
 * - $plural     (string) Saved custom plural name.
 * - $mapEnabled (bool)   Whether Google Maps is enabled.
 * - $maxLength  (int)    Maximum custom name length.
 * - $saveAction (string) admin-post action and nonce action for saving.
 * - $skipUrl    (string) Nonce-protected URL that skips setup.
 * - $error      (string) Error from the last attempt, or an empty string.
 *
 * @package Locfinder
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap locfinder-admin">
	<div class="locfinder-admin__page-header">
		<div>
			<h1 class="locfinder-admin__title"><?php esc_html_e('Set Up Location Finder', 'locfinder'); ?></h1>
			<p class="locfinder-admin__subtitle"><?php esc_html_e('Two quick questions. You can change both later under Location Finder → General.', 'locfinder'); ?></p>
		</div>
	</div>

	<hr class="wp-header-end">

	<div class="locfinder-admin__content">
		<?php if ($error === 'incomplete') : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e('To use your own name, enter both the singular and the plural.', 'locfinder'); ?></p>
			</div>
		<?php endif; ?>

		<form class="locfinder-setup locfinder-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr($saveAction); ?>">
			<?php wp_nonce_field($saveAction); ?>

			<fieldset class="locfinder-setup__question">
				<legend><?php esc_html_e('What does your directory list?', 'locfinder'); ?></legend>

				<div class="locfinder-setup__choices">
					<?php foreach ($presets as $key => $names) : ?>
						<label class="locfinder-setup__choice">
							<input type="radio" name="item_label" value="<?php echo esc_attr($key); ?>" <?php checked($current, $key); ?>>
							<?php echo esc_html($names['plural']); ?>
						</label>
					<?php endforeach; ?>
					<label class="locfinder-setup__choice">
						<input id="locfinder-setup-custom" type="radio" name="item_label" value="<?php echo esc_attr($customKey); ?>" <?php checked($current, $customKey); ?>>
						<?php esc_html_e('Other', 'locfinder'); ?>
					</label>
				</div>

				<div class="locfinder-setup__custom">
					<p class="locfinder-setup__field">
						<label for="locfinder-setup-singular"><?php esc_html_e('Singular', 'locfinder'); ?></label>
						<input id="locfinder-setup-singular" type="text" name="item_label_singular" value="<?php echo esc_attr($singular); ?>" maxlength="<?php echo esc_attr((string) $maxLength); ?>" placeholder="<?php /* translators: Example singular name for a custom directory. */ esc_attr_e('Food Bank', 'locfinder'); ?>">
					</p>
					<p class="locfinder-setup__field">
						<label for="locfinder-setup-plural"><?php esc_html_e('Plural', 'locfinder'); ?></label>
						<input id="locfinder-setup-plural" type="text" name="item_label_plural" value="<?php echo esc_attr($plural); ?>" maxlength="<?php echo esc_attr((string) $maxLength); ?>" placeholder="<?php /* translators: Example plural name for a custom directory. */ esc_attr_e('Food Banks', 'locfinder'); ?>">
					</p>
				</div>
			</fieldset>

			<fieldset class="locfinder-setup__question">
				<legend><?php esc_html_e('Do you want to show a map?', 'locfinder'); ?></legend>

				<div class="locfinder-setup__choices locfinder-setup__choices--wide">
					<label class="locfinder-setup__choice">
						<input type="radio" name="enable_map" value="1" <?php checked($mapEnabled); ?>>
						<span>
							<strong><?php esc_html_e('Yes, show a map', 'locfinder'); ?></strong>
							<span class="locfinder-setup__hint"><?php esc_html_e('Adds a map, address search, and distance filters. Needs a Google Maps API key.', 'locfinder'); ?></span>
						</span>
					</label>
					<label class="locfinder-setup__choice">
						<input type="radio" name="enable_map" value="0" <?php checked(!$mapEnabled); ?>>
						<span>
							<strong><?php esc_html_e('No, list only', 'locfinder'); ?></strong>
							<span class="locfinder-setup__hint"><?php esc_html_e('A searchable list with categories. You can turn the map on later.', 'locfinder'); ?></span>
						</span>
					</label>
				</div>
			</fieldset>

			<p class="locfinder-setup__actions">
				<?php submit_button(__('Save and continue', 'locfinder'), 'primary', 'submit', false); ?>
				<a href="<?php echo esc_url($skipUrl); ?>"><?php esc_html_e('Skip setup', 'locfinder'); ?></a>
			</p>
		</form>
	</div>
</div>
