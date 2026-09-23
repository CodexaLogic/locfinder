<?php
/**
 * Registers and manages post meta for location posts.
 *
 * @package Locfinder
 */

namespace Locfinder\PostTypes;

use WP_Post;
use Locfinder\Database\LocationRepository;
use Locfinder\Utilities\Helper;
use Locfinder\Utilities\Options;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Manages and registers post meta for the Location Finder custom post type.
 */
class LocationMeta {

	/**
	 * Location repository instance.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new LocationRepository();
	}

	/**
	 * Registers post meta fields for the location CPT.
	 *
	 * @return void
	 */
	public function registerPostMeta(): void {
		register_post_meta('locfinder_location', 'locfinder_phone', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => false,
			'single'            => true,
		]);

		register_post_meta('locfinder_location', 'locfinder_email', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'show_in_rest'      => false,
			'single'            => true,
		]);

		register_post_meta('locfinder_location', 'locfinder_website_url', [
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => false,
			'single'            => true,
		]);
	}

	/**
	 * Registers the location details meta box on the location CPT.
	 *
	 * @return void
	 */
	public function registerMetaBox(): void {
		add_meta_box(
			'location_details',
			__('Location Details', 'locfinder'),
			[$this, 'renderLocationMetaBox'],
			'locfinder_location',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the location meta box.
	 *
	 * @param  WP_Post $post  The current post object.
	 * @return void
	 */
	public function renderLocationMetaBox(WP_Post $post): void {
		wp_nonce_field('locfinder_save_location_meta', 'locfinder_location_meta_nonce');

		$mapEnabled  = Options::getMapEnabled();
		$location    = $this->repo->getLocationByPostId($post->ID);
		$city        = $location['city'] ?? '';
		$state       = $location['state'] ?? '';
		$postalCode  = $location['postal_code'] ?? '';
		$countryCode = $location['country_code'] ?? '';
		$address     = $location['address'] ?? '';
		$address2    = $location['address_2'] ?? '';
		$latitude    = $location['latitude'] ?? '';
		$longitude   = $location['longitude'] ?? '';
		$placeId     = $location['place_id'] ?? '';

		$phone      = get_post_meta($post->ID, 'locfinder_phone', true);
		$email      = get_post_meta($post->ID, 'locfinder_email', true);
		$websiteUrl = get_post_meta($post->ID, 'locfinder_website_url', true);

		$locationId = $this->repo->getIdByPostId($post->ID);
		$hoursRows  = $locationId ? $this->repo->getHoursByLocationId($locationId) : [];
		$hoursByDay = array_column($hoursRows, null, 'day_of_week');
		?>

		<div class="locfinder-meta__field">
			<label for="locfinder_address">
				<strong><?php esc_html_e('Address', 'locfinder'); ?></strong>
			</label>
			<input
				type="text"
				id="locfinder_address"
				name="locfinder_formatted_address"
				value="<?php echo esc_attr($address); ?>"
				class="widefat locfinder-meta__address"
				placeholder="<?php
					echo $mapEnabled
						? esc_attr__('Start typing and select an address in the dropdown.', 'locfinder')
						: esc_attr__('Enter the full street address.', 'locfinder');
				?>"
			/>
			<p class="description">
				<?php
				echo $mapEnabled
					? esc_html__('Powered by Google Places autocomplete. Select a result to set the full address and coordinates.', 'locfinder')
					: esc_html__('Enter the address manually. Turn on Google Maps under Location Finder → General to enable autocomplete and coordinates.', 'locfinder');
				?>
			</p>
		</div>

		<div class="locfinder-meta__field">
			<label for="locfinder_address_2">
				<?php esc_html_e('Suite / Unit / Apt', 'locfinder'); ?>
			</label>
			<input
				type="text"
				id="locfinder_address_2"
				name="locfinder_address_2"
				value="<?php echo esc_attr($address2); ?>"
				class="widefat"
			/>
		</div>

		<input type="hidden" id="locfinder_city" name="locfinder_city" value="<?php echo esc_attr($city); ?>" />
		<input type="hidden" id="locfinder_state" name="locfinder_state" value="<?php echo esc_attr($state); ?>" />
		<input type="hidden" id="locfinder_postal_code" name="locfinder_postal_code" value="<?php echo esc_attr($postalCode); ?>" />
		<input type="hidden" id="locfinder_country_code" name="locfinder_country_code" value="<?php echo esc_attr($countryCode); ?>" />
		<input type="hidden" id="locfinder_latitude" name="locfinder_latitude" value="<?php echo esc_attr($latitude); ?>" />
		<input type="hidden" id="locfinder_longitude" name="locfinder_longitude" value="<?php echo esc_attr($longitude); ?>" />
		<input type="hidden" id="locfinder_place_id" name="locfinder_place_id" value="<?php echo esc_attr($placeId); ?>" />

		<?php if ($mapEnabled) : ?>
			<div id="locfinder-map" class="locfinder-meta__map"></div>
			<p class="description">
				<?php esc_html_e('Choose an autocomplete result to set coordinates. The map preview updates automatically.', 'locfinder'); ?>
			</p>
		<?php endif; ?>

		<div class="locfinder-meta__field">
			<label for="locfinder_phone"><?php esc_html_e('Phone', 'locfinder'); ?></label>
			<input
				type="text"
				id="locfinder_phone"
				name="locfinder_phone"
				value="<?php echo esc_attr($phone); ?>"
				class="widefat"
				placeholder="<?php esc_attr_e('(555) 555-1234 or +44 20 7123 4567', 'locfinder'); ?>"
				aria-describedby="locfinder_phone_desc"
			/>
			<p id="locfinder_phone_desc" class="description">
				<?php esc_html_e('For international numbers, include the country code with a leading +.', 'locfinder'); ?>
			</p>
		</div>

		<div class="locfinder-meta__field">
			<label for="locfinder_email"><?php esc_html_e('Email', 'locfinder'); ?></label>
			<input type="email" id="locfinder_email" name="locfinder_email" value="<?php echo esc_attr($email); ?>" class="widefat" />
		</div>

		<div class="locfinder-meta__field">
			<label for="locfinder_website_url"><?php esc_html_e('Website URL', 'locfinder'); ?></label>
			<input type="url" id="locfinder_website_url" name="locfinder_website_url" value="<?php echo esc_attr($websiteUrl); ?>" class="widefat" />
		</div>

		<div class="locfinder-meta__field">
			<label><strong><?php esc_html_e('Hours', 'locfinder'); ?></strong></label>
			<table class="locfinder-meta__hours-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Day', 'locfinder'); ?></th>
						<th><?php esc_html_e('Closed', 'locfinder'); ?></th>
						<th><?php esc_html_e('Open 24 Hours', 'locfinder'); ?></th>
						<th><?php esc_html_e('Opens', 'locfinder'); ?></th>
						<th><?php esc_html_e('Closes', 'locfinder'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach (Helper::getWeekdayLabels() as $dayNum => $dayLabel) :
						$row      = $hoursByDay[$dayNum] ?? null;
						$isClosed = !empty($row['is_closed']);
						$is24     = !empty($row['is_24_hours']);
						$openVal  = !empty($row['open_time'])  ? substr((string) $row['open_time'], 0, 5)  : '';
						$closeVal = !empty($row['close_time']) ? substr((string) $row['close_time'], 0, 5) : '';
					?>
						<tr>
							<td><?php echo esc_html($dayLabel); ?></td>
							<td>
								<input type="checkbox" class="locfinder-hours-closed" name="locfinder_hours[<?php echo esc_attr($dayNum); ?>][closed]" value="1" <?php checked($isClosed, true); ?> />
							</td>
							<td>
								<input type="checkbox" class="locfinder-hours-is24" name="locfinder_hours[<?php echo esc_attr($dayNum); ?>][is_24]" value="1" <?php checked($is24, true); ?> />
							</td>
							<td>
								<input type="time" class="locfinder-hours-open" name="locfinder_hours[<?php echo esc_attr($dayNum); ?>][open]" value="<?php echo esc_attr($openVal); ?>" <?php disabled($isClosed || $is24, true); ?> />
							</td>
							<td>
								<input type="time" class="locfinder-hours-close" name="locfinder_hours[<?php echo esc_attr($dayNum); ?>][close]" value="<?php echo esc_attr($closeVal); ?>" <?php disabled($isClosed || $is24, true); ?> />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e('Check "Closed" for days the location is not open. Check "Open 24 Hours" to skip setting specific times. Checking either disables the time fields, since whatever they contain is not saved.', 'locfinder'); ?></p>
		</div>
		<?php
	}

	/**
	 * Writes or clears a single contact field in both postmeta and the
	 * contacts table, keeping the two in sync.
	 *
	 * @param  int    $postId       The post ID.
	 * @param  string $metaKey      Post meta key to write or delete.
	 * @param  string $contactType  Contact type slug: 'phone', 'email', 'website'.
	 * @param  string $value        Sanitized value, or '' to clear the field.
	 * @return void
	 */
	private function syncContactField(int $postId, string $metaKey, string $contactType, string $value): void {
		if ($value !== '') {
			update_post_meta($postId, $metaKey, $value);
		} else {
			delete_post_meta($postId, $metaKey);
		}

		$this->repo->upsertContact($postId, $contactType, $value);
	}

	/**
	 * Saves location meta fields when a location post is saved.
	 *
	 * Handles nonce verification, capability checks, and sanitization.
	 *
	 * @param  int     $postId  The post ID.
	 * @param  WP_Post $post    The post object.
	 * @return void
	 */
	public function saveLocationMeta(int $postId, WP_Post $post): void {
		if (get_post_type($postId) !== 'locfinder_location') {
			return;
		}

		if (!isset($_POST['locfinder_location_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['locfinder_location_meta_nonce'])), 'locfinder_save_location_meta')
		) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $postId)) {
			return;
		}

		// Sanitize and prepare location fields.
		$address2    = sanitize_text_field(wp_unslash($_POST['locfinder_address_2'] ?? ''));
		$city        = sanitize_text_field(wp_unslash($_POST['locfinder_city'] ?? ''));
		$state       = sanitize_text_field(wp_unslash($_POST['locfinder_state'] ?? ''));
		$postalCode  = sanitize_text_field(wp_unslash($_POST['locfinder_postal_code'] ?? ''));
		$countryCode = strtoupper(substr(sanitize_text_field(wp_unslash($_POST['locfinder_country_code'] ?? '')), 0, 2));
		$address     = sanitize_text_field(wp_unslash($_POST['locfinder_formatted_address'] ?? ''));
		$latRaw      = sanitize_text_field(wp_unslash($_POST['locfinder_latitude'] ?? ''));
		$lngRaw      = sanitize_text_field(wp_unslash($_POST['locfinder_longitude'] ?? ''));
		$placeId     = sanitize_text_field(wp_unslash($_POST['locfinder_place_id'] ?? ''));
		$precision   = Options::getCoordinatePrecision();
		$lat         = is_numeric($latRaw) ? round(max(-90.0, min(90.0, (float) $latRaw)), $precision) : null;
		$lng         = is_numeric($lngRaw) ? round(max(-180.0, min(180.0, (float) $lngRaw)), $precision) : null;

		$hasPhone   = isset($_POST['locfinder_phone']) && sanitize_text_field(wp_unslash($_POST['locfinder_phone'])) !== '';
		$hasEmail   = isset($_POST['locfinder_email']) && sanitize_email(wp_unslash($_POST['locfinder_email'])) !== '';
		$hasWebsite = isset($_POST['locfinder_website_url']) && esc_url_raw(wp_unslash($_POST['locfinder_website_url'])) !== '';

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only used for a boolean emptiness check on nested keys; nothing here is stored or output. The real per-field values are sanitized individually below.
		$hasHours = isset($_POST['locfinder_hours']) && is_array($_POST['locfinder_hours']) && array_filter(
			wp_unslash($_POST['locfinder_hours']),
			static fn($day) => is_array($day) && (!empty($day['closed']) || !empty($day['is_24']) || !empty($day['open']) || !empty($day['close']))
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ($city === '' && $state === '' && $postalCode === '' && $countryCode === '' && $address === '' && $lat === null && $lng === null
			&& !$hasPhone && !$hasEmail && !$hasWebsite && !$hasHours) {
			delete_post_meta($postId, 'locfinder_phone');
			delete_post_meta($postId, 'locfinder_email');
			delete_post_meta($postId, 'locfinder_website_url');
			$this->repo->delete($postId);
			$this->repo->clearGeocodeCache();
			return;
		}

		$existing = $this->repo->getLocationByPostId($postId);

		$data = [
			'address'      => $address,
			'address_2'    => $address2,
			'city'         => $city,
			'state'        => $state,
			'postal_code'  => $postalCode,
			'country_code' => $countryCode,
			'latitude'     => $lat,
			'longitude'    => $lng,
		];

		if ($placeId !== '') {
			$data['place_id'] = $placeId;
		} elseif ($existing !== null && !empty($existing['place_id'])) {
			$data['place_id'] = (string) $existing['place_id'];
		}

		$this->repo->upsert($postId, $data);

		// Sanitize and save contact fields.
		if (isset($_POST['locfinder_phone'])) {
			$phone = Helper::normalizePhoneNumber(sanitize_text_field(wp_unslash($_POST['locfinder_phone'])));
			$this->syncContactField($postId, 'locfinder_phone', 'phone', $phone);
		}

		if (isset($_POST['locfinder_email'])) {
			$emailRaw = sanitize_email(wp_unslash($_POST['locfinder_email']));
			$email    = ($emailRaw !== '' && is_email($emailRaw)) ? $emailRaw : '';
			$this->syncContactField($postId, 'locfinder_email', 'email', $email);
		}

		if (isset($_POST['locfinder_website_url'])) {
			$url = esc_url_raw(wp_unslash($_POST['locfinder_website_url']));
			$this->syncContactField($postId, 'locfinder_website_url', 'website', $url);
		}

		// Sanitize and save hours.
		if (isset($_POST['locfinder_hours']) && is_array($_POST['locfinder_hours'])) {
			$locationId = $this->repo->getIdByPostId($postId);

			if ($locationId) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array input; each element is sanitized individually inside the loop via absint(), sanitize_text_field() and a strict HH:MM pattern match.
				foreach (wp_unslash($_POST['locfinder_hours']) as $dayNum => $dayData) {
					$dayNum = absint($dayNum);

					if ($dayNum < 1 || $dayNum > 7 || !is_array($dayData)) {
						continue;
					}

					$isClosed = !empty($dayData['closed']);
					$is24     = !empty($dayData['is_24']);
					$openRaw  = sanitize_text_field($dayData['open'] ?? '');
					$closeRaw = sanitize_text_field($dayData['close'] ?? '');

					// Only accept a strict HH:MM match from the time input.
					$openTime  = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $openRaw)  ? $openRaw . ':00'  : null;
					$closeTime = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $closeRaw) ? $closeRaw . ':00' : null;

					if (!$isClosed && !$is24 && !($openTime && $closeTime)) {
						// Fields for this day are blank, remove previously saved row instead of leaving it stale.
						$this->repo->deleteHoursForDay($locationId, $dayNum);
						continue;
					}

					$this->repo->upsertHours($locationId, [
						'day_of_week' => $dayNum,
						'open_time'   => ($isClosed || $is24) ? null : $openTime,
						'close_time'  => ($isClosed || $is24) ? null : $closeTime,
						'is_closed'   => $isClosed ? 1 : 0,
						'is_24_hours' => $is24 ? 1 : 0,
						'sort_order'  => $dayNum,
					]);
				}
			}
		}

		$this->repo->clearGeocodeCache();
	}

	/**
	 * Deletes location data when a Location post is permanently deleted.
	 *
	 * Runs before the post is deleted so related location, contact, and hours
	 * data can be removed first.
	 *
	 * @param  int $postId  Post ID being deleted.
	 * @return void
	 */
	public function deleteLocationData(int $postId): void {
		if (get_post_type($postId) !== 'locfinder_location') {
			return;
		}

		$this->repo->delete($postId);
		$this->repo->clearGeocodeCache();
	}

	/**
	 * Clears cached search results when a Location post changes status.
	 *
	 * Status changes can bypass saveLocationMeta(), including trash, restore,
	 * and Quick Edit, so the search cache is invalidated separately here.
	 *
	 * @param  string   $new   New post status.
	 * @param  string   $old   Previous post status.
	 * @param  \WP_Post $post  Post being transitioned.
	 * @return void
	 */
	public function flushCacheOnStatusChange(string $new, string $old, \WP_Post $post): void {
		if ($post->post_type !== 'locfinder_location' || $new === $old) {
			return;
		}

		$this->repo->clearGeocodeCache();
	}

	/**
	 * Clears cached search results when a Location's terms change.
	 *
	 * Term changes can affect taxonomy-filtered results, including updates made
	 * through Quick Edit or the term editor.
	 *
	 * @param  int    $objectId  Object ID.
	 * @param  array  $terms     Terms being assigned.
	 * @param  array  $ttIds     Term taxonomy IDs.
	 * @param  string $taxonomy  Taxonomy slug.
	 * @param  bool   $append    Whether terms were appended.
	 * @param  array  $oldTtIds  Previous term taxonomy IDs.
	 * @return void
	 */
	public function flushCacheOnTermChange(int $objectId, array $terms, array $ttIds, string $taxonomy, bool $append, array $oldTtIds): void {
		if (get_post_type($objectId) !== 'locfinder_location' || $ttIds === $oldTtIds) {
			return;
		}

		$this->repo->clearGeocodeCache();
	}
}
