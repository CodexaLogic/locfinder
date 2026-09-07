<?php
/**
 * Single Location fallback template.
 *
 * Themes can override this template by copying it to:
 *
 * /wp-content/themes/theme-name/locfinder/single-locfinder_location.php
 *
 * @package  Locfinder
 * @template single-locfinder_location.php
 * @since    1.0.0
 * @version  1.0.0
 */

use Locfinder\Frontend\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

get_header();

if (!have_posts()) {
	get_footer();
	return;
}

the_post();

$locfinder_frontend = new Frontend();
?>

<main id="primary" class="site-main locfinder-single">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within the rendering methods.
	echo $locfinder_frontend->renderSingleLocationBody();
	?>
</main>

<?php
get_footer();
