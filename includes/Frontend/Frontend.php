<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Frontend;

use Locfinder\Database\LocationRepository;
use Locfinder\Utilities\Helper;
use Locfinder\Utilities\Options;
use Locfinder\Utilities\Scripts;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles the public-facing functionality of the Locfinder plugin.
 */
class Frontend {

	/**
	 * Location data repository.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $repo;

	/**
	 * Initializes the frontend handler.
	 */
	public function __construct() {
		$this->repo = new LocationRepository();
	}

	/**
	 * Renders an admin-only setup notice when the map can't function yet.
	 *
	 * Checks for a configured Google Maps API key and at least one published
	 * location. The notice is shown only to users who can manage settings.
	 *
	 * @return string  Notice HTML, or empty string if nothing to report
	 *                 or the current user can't manage settings.
	 */
	public static function renderSetupNotice(): string {
		static $rendered = false;

		if ($rendered || !current_user_can('manage_options')) {
			return '';
		}

		$issues = [];

		if (Options::getMapEnabled() && Options::getApiKey() === '') {
			$issues[] = [
				'icon'    => 'key',
				'message' => __('Add a Google Maps API key to display the map.', 'locfinder'),
				'url'     => admin_url('admin.php?page=locfinder_general'),
				'label'   => __('Add API key', 'locfinder'),
			];
		}

		if (!Options::hasAnyLocations()) {
			$issues[] = [
				'icon'    => 'location',
				'message' => __('Create your first location to begin displaying results.', 'locfinder'),
				'url'     => admin_url('post-new.php?post_type=locfinder_location'),
				'label'   => __('Add location', 'locfinder'),
			];
		}

		if (empty($issues)) {
			return '';
		}

		$rendered = true;

		$noticeStyle = 'margin-top:var(--locfinder-space-5);box-sizing:border-box;width:100%;max-width:64rem;'
			. 'margin-bottom:var(--locfinder-space-5);padding:var(--locfinder-space-5);'
			. 'border:var(--locfinder-border-muted);border-radius:var(--locfinder-radius-xl);'
			. 'background:var(--locfinder-bg);box-shadow:var(--locfinder-shadow-lg);'
			. 'color:var(--locfinder-body);';

		$titleStyle = 'margin:0;color:var(--locfinder-gray-900);'
			. 'font-size:var(--locfinder-font-size-xl);'
			. 'font-weight:var(--locfinder-fw-bold);'
			. 'line-height:var(--locfinder-lh-tight);';

		$visibilityStyle = 'display:inline-flex;margin-top:var(--locfinder-space-2);'
			. 'padding:0.25rem var(--locfinder-space-2);'
			. 'border-radius:var(--locfinder-radius-md);'
			. 'background:color-mix(in srgb,var(--locfinder-primary) 8%,var(--locfinder-white));'
			. 'color:var(--locfinder-primary);'
			. 'font-size:var(--locfinder-font-size-sm);'
			. 'line-height:var(--locfinder-lh-normal);';

		$itemsStyle = 'margin:var(--locfinder-space-4) 0 0;padding:0;list-style:none;';

		$itemStyle = 'display:flex;flex-wrap:wrap;align-items:center;'
			. 'gap:var(--locfinder-space-3);margin:0;'
			. 'padding:var(--locfinder-space-4) 0;';

		$iconStyle = 'display:grid;flex:0 0 var(--locfinder-space-6);'
			. 'width:var(--locfinder-space-6);height:var(--locfinder-space-6);'
			. 'border-radius:var(--locfinder-radius-pill);'
			. 'background:color-mix(in srgb,var(--locfinder-primary) 9%,var(--locfinder-white));'
			. 'color:var(--locfinder-primary);place-items:center;';

		$messageStyle = 'flex:1 1 18rem;'
			. 'font-size:var(--locfinder-font-size-md);'
			. 'line-height:var(--locfinder-lh-normal);';

		$buttonStyle = 'display:inline-flex;align-items:center;justify-content:center;'
			. 'box-sizing:border-box;min-width:10.75rem;'
			. 'min-height:var(--locfinder-field-height);margin-left:auto;'
			. 'padding:var(--locfinder-space-1) var(--locfinder-space-3);'
			. 'border:1px solid var(--locfinder-primary);'
			. 'border-radius:var(--locfinder-radius-md);'
			. 'background:var(--locfinder-primary);'
			. 'box-shadow:var(--locfinder-shadow-sm);'
			. 'color:var(--locfinder-white);'
			. 'font-size:var(--locfinder-font-size-md);'
			. 'font-weight:var(--locfinder-fw-semibold);'
			. 'line-height:var(--locfinder-lh-tight);'
			. 'text-decoration:none;';

		$items = '';

		foreach ($issues as $index => $issue) {
			$rowStyle = $itemStyle;

			if ($index > 0) {
				$rowStyle .= 'border-top:var(--locfinder-border-muted);';
			}

			$items .= sprintf(
				'<li style="%1$s">
					<span style="%2$s" aria-hidden="true">%3$s</span>
					<span style="%4$s">%5$s</span>
					<a href="%6$s" style="%7$s">%8$s</a>
				</li>',
				esc_attr($rowStyle),
				esc_attr($iconStyle),
				self::renderSetupIcon($issue['icon']),
				esc_attr($messageStyle),
				esc_html($issue['message']),
				esc_url($issue['url']),
				esc_attr($buttonStyle),
				esc_html($issue['label'])
			);
		}

		return '<div class="locfinder-setup-notice" role="note" '
			. 'aria-labelledby="locfinder-setup-notice-title" style="'
			. esc_attr($noticeStyle)
			. '">'
			. '<h2 id="locfinder-setup-notice-title" style="'
			. esc_attr($titleStyle)
			. '">'
			. esc_html__('Finish setting up Location Finder', 'locfinder')
			. '</h2>'
			. '<span style="'
			. esc_attr($visibilityStyle)
			. '">'
			. esc_html__('Visible only to administrators', 'locfinder')
			. '</span>'
			. '<ul style="'
			. esc_attr($itemsStyle)
			. '">'
			. $items
			. '</ul>'
			. '</div>';
	}

	/**
	 * Renders a notice when the map is disabled for the current user.
	 *
	 * The notice is shown only to users who can edit other locations.
	 *
	 * @param  string $message  Notice message to display.
	 * @return string           Notice HTML, or empty string if the current user can't edit other locations.
	 */
	public static function renderMapDisabledNotice(string $message): string {
		if (!current_user_can('edit_others_locfinder_locations')) {
			return '';
		}

		$canManageSettings = current_user_can('manage_options');

		ob_start();
		?>
		<div class="locfinder__map locfinder__map--disabled-notice" role="region">
			<div class="locfinder-notice-content">
				<p class="locfinder-notice-title"><?php esc_html_e('Google Maps is turned off', 'locfinder'); ?></p>
				<p class="locfinder-notice-body"><?php echo esc_html($message); ?></p>
				<?php if (!$canManageSettings) : ?>
					<p class="locfinder-notice-body"><?php esc_html_e('Ask your site administrator to check the Enable Google Maps setting if this wasn\'t intentional.', 'locfinder'); ?></p>
				<?php endif; ?>
			</div>
			<?php if ($canManageSettings) : ?>
				<a
					class="locfinder-notice-button"
					href="<?php echo esc_url(admin_url('admin.php?page=locfinder_general')); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php esc_html_e('Map Settings', 'locfinder'); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders an icon for a setup-notice item.
	 *
	 * @param  string $icon  Icon name.
	 * @return string        SVG markup, or an empty string if unknown.
	 */
	private static function renderSetupIcon(string $icon): string {
		$svgStyle = 'display:block;width:var(--locfinder-space-4);'
			. 'height:var(--locfinder-space-4);';

		return match ($icon) {
			'key' => '<svg viewBox="0 0 24 24" fill="none" '
				. 'stroke="currentColor" stroke-width="2.25" '
				. 'stroke-linecap="round" stroke-linejoin="round" '
				. 'focusable="false" style="' . esc_attr($svgStyle) . '">'
				. '<circle cx="15.5" cy="8.5" r="4.5"/>'
				. '<path d="m12.3 11.7-8.8 8.8M6.5 17.5l2 2M9 15l2 2"/>'
				. '</svg>',

			'location' => '<svg viewBox="0 0 24 24" fill="currentColor" '
				. 'focusable="false" style="' . esc_attr($svgStyle) . '">'
				. '<path fill-rule="evenodd" '
				. 'd="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" '
				. 'clip-rule="evenodd"/>'
				. '</svg>',

			default => '',
		};
	}

	/**
	 * Registers and enqueues public-facing assets.
	 *
	 * Single-location assets are loaded on Location posts. The full locator
	 * bundle respects the configured enqueue strategy.
	 *
	 * @return void
	 */
	public function enqueueFiles(): void {
		if (is_admin()) {
			return;
		}

		if (is_singular('locfinder_location')) {
			$this->enqueueSingleLocationAssets();
		}

		$enqueueOn = Options::getEnqueueOn();

		if ($enqueueOn === 'shortcode_only') {
			$postsToCheck = [];

			if (is_singular()) {
				global $post;

				if ($post instanceof \WP_Post) {
					$postsToCheck[] = $post;
				}
			} else {
				global $wp_query;

				if (!empty($wp_query->posts)) {
					$postsToCheck = $wp_query->posts;
				}
			}

			$found = false;

			foreach ($postsToCheck as $queriedPost) {
				if (!$queriedPost instanceof \WP_Post) {
					continue;
				}

				if (has_shortcode($queriedPost->post_content, 'locfinder') || has_block('locfinder/locfinder-map', $queriedPost)) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				return;
			}
		}

		wp_enqueue_style(
			LOCFINDER_NAME . '-public',
			LOCFINDER_URL . 'build/assets/public/css/public.css',
			[],
			LOCFINDER_VERSION,
			'all'
		);

		wp_enqueue_script(
			LOCFINDER_NAME . '-public',
			LOCFINDER_URL . 'build/assets/public/js/public.js',
			[],
			LOCFINDER_VERSION,
			true
		);

		$this->applyPrimaryColorOverride(LOCFINDER_NAME . '-public');

		wp_localize_script(
			LOCFINDER_NAME . '-public',
			'locfinderConfig',
			array_merge(Options::getMapConfig(), [
				'debugMode' => Options::getDebugMode() && current_user_can('manage_options'),
			])
		);
	}

	/**
	 * Enqueues assets for a single Location page.
	 *
	 * Uses a dedicated bundle containing only the single-location map behavior.
	 *
	 * @return void
	 */
	private function enqueueSingleLocationAssets(): void {
		wp_enqueue_style(
			LOCFINDER_NAME . '-single',
			LOCFINDER_URL . 'build/assets/public/css/single.css',
			[],
			LOCFINDER_VERSION,
			'all'
		);

		wp_enqueue_script(
			LOCFINDER_NAME . '-single',
			LOCFINDER_URL . 'build/assets/public/js/single.js',
			[],
			LOCFINDER_VERSION,
			true
		);

		$this->applyPrimaryColorOverride(LOCFINDER_NAME . '-single');

		wp_localize_script(
			LOCFINDER_NAME . '-single',
			'locfinderSingleConfig',
			array_merge(Options::getMapConfig(), [
				'debugMode' => Options::getDebugMode() && current_user_can('manage_options'),
			])
		);
	}

	/**
	 * Applies the configured primary color to a stylesheet.
	 *
	 * @param  string $handle  Registered stylesheet handle.
	 * @return void
	 */
	private function applyPrimaryColorOverride(string $handle): void {
		$color = Options::getPrimaryColor();

		if (strcasecmp($color, Options::DEFAULT_COLOR) === 0) {
			return;
		}

		wp_add_inline_style($handle, ':root{--locfinder-primary:' . esc_attr($color) . ';}');
	}

	/**
	 * Adds type="module" to Locfinder frontend script tags.
	 *
	 * @param  string $tag     Original script tag.
	 * @param  string $handle  Registered script handle.
	 * @return string          Updated script tag.
	 */
	public function updateScriptTag(string $tag, string $handle): string {
		$handlesToModify = [
			LOCFINDER_NAME . '-public',
			LOCFINDER_NAME . '-single',
		];

		if (!in_array($handle, $handlesToModify, true)) {
			return $tag;
		}

		return Scripts::moduleScriptTag($tag);
	}

	/**
	 * Gets a single contact value by type from a set of contact rows.
	 *
	 * @param  array  $contacts  Contact rows from LocationRepository.
	 * @param  string $type      Contact type to look up ('phone', 'email', 'website').
	 * @return string            The first matching value, or '' if none found.
	 */
	public static function getContactValue(array $contacts, string $type): string {
		foreach ($contacts as $contact) {
			if (($contact['contact_type'] ?? '') === $type) {
				return (string) ($contact['value'] ?? '');
			}
		}

		return '';
	}

	/**
	 * Builds location information for frontend display.
	 *
	 * Accepts pre-fetched contacts, terms, and hours to avoid redundant queries
	 * when rendering multiple locations.
	 *
	 * @param  int    $postId      Location post ID.
	 * @param  string $taxonomy    Taxonomy used for location terms.
	 * @param  array  $contacts    Pre-fetched contacts.
	 * @param  array  $terms       Pre-fetched terms.
	 * @param  array  $hours       Pre-fetched hours.
	 * @param  array  $visibility  Field visibility and link settings.
	 * @return string              Rendered location information.
	 */
	public function getLocationInfo(int $postId, string $taxonomy = 'locfinder_category', array $contacts = [], array $terms = [], array $hours = [], array $visibility = []): string {
		if ($postId <= 0) {
			return '';
		}

		$showAddress         = array_key_exists('address', $visibility) ? (bool) $visibility['address'] : true;
		$showDirections      = array_key_exists('directions', $visibility) ? (bool) $visibility['directions'] : true;
		$showPhone           = array_key_exists('phone', $visibility) ? (bool) $visibility['phone'] : true;
		$showEmail           = array_key_exists('email', $visibility) ? (bool) $visibility['email'] : false;
		$showWebsite         = array_key_exists('website', $visibility) ? (bool) $visibility['website'] : true;
		$showHours           = array_key_exists('hours', $visibility) ? (bool) $visibility['hours'] : false;
		$showCategories      = array_key_exists('categories', $visibility) ? (bool) $visibility['categories'] : true;
		$showViewDetailsLink = array_key_exists('viewDetailsLink', $visibility) ? (bool) $visibility['viewDetailsLink'] : true;
		$linkTarget          = array_key_exists('linkTarget', $visibility) ? (string) $visibility['linkTarget'] : Options::getResultLinkTarget();

		$output = '';
		$row    = $this->repo->getLocationByPostId($postId);
		$lat    = null;
		$lng    = null;

		if ($row) {
			$address = Helper::formatAddress($row);
			$lat     = is_numeric($row['latitude'])  ? (float) $row['latitude']  : null;
			$lng     = is_numeric($row['longitude']) ? (float) $row['longitude'] : null;

			if ($address && $showAddress) {
				$output .= '<div class="locfinder-location__address">' . esc_html($address) . '</div>';
			}
		}

		if (empty($contacts)) {
			$locationId = $this->repo->getIdByPostId($postId);
			$contacts   = $locationId ? $this->repo->getContactsByLocationId($locationId) : [];
		}

		$rows = self::renderContactRows($postId, $contacts, $showPhone, $showEmail, $showWebsite);
		$output .= $rows['phone'] . $rows['email'] . $rows['website'];

		$directionsHtml  = $showDirections ? self::renderDirectionsHtml($lat, $lng) : '';
		$viewDetailsHtml = $showViewDetailsLink
			? '<div class="locfinder-location__view-details"><a href="' . esc_url(get_permalink($postId)) . '"' . ($linkTarget === 'new' ? ' target="_blank" rel="noopener"' : '') . '>' . esc_html__('View Details', 'locfinder') . ($linkTarget === 'new' ? self::renderNewTabSrText() : '') . '</a></div>'
			: '';

		if ($directionsHtml || $viewDetailsHtml) {
			$output .= '<div class="locfinder-result__actions">' . $directionsHtml . $viewDetailsHtml . '</div>';
		}

		if (empty($hours)) {
			$hoursLocationId = $this->repo->getIdByPostId($postId);
			$hours           = $hoursLocationId ? $this->repo->getHoursByLocationId($hoursLocationId) : [];
		}

		if (!empty($hours) && $showHours) {
			$output .= self::renderHoursTable($hours);
		}

		if ($taxonomy && $showCategories) {
			$postTerms = !empty($terms) ? $terms : get_the_terms($postId, $taxonomy);

			if ($postTerms && !is_wp_error($postTerms)) {
				$output .= '<ul class="locfinder-location__categories">';
				foreach ($postTerms as $term) {
					$output .= '<li>' . esc_html($term->name) . '</li>';
				}
				$output .= '</ul>';
			}
		}

		return $output;
	}

	/**
	 * Renders contact information by type.
	 *
	 * Returns each contact row separately for flexible placement by callers.
	 *
	 * @param  int   $postId       Location post ID.
	 * @param  array $contacts     Contact rows.
	 * @param  bool  $showPhone    Whether to render the phone row.
	 * @param  bool  $showEmail    Whether to render the email row.
	 * @param  bool  $showWebsite  Whether to render the website row.
	 * @return array{phone:string,email:string,website:string}
	 */
	private static function renderContactRows(int $postId, array $contacts, bool $showPhone, bool $showEmail, bool $showWebsite): array {
		$rows = ['phone' => '', 'email' => '', 'website' => ''];

		foreach ($contacts as $contact) {
			$val  = sanitize_text_field($contact['value'] ?? '');
			$type = sanitize_key($contact['contact_type'] ?? '');

			if ($val === '') {
				continue;
			}

			switch ($type) {
				case 'phone':
					if ($showPhone) {
						$tel           = Helper::formatPhoneForTel($val);
						$rows['phone'] = '<div class="locfinder-location__phone">'
							. Helper::getIconSvg('phone')
							. ($tel !== ''
								? '<a href="tel:' . esc_attr($tel) . '">' . esc_html($val) . '</a>'
								: esc_html($val))
							. '</div>';
					}
					break;
				case 'email':
					if ($showEmail) {
						$emailSafe = sanitize_email($val);

						if ($emailSafe !== '') {
							$rows['email'] = '<div class="locfinder-location__email">'
								. Helper::getIconSvg('email')
								. '<a href="mailto:' . Helper::obfuscateForHtml($emailSafe) . '">'
								. esc_html__('Send Email', 'locfinder')
								. '</a></div>';
						}
					}
					break;
				case 'website':
					if ($showWebsite) {
						$ariaLabel = sprintf(
							/* translators: %s: location name */
							__('View site for %s (opens in a new tab)', 'locfinder'),
							get_the_title($postId)
						);
						$rows['website'] = '<div class="locfinder-location__website">'
							. Helper::getIconSvg('website')
							. '<a href="' . esc_url($val) . '" target="_blank" rel="noopener" aria-label="' . esc_attr($ariaLabel) . '">' . esc_html__('Visit Website', 'locfinder') . '</a></div>';
					}
					break;
			}
		}

		return $rows;
	}

	/**
	 * Builds a screen-reader-only "opens in a new tab" span for links that open in a new browser tab.
	 *
	 * @return string HTML span markup.
	 */
	private static function renderNewTabSrText(): string {
		return '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'locfinder') . '</span>';
	}

	/**
	 * Renders a Google Maps directions link.
	 *
	 * @param  float|null $lat  Latitude.
	 * @param  float|null $lng  Longitude.
	 * @return string           Directions markup, or an empty string if unavailable.
	 */
	private static function renderDirectionsHtml(?float $lat, ?float $lng): string {
		if ($lat === null || $lng === null) {
			return '';
		}

		$destination = urlencode($lat . ',' . $lng);
		$url         = 'https://www.google.com/maps/dir/?api=1&destination=' . $destination;

		return '<div class="locfinder-location__directions">'
			. '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">'
			. Helper::getIconSvg('directions')
			. esc_html__('Get Directions', 'locfinder')
			. self::renderNewTabSrText()
			. '</a></div>';
	}

	/**
	 * Builds display-ready content for a marker info window.
	 *
	 * Uses pre-fetched contacts and hours when available and loads missing data
	 * from the repository.
	 *
	 * @param  int   $postId      Location post ID.
	 * @param  array $contacts    Pre-fetched contacts.
	 * @param  array $hours       Pre-fetched hours.
	 * @param  array $visibility  Field visibility settings.
	 * @return array{address:string,openBadgeHtml:string,phoneHtml:string,websiteHtml:string,directionsHtml:string}
	 */
	public function getLocationDetailParts(int $postId, array $contacts, array $hours, array $visibility = []): array {
		$empty = [
			'address'        => '',
			'openBadgeHtml'  => '',
			'phoneHtml'      => '',
			'websiteHtml'    => '',
			'directionsHtml' => '',
		];

		if ($postId <= 0) {
			return $empty;
		}

		$showAddress    = array_key_exists('address', $visibility) ? (bool) $visibility['address'] : true;
		$showDirections = array_key_exists('directions', $visibility) ? (bool) $visibility['directions'] : true;
		$showPhone      = array_key_exists('phone', $visibility) ? (bool) $visibility['phone'] : true;
		$showWebsite    = array_key_exists('website', $visibility) ? (bool) $visibility['website'] : true;
		$showOpenBadge  = array_key_exists('openBadge', $visibility) ? (bool) $visibility['openBadge'] : Options::getShowOpenNowBadge();

		$row     = $this->repo->getLocationByPostId($postId);
		$address = ($showAddress && $row) ? Helper::formatAddress($row) : '';

		$directionsHtml = '';

		if ($showDirections && $row) {
			$lat            = is_numeric($row['latitude'])  ? (float) $row['latitude']  : null;
			$lng            = is_numeric($row['longitude']) ? (float) $row['longitude'] : null;
			$directionsHtml = self::renderDirectionsHtml($lat, $lng);
		}

		if (empty($contacts)) {
			$locationId = $this->repo->getIdByPostId($postId);
			$contacts   = $locationId ? $this->repo->getContactsByLocationId($locationId) : [];
		}

		// Email is intentionally excluded from marker popups.
		$contactRows = self::renderContactRows(
			$postId,
			$contacts,
			$showPhone,
			false,
			$showWebsite
		);

		if (empty($hours)) {
			$hoursLocationId = $this->repo->getIdByPostId($postId);
			$hours           = $hoursLocationId ? $this->repo->getHoursByLocationId($hoursLocationId) : [];
		}

		$openBadgeHtml = ($showOpenBadge && !empty($hours)) ? self::renderOpenNowBadge($hours) : '';

		return [
			'address'        => $address,
			'openBadgeHtml'  => $openBadgeHtml,
			'phoneHtml'      => $contactRows['phone'],
			'websiteHtml'    => $contactRows['website'],
			'directionsHtml' => $directionsHtml,
		];
	}

	/**
	 * Renders the Open Now/Closed Now badge.
	 *
	 * Filtered through the `locfinder/pro/open_now_badge` filter.
	 *
	 * @param  array $hours  Location hours.
	 * @return string        Badge HTML, or an empty string.
	 */
	public static function renderOpenNowBadge(array $hours): string {
		return (string) apply_filters('locfinder/pro/open_now_badge', '', $hours);
	}

	/**
	 * Renders configured weekly hours as an HTML table.
	 *
	 * @param  array $hours  Location hours.
	 * @return string        Hours table, or an empty string if no hours are configured.
	 */
	public static function renderHoursTable(array $hours): string {
		if (empty($hours)) {
			return '';
		}

		$byDay            = array_column($hours, null, 'day_of_week');
		$hasConfiguredDay = false;

		foreach ($byDay as $row) {
			if (!empty($row['is_closed']) || !empty($row['is_24_hours']) || (!empty($row['open_time']) && !empty($row['close_time']))) {
				$hasConfiguredDay = true;
				break;
			}
		}

		if (!$hasConfiguredDay) {
			return '';
		}

		$rows = '';

		foreach (Helper::getWeekdayLabels() as $dayNum => $dayLabel) {
			$row = $byDay[$dayNum] ?? null;

			if (!$row) {
				continue;
			}

			if (!empty($row['is_closed'])) {
				$timeText = __('Closed', 'locfinder');
			} elseif (!empty($row['is_24_hours'])) {
				$timeText = __('Open 24 Hours', 'locfinder');
			} elseif (!empty($row['open_time']) && !empty($row['close_time'])) {
				$timeText = sprintf(
					/* translators: 1: opening time, 2: closing time */
					__('%1$s to %2$s', 'locfinder'),
					Helper::formatTime12Hour($row['open_time']),
					Helper::formatTime12Hour($row['close_time'])
				);
			} else {
				$timeText = __('Closed', 'locfinder');
			}

			$rows .= '<tr class="locfinder-location__hours-row">'
				. '<th scope="row">' . esc_html($dayLabel) . '</th>'
				. '<td>' . esc_html($timeText) . '</td>'
				. '</tr>';
		}

		return '<table class="locfinder-location__hours"><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Resolves the single Location template.
	 *
	 * Classic themes can override the plugin template from the active theme.
	 * Block themes use WordPress block-template resolution instead.
	 *
	 * @param  string $template  Current template path.
	 * @return string            Resolved template path.
	 */
	public function loadTemplate(string $template): string {
		if (is_singular('locfinder_location')) {
			// Let block themes use WordPress block-template resolution.
			if (wp_is_block_theme() && function_exists('register_block_template')) {
				return $template;
			}

			$themeFiles    = ['single-locfinder_location.php', 'locfinder/single-locfinder_location.php'];
			$themeTemplate = locate_template($themeFiles, false);
			if ($themeTemplate) {
				return $themeTemplate;
			}

			$pluginTemplate = LOCFINDER_DIR . 'templates/single-locfinder_location.php';
			if (file_exists($pluginTemplate)) {
				return $pluginTemplate;
			}
		}

		return $template;
	}

	/**
	 * Renders the body of a single Location page.
	 *
	 * Shared by the classic and block-theme templates to keep their output in sync.
	 * Establishes and restores global post data when rendering outside the loop.
	 *
	 * @return string Rendered article markup, or an empty string if unavailable.
	 */
	public function renderSingleLocationBody(): string {
		$post_id = get_the_ID();
		if (!$post_id) {
			$queried = get_queried_object();
			$post_id = ($queried instanceof \WP_Post) ? $queried->ID : 0;
		}

		if (!$post_id) {
			return '';
		}

		global $post;
		$locfinder_originalPost = $post;
		$post                   = get_post($post_id);

		if (!$post instanceof \WP_Post) {
			$post = $locfinder_originalPost;
			return '';
		}

		setup_postdata($post);

		$locfinder_location   = $this->repo->getLocationByPostId($post_id);
		$locfinder_locationId = isset($locfinder_location['id']) ? (int) $locfinder_location['id'] : null;
		$locfinder_hours      = $locfinder_locationId ? $this->repo->getHoursByLocationId($locfinder_locationId) : [];
		$locfinder_lat        = ($locfinder_location && is_numeric($locfinder_location['latitude'] ?? null)) ? (float) $locfinder_location['latitude'] : null;
		$locfinder_lng        = ($locfinder_location && is_numeric($locfinder_location['longitude'] ?? null)) ? (float) $locfinder_location['longitude'] : null;
		$locfinder_address    = $locfinder_location ? Helper::formatAddress($locfinder_location) : '';
		$locfinder_hasImage   = has_post_thumbnail($post_id);

		$locfinder_infoHtml = $this->getLocationInfo($post_id, 'locfinder_category', [], [], $locfinder_hours, [
			'address'         => false,
			'email'           => true,
			'hours'           => true,
			'viewDetailsLink' => false,
		]);

		// Single-location map configuration.
		$locfinder_config = [
			'postId'    => $post_id,
			'postTitle' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			'lat'       => $locfinder_lat,
			'lng'       => $locfinder_lng,
			'pinColor'  => Options::getPinColor(),
		];

		ob_start();
		?>

		<article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class('locfinder-single__article'); ?>>

			<div class="locfinder-single__layout<?php echo $locfinder_hasImage ? ' locfinder-single__layout--has-thumbnail' : ''; ?>">

				<?php if ($locfinder_hasImage) : ?>
					<div class="locfinder-single__thumbnail">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WordPress core.
							echo get_the_post_thumbnail(
								$post_id,
								'medium_large',
								['loading' => 'eager']
							);
						?>
					</div>
				<?php endif; ?>

				<div class="locfinder-single__main">
					<header class="entry-header locfinder-single__header">
						<div class="locfinder-result__title-row">
							<h1 class="entry-title locfinder-single__title"><?php the_title(); ?></h1>
							<?php echo wp_kses_post(Frontend::renderOpenNowBadge($locfinder_hours)); ?>
						</div>
						<?php if (!empty($locfinder_address)) : ?>
							<p class="locfinder-single__address"><?php echo esc_html($locfinder_address); ?></p>
						<?php endif; ?>
					</header>

					<div class="entry-content locfinder-single__content">
						<?php
						the_content();
						?>

						<section class="locfinder-single__details" aria-label="<?php echo esc_attr__('Location details', 'locfinder'); ?>">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped within the rendering methods.
							echo $locfinder_infoHtml;
							?>
						</section>
					</div>
				</div>

			</div>

			<?php if (Options::getMapEnabled()) : ?>
				<section class="locfinder-single__map-section" aria-label="<?php echo esc_attr__('Map', 'locfinder'); ?>">
					<script type="application/json" class="locfinder-config">
						<?php echo wp_json_encode($locfinder_config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>
					</script>

					<div class="locfinder-single__map-wrap">
						<div
							class="locfinder__map"
							data-locfinder-single
							role="region"
							<?php /* translators: %s: location name */ ?>
							aria-label="<?php echo esc_attr(sprintf(__('Map for %s', 'locfinder'), html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); ?>"
						></div>

						<p class="locfinder-single__map-loading" data-locfinder-single-loading>
							<?php echo esc_html__('Loading map…', 'locfinder'); ?>
						</p>

						<noscript>
							<p><?php echo esc_html__('JavaScript is required to display the map.', 'locfinder'); ?></p>
						</noscript>
					</div>
				</section>
			<?php else : ?>
				<?php
				$locfinder_disabledNotice = self::renderMapDisabledNotice(
					__('Visitors don\'t see a map on location pages. This note is only visible to editors and admins.', 'locfinder')
				);
				?>
				<?php if ($locfinder_disabledNotice !== '') : ?>
					<section class="locfinder-single__map-section" aria-label="<?php echo esc_attr__('Map', 'locfinder'); ?>">
						<div class="locfinder-single__map-wrap">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped within renderMapDisabledNotice().
							echo $locfinder_disabledNotice;
							?>
						</div>
					</section>
				<?php endif; ?>
			<?php endif; ?>

		</article>

		<?php
		$locfinder_html = (string) ob_get_clean();

		$post = $locfinder_originalPost;
		if ($post) {
			setup_postdata($post);
		} else {
			wp_reset_postdata();
		}

		return $locfinder_html;
	}

	/**
	 * Registers the block-theme template for single Location posts.
	 *
	 * Falls back to the classic template when block-template registration is
	 * unavailable.
	 *
	 * @return void
	 */
	public function registerBlockTemplate(): void {
		if (!function_exists('register_block_template')) {
			return;
		}

		$templateFile = LOCFINDER_DIR . 'templates/block/single-locfinder_location.html';
		if (!file_exists($templateFile)) {
			return;
		}

		register_block_template('locfinder//single-locfinder_location', [
			'title'       => __('Single Location', 'locfinder'),
			'description' => __('Displays a single Location Finder location.', 'locfinder'),
			'content'     => (string) file_get_contents($templateFile),
			'post_types'  => ['locfinder_location'],
		]);
	}
}
