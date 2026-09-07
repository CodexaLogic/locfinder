<?php
/**
 * Handles AJAX requests for the plugin.
 *
 * @package Locfinder
 */

namespace Locfinder\Api;

use Locfinder\Database\LocationRepository;
use Locfinder\Frontend\Frontend;
use Locfinder\Utilities\Helper;
use Locfinder\Utilities\Options;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles AJAX requests for the plugin.
 */
class AjaxController {

	/**
	 * Maximum allowed value for posts_per_page from an AJAX request.
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Maximum number of pins sent to the map per request, independent of
	 * postsPerPage. The map shows every location matching the current
	 * filters, not just the current results page.
	 *
	 * @var int
	 */
	private const MAX_MAP_PINS = 500;

	/**
	 * Maximum allowed search radius from an AJAX request, in whichever unit
	 * the site is configured to use.
	 *
	 * @var int
	 */
	private const MAX_RADIUS = 5000;

	/**
	 * Location repository instance.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $repo;

	/**
	 * Maximum requests allowed per IP within the rate-limit window.
	 */
	private const RATE_LIMIT_MAX_REQUESTS = 30;

	/**
	 * Rate-limit window, in seconds.
	 */
	private const RATE_LIMIT_WINDOW_SECONDS = 60;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new LocationRepository();
	}

	/**
	 * Handles the location search results AJAX request.
	 *
	 * Two code paths depending on whether an address is part of the search:
	 *
	 * A) Address search: WP_Query builds a whitelist of matching published
	 *    locations. The repository then handles distance sorting, optional
	 *    radius filtering, and pagination.
	 *
	 * B) No address: WP_Query handles filtering, sorting, and pagination.
	 *
	 * Map pins are resolved separately from the paginated results, allowing
	 * the map to include all matching locations up to MAX_MAP_PINS.
	 *
	 * This is an intentionally unauthenticated, nonce-free, read-only endpoint.
	 *
	 * @return void
	 */
	public function getLocationResults(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public read-only search endpoint registered on wp_ajax_nopriv_. See the method docblock above for why a nonce is intentionally absent.
		$gridCols          = absint($_POST['grid_cols'] ?? 3);
		$orderRaw          = strtoupper(sanitize_text_field(wp_unslash($_POST['order'] ?? '')));
		$order             = in_array($orderRaw, ['ASC', 'DESC'], true) ? $orderRaw : 'ASC';
		$orderbyRaw        = sanitize_key(wp_unslash($_POST['orderby'] ?? ''));
		$orderby           = in_array($orderbyRaw, ['date', 'post_title', 'rand'], true) ? $orderbyRaw : 'post_title';
		$paged             = max(1, absint($_POST['paged'] ?? 1));
		$postType          = 'locfinder_location';
		$requestedTaxonomy = sanitize_key(wp_unslash($_POST['taxonomy'] ?? 'locfinder_category'));
		$taxonomy          = 'locfinder_category';
		$postStatus        = 'publish';
		$perPage           = max(1, min(self::MAX_PER_PAGE, absint($_POST['posts_per_page'] ?? Options::getResultsPerPage())));
		$pinColor          = sanitize_hex_color(wp_unslash($_POST['pin_color'] ?? '')) ?: Options::getPinColor();
		$unit              = Options::getDistanceUnit();
		$showAddress       = isset($_POST['show_address']) ? filter_var(wp_unslash($_POST['show_address']), FILTER_VALIDATE_BOOLEAN) : true;
		$showDirections    = isset($_POST['show_directions']) ? filter_var(wp_unslash($_POST['show_directions']), FILTER_VALIDATE_BOOLEAN) : true;
		$showPhone         = isset($_POST['show_phone']) ? filter_var(wp_unslash($_POST['show_phone']), FILTER_VALIDATE_BOOLEAN) : true;
		$showEmail         = isset($_POST['show_email']) ? filter_var(wp_unslash($_POST['show_email']), FILTER_VALIDATE_BOOLEAN) : false;
		$showWebsite       = isset($_POST['show_website']) ? filter_var(wp_unslash($_POST['show_website']), FILTER_VALIDATE_BOOLEAN) : true;
		$showHours         = isset($_POST['show_hours']) ? filter_var(wp_unslash($_POST['show_hours']), FILTER_VALIDATE_BOOLEAN)   : false;
		$showOpenBadge     = isset($_POST['show_open_badge']) ? filter_var(wp_unslash($_POST['show_open_badge']), FILTER_VALIDATE_BOOLEAN) : Options::getShowOpenNowBadge();
		$showCategories    = isset($_POST['show_categories']) ? filter_var(wp_unslash($_POST['show_categories']), FILTER_VALIDATE_BOOLEAN) : true;
		$keyword           = self::normalizeSearchTerm(sanitize_text_field(wp_unslash($_POST['filter_post_title'] ?? '')));
		$filterCategory    = absint($_POST['filter_category'] ?? 0);
		$radius            = max(0.0, min(self::MAX_RADIUS, (float) sanitize_text_field(wp_unslash($_POST['filter_radius'] ?? ''))));
		$latRaw            = sanitize_text_field(wp_unslash($_POST['filter_lat'] ?? ''));
		$lat               = (is_numeric($latRaw) && is_finite((float) $latRaw))
			? max(-90.0, min(90.0, (float) $latRaw))
			: null;
		$lngRaw = sanitize_text_field(wp_unslash($_POST['filter_lng'] ?? ''));
		$lng    = (is_numeric($lngRaw) && is_finite((float) $lngRaw))
			? max(-180.0, min(180.0, (float) $lngRaw))
			: null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$hasAddress  = $lat !== null && $lng !== null;
		$hasRadius   = $hasAddress && $radius > 0;
		$queryRadius = $hasRadius ? $radius : null;
		$offset      = ($paged - 1) * $perPage;

		// Build sanitized context for query filters.
		$filterContext = [
			'taxonomy'          => $taxonomy,
			'requestedTaxonomy' => $requestedTaxonomy,
			'keyword'           => $keyword,
			'filterCategory'    => $filterCategory,
			'radius'            => $radius,
			'lat'               => $lat,
			'lng'               => $lng,
			'unit'              => $unit,
			'paged'             => $paged,
			'perPage'           => $perPage,
			'order'             => $order,
			'orderby'           => $orderby,
			'hasAddress'        => $hasAddress,
			'hasRadius'         => $hasRadius,
		];

		// Cache query results separately from presentation state.
		$cacheKeyword = mb_substr($keyword, 0, 100, 'UTF-8');
		$cacheLat     = $lat !== null ? round($lat, 4) : null;
		$cacheLng     = $lng !== null ? round($lng, 4) : null;

		$dataCacheKey = 'locfinder_geo_data_' . $this->repo->getGeocodeCacheGeneration() . '_' . md5(serialize([
			$postType, $postStatus, $requestedTaxonomy, $order, $orderby,
			$paged, $perPage, $cacheKeyword, $filterCategory, $radius, $cacheLat, $cacheLng, $unit,
		]));

		if (!self::checkRateLimit()) {
			wp_send_json_error(['message' => __('Too many requests, please slow down.', 'locfinder')], 429);
		}

		$cachedData = get_transient($dataCacheKey);

		if ($cachedData !== false) {
			$postIds    = $cachedData['postIds'];
			$geoData    = $cachedData['geoData'];
			$total      = $cachedData['total'];
			$totalPages = $cachedData['totalPages'];
		} else {
			if ($hasAddress) {
				$queryArgs = [
					'post_type'      => $postType,
					'post_status'    => $postStatus,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				];

				if ($keyword) {
					$queryArgs['s'] = $keyword;
				}

				if ($filterCategory) {
					$queryArgs['tax_query'] = [[
						'taxonomy' => 'locfinder_category',
						'field'    => 'term_id',
						'terms'    => $filterCategory,
					]];
				}

				$queryArgs    = self::lockQueryArgs(apply_filters('locfinder/query_args', $queryArgs, $filterContext), $postType, $postStatus);
				$whitelistIds = (new \WP_Query($queryArgs))->posts;

				if (empty($whitelistIds)) {
					$postIds    = [];
					$geoData    = [];
					$total      = 0;
					$totalPages = 0;
				} else {
					$total      = $this->repo->countLocationsInRadius($lat, $lng, $queryRadius, $unit, $whitelistIds);
					$geoRows    = $this->repo->getLocationsInRadius($lat, $lng, $queryRadius, $unit, $perPage, $offset, $whitelistIds);
					$totalPages = (int) ceil($total / $perPage);

					$postIds = [];
					$geoData = [];

					foreach ($geoRows as $row) {
						$pid           = (int) $row['post_id'];
						$postIds[]     = $pid;
						$geoData[$pid] = [
							'lat'      => is_numeric($row['latitude'])  ? (float) $row['latitude']  : null,
							'lng'      => is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
							'distance' => isset($row['distance'])       ? (float) $row['distance']  : null,
						];
					}
				}
			} else {
				$queryArgs = [
					'order'          => $order,
					'orderby'        => $orderby,
					'post_type'      => $postType,
					'post_status'    => $postStatus,
					'posts_per_page' => $perPage,
					'paged'          => $paged,
					'fields'         => 'ids',
					'no_found_rows'  => false,
				];

				if ($keyword) {
					$queryArgs['s'] = $keyword;
				}

				if ($filterCategory) {
					$queryArgs['tax_query'] = [[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $filterCategory,
					]];
				}

				$queryArgs  = self::lockQueryArgs(apply_filters('locfinder/query_args', $queryArgs, $filterContext), $postType, $postStatus);
				$query      = new \WP_Query($queryArgs);
				$postIds    = $query->posts;
				$total      = $query->found_posts;
				$totalPages = (int) ceil($total / $perPage);
				$rawGeo     = $this->repo->getGeoDataForLocations($postIds);
				$geoData    = [];

				foreach ($rawGeo as $pid => $geo) {
					$geoData[$pid] = [
						'lat'      => $geo['lat'],
						'lng'      => $geo['lng'],
						'distance' => null,
					];
				}
			}

			set_transient($dataCacheKey, [
				'postIds'    => $postIds,
				'geoData'    => $geoData,
				'total'      => $total,
				'totalPages' => $totalPages,
			], Options::getGeocodeCacheTtl());
		}

		$mapData    = $this->getMapLocationIds($requestedTaxonomy, $keyword, $filterCategory, $hasAddress, $queryRadius, $lat, $lng, $unit);
		$mapPostIds = $mapData['postIds'];
		$mapGeoData = $mapData['geoData'];

		$mapLocations = [];

		if (!empty($mapPostIds)) {
			$mapPostTitles = [];

			foreach (get_posts([
				'post__in'       => $mapPostIds,
				'posts_per_page' => count($mapPostIds),
				'orderby'        => 'post__in',
				'post_type'      => $postType,
				'post_status'    => $postStatus,
			]) as $mapPostObj) {
				$mapPostTitles[$mapPostObj->ID] = html_entity_decode((string) get_the_title($mapPostObj), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			}

			$mapAddressData = $this->repo->getAddressesByPostIds($mapPostIds);
			$mapTerms       = wp_get_object_terms($mapPostIds, 'locfinder_category', ['fields' => 'all_with_object_id']);
			$mapTermsByPost = [];

			if (!is_wp_error($mapTerms)) {
				foreach ($mapTerms as $term) {
					$mapTermsByPost[$term->object_id][] = $term;
				}
			}

			foreach ($mapPostIds as $mapPostId) {
				$mapPostId = (int) $mapPostId;

				// Skip cached IDs that no longer resolve to published posts.
				if (!isset($mapPostTitles[$mapPostId])) {
					continue;
				}

				$mapGeo     = $mapGeoData[$mapPostId] ?? ['lat' => null, 'lng' => null];
				$mapTermIds = array_map(fn ($t) => (int) $t->term_id, $mapTermsByPost[$mapPostId] ?? []);

				if ($filterCategory && in_array($filterCategory, $mapTermIds, true)) {
					$mapTermIds = array_merge(
						[$filterCategory],
						array_diff($mapTermIds, [$filterCategory])
					);
				}

				$mapPinItem = [
					'id'        => $mapPostId,
					'postTitle' => $mapPostTitles[$mapPostId],
					'address'   => sanitize_text_field($mapAddressData[$mapPostId] ?? ''),
					'permalink' => get_permalink($mapPostId),
					'pinColor'  => $pinColor,
					'termIds'   => $mapTermIds,
					'lat'       => $mapGeo['lat'],
					'lng'       => $mapGeo['lng'],
				];

				/**
				 * Filters a single map pin item before it's returned to the frontend.
				 *
				 * $mapGeo['distance'] (this pin's computed distance in the configured unit,
				 * or null if unavailable) is available here for the pro plugin to add a
				 * 'distance' key, or any other additional field, to the map pin payload.
				 *
				 * @param array $item      The map pin item.
				 * @param int   $mapPostId The location's post ID.
				 */
				$mapLocations[] = apply_filters('locfinder_map_location_item', $mapPinItem, $mapPostId);
			}
		}

		if (empty($postIds)) {
			wp_send_json_success([
				'posts'           => [],
				'mapLocations'    => $mapLocations,
				'defaultPinColor' => $pinColor,
				'postsPerPage'    => $perPage,
				'total'           => 0,
				'paged'           => $paged,
				'totalPages'      => 0,
			]);
		}

		$postObjectMap = [];

		foreach (get_posts([
			'post__in'       => $postIds,
			'posts_per_page' => count($postIds),
			'orderby'        => 'post__in',
			'post_type'      => $postType,
			'post_status'    => $postStatus,
		]) as $postObj) {
			$postObjectMap[$postObj->ID] = $postObj;
		}

		$locationIdMap = $this->repo->getLocationIdsByPostIds($postIds);

		$allContacts = !empty($locationIdMap)
			? $this->repo->getContactsForLocations(array_values($locationIdMap))
			: [];

		$allHours = !empty($locationIdMap)
			? $this->repo->getHoursForLocations(array_values($locationIdMap))
			: [];

		$addressData = $this->repo->getAddressesByPostIds($postIds);

		$allTerms    = wp_get_object_terms($postIds, 'locfinder_category', ['fields' => 'all_with_object_id']);
		$termsByPost = [];

		if (!is_wp_error($allTerms)) {
			foreach ($allTerms as $term) {
				$termsByPost[$term->object_id][] = $term;
			}
		}

		// Build the results list. Map pin data was already resolved above,
		// independent of this page's post IDs.
		$posts    = [];
		$frontend = new Frontend();

		foreach ($postIds as $postId) {
			$postId = (int) $postId;
			$post   = $postObjectMap[$postId] ?? null;

			// Skip cached IDs that no longer resolve to published posts.
			if (!$post instanceof \WP_Post) {
				continue;
			}

			$geo           = $geoData[$postId] ?? ['lat' => null, 'lng' => null, 'distance' => null];
			$locationId    = $locationIdMap[$postId] ?? null;
			$contacts      = $locationId ? ($allContacts[$locationId] ?? []) : [];
			$hours         = $locationId ? ($allHours[$locationId] ?? []) : [];
			$phoneValue    = $showPhone ? Frontend::getContactValue($contacts, 'phone')   : '';
			$phoneTel      = $showPhone ? Helper::formatPhoneForTel($phoneValue)          : '';
			$emailValue    = $showEmail ? Frontend::getContactValue($contacts, 'email')   : '';
			$websiteValue  = $showWebsite ? Frontend::getContactValue($contacts, 'website') : '';
			$hoursHtml     = $showHours ? Frontend::renderHoursTable($hours) : '';
			$openBadgeHtml = ($showOpenBadge && !empty($hours)) ? Frontend::renderOpenNowBadge($hours) : '';
			$terms         = $termsByPost[$postId] ?? [];
			$locationInfo  = $frontend->getLocationInfo($postId, $taxonomy, $contacts, $terms, $hours, [
				'address'    => $showAddress,
				'directions' => $showDirections,
				'phone'      => $showPhone,
				'email'      => $showEmail,
				'website'    => $showWebsite,
				'hours'      => $showHours,
				'categories' => $showCategories,
			]);

			$hasAnyData = ($addressData[$postId] ?? '') !== ''
				|| !empty($contacts)
				|| !empty($hours)
				|| !empty($terms);

			$termSlugs = array_column($terms, 'slug');
			$termIds   = array_map(fn ($t) => (int) $t->term_id, $terms);

			// Prioritize the active term when resolving pin styles.
			if ($filterCategory && in_array($filterCategory, $termIds, true)) {
				$termIds = array_merge(
					[$filterCategory],
					array_diff($termIds, [$filterCategory])
				);
			}

			$postTitle    = html_entity_decode((string) get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$rawExcerpt   = $post ? ($post->post_excerpt ?: wp_trim_words($post->post_content, 55)) : '';
			$postExcerpt  = $rawExcerpt !== '' ? html_entity_decode($rawExcerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
			$thumbnailUrl = get_the_post_thumbnail_url($postId, 'medium_large');
			$thumbnailUrl = $thumbnailUrl ? esc_url_raw($thumbnailUrl) : '';

			$item = [
				'id'            => $postId,
				'postTitle'     => $postTitle,
				'address'       => sanitize_text_field($addressData[$postId] ?? ''),
				'phone'         => sanitize_text_field($phoneValue),
				'phoneTel'      => sanitize_text_field($phoneTel),
				'email'         => sanitize_email($emailValue),
				'website'       => esc_url_raw($websiteValue),
				'hoursHtml'     => $hoursHtml,
				'openBadgeHtml' => $openBadgeHtml,
				'gridCols'      => $gridCols,
				'excerpt'       => $postExcerpt,
				'permalink'     => get_permalink($postId),
				'locationInfo'  => $locationInfo !== '' ? $locationInfo : ($hasAnyData ? '' : self::getNoInfoHtml()),
				'thumbnailUrl'  => $thumbnailUrl,
				'lat'           => $geo['lat'],
				'lng'           => $geo['lng'],
				'taxClasses'    => implode(' ', $termSlugs),
			];

			/**
			 * Filters a single search result item before it's returned to the frontend.
			 *
			 * $geo['distance'] (this result's computed distance in the configured
			 * unit, or null if unavailable) is available here for a plugin that
			 * wants to add a 'distance' key, or any other additional field, to
			 * the result payload.
			 *
			 * @param array   $item  The result item.
			 * @param WP_Post $post  The location's post object.
			 * @param array   $geo   ['lat' => float|null, 'lng' => float|null, 'distance' => float|null].
			 */
			$posts[] = apply_filters('locfinder_result_item', $item, $post, $geo);
		}

		$payload = [
			'posts'           => $posts,
			'mapLocations'    => $mapLocations,
			'defaultPinColor' => $pinColor,
			'postsPerPage'    => $perPage,
			'total'           => $total,
			'paged'           => $paged,
			'totalPages'      => $totalPages,
		];

		wp_send_json_success($payload);
	}

	/**
	 * Handles the map pin popup's detail request.
	 *
	 * Populates the popup's phone, website, directions, and open/closed
	 * badge, fetched once a pin is clicked since that data isn't part of
	 * the lightweight mapLocations payload every pin already has (see
	 * getMapLocationIds()). "View details" links straight to the
	 * location's own page rather than opening anything further here, so
	 * this endpoint stays narrow: no taxonomy, no title, nothing else.
	 *
	 * Deliberately unauthenticated and nonce-free, for the same reason as
	 * getLocationResults(): read-only, returns only published locations
	 * already visible on the front end. See that method's docblock.
	 *
	 * @return void
	 */
	public function getLocationDetails(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public read-only endpoint, same rationale as getLocationResults(). See that method's docblock.
		$postId         = absint($_POST['post_id'] ?? 0);
		$showAddress    = isset($_POST['show_address']) ? filter_var(wp_unslash($_POST['show_address']), FILTER_VALIDATE_BOOLEAN) : true;
		$showDirections = isset($_POST['show_directions']) ? filter_var(wp_unslash($_POST['show_directions']), FILTER_VALIDATE_BOOLEAN) : true;
		$showPhone      = isset($_POST['show_phone']) ? filter_var(wp_unslash($_POST['show_phone']), FILTER_VALIDATE_BOOLEAN) : true;
		$showWebsite    = isset($_POST['show_website']) ? filter_var(wp_unslash($_POST['show_website']), FILTER_VALIDATE_BOOLEAN) : true;
		$showOpenBadge  = isset($_POST['show_open_badge']) ? filter_var(wp_unslash($_POST['show_open_badge']), FILTER_VALIDATE_BOOLEAN) : Options::getShowOpenNowBadge();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if (!self::checkRateLimit()) {
			wp_send_json_error(['message' => __('Too many requests, please slow down.', 'locfinder')], 429);
		}

		$post = get_post($postId);

		if (
			!$post instanceof \WP_Post
			|| $post->post_type !== 'locfinder_location'
			|| $post->post_status !== 'publish'
		) {
			wp_send_json_error(['message' => __('Location not found.', 'locfinder')]);
		}

		$locationIdMap = $this->repo->getLocationIdsByPostIds([$postId]);
		$locationId    = $locationIdMap[$postId] ?? null;
		$contacts      = $locationId ? ($this->repo->getContactsForLocations([$locationId])[$locationId] ?? []) : [];
		$hours         = $locationId ? ($this->repo->getHoursForLocations([$locationId])[$locationId] ?? []) : [];

		$frontend = new Frontend();
		$parts    = $frontend->getLocationDetailParts($postId, $contacts, $hours, [
			'address'    => $showAddress,
			'directions' => $showDirections,
			'phone'      => $showPhone,
			'website'    => $showWebsite,
			'openBadge'  => $showOpenBadge,
		]);

		wp_send_json_success($parts);
	}

	/**
	 * Gets all locations matching the current filters for the map, independent
	 * of list pagination and capped at MAX_MAP_PINS.
	 *
	 * Map results are cached separately from paginated list results because
	 * pagination and sort order do not affect the map pins.
	 *
	 * When an address search is active, this method runs its own unpaginated
	 * WP_Query to build the allowed location IDs before applying the radius
	 * query. This keeps the map query self-contained and is only incurred on
	 * a cold cache.
	 *
	 * @param  string     $requestedTaxonomy  Requested taxonomy, e.g. 'locfinder_category'.
	 * @param  string     $keyword            Keyword search term.
	 * @param  int        $filterCategory     Term ID to filter by, 0 for none.
	 * @param  bool       $hasAddress         Whether a search center is active (address selected).
	 * @param  float|null $radius             Search radius, or null for "any distance" (no cutoff, sorted by
	 *                                        proximity anyway). Ignored when $hasAddress is false. See
	 *                                        LocationRepository::getLocationsInRadius() for why null and not
	 *                                        an arbitrarily large stand-in value.
	 * @param  float|null $lat                Search center latitude, ignored when $hasAddress is false.
	 * @param  float|null $lng                Search center longitude, ignored when $hasAddress is false.
	 * @param  string     $unit               Distance unit, 'mi' or 'km'.
	 * @return array{postIds:int[],geoData:array<int,array{lat:?float,lng:?float}>}
	 */
	private function getMapLocationIds(
		string $requestedTaxonomy,
		string $keyword,
		int $filterCategory,
		bool $hasAddress,
		?float $radius,
		?float $lat,
		?float $lng,
		string $unit
	): array {
		$postType   = 'locfinder_location';
		$postStatus = 'publish';

		$cacheKeyword = mb_substr($keyword, 0, 100, 'UTF-8');
		$cacheLat     = $lat !== null ? round($lat, 4) : null;
		$cacheLng     = $lng !== null ? round($lng, 4) : null;

		$mapCacheKey = 'locfinder_map_data_' . $this->repo->getGeocodeCacheGeneration() . '_' . md5(serialize([
			$postType, $postStatus, $requestedTaxonomy, $cacheKeyword, $filterCategory, $hasAddress, $radius, $cacheLat, $cacheLng, $unit,
		]));

		$cached = get_transient($mapCacheKey);

		if ($cached !== false) {
			return $cached;
		}

		$queryArgs = [
			'post_type'      => $postType,
			'post_status'    => $postStatus,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		if ($keyword) {
			$queryArgs['s'] = $keyword;
		}

		if ($filterCategory) {
			$queryArgs['tax_query'] = [[
				'taxonomy' => 'locfinder_category',
				'field'    => 'term_id',
				'terms'    => $filterCategory,
			]];
		}

		$queryArgs = self::lockQueryArgs(
			apply_filters('locfinder/query_args', $queryArgs, [
				'taxonomy'          => 'locfinder_category',
				'requestedTaxonomy' => $requestedTaxonomy,
				'keyword'           => $keyword,
				'filterCategory'    => $filterCategory,
				'radius'            => $radius,
				'lat'               => $lat,
				'lng'               => $lng,
				'unit'              => $unit,
				'hasAddress'        => $hasAddress,
			]),
			$postType,
			$postStatus
		);

		if ($hasAddress) {
			$whitelistIds = (new \WP_Query($queryArgs))->posts;

			if (empty($whitelistIds)) {
				$result = ['postIds' => [], 'geoData' => []];
			} else {
				$geoRows = $this->repo->getLocationsInRadius($lat, $lng, $radius, $unit, self::MAX_MAP_PINS, 0, $whitelistIds);
				$postIds = [];
				$geoData = [];

				foreach ($geoRows as $row) {
					$pid           = (int) $row['post_id'];
					$postIds[]     = $pid;
					$geoData[$pid] = [
						'lat' => is_numeric($row['latitude'])  ? (float) $row['latitude']  : null,
						'lng' => is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
					];
				}

				$result = ['postIds' => $postIds, 'geoData' => $geoData];
			}
		} else {
			$queryArgs['posts_per_page'] = self::MAX_MAP_PINS;
			$postIds                     = (new \WP_Query($queryArgs))->posts;
			$rawGeo                      = $this->repo->getGeoDataForLocations($postIds);
			$geoData                     = [];

			foreach ($rawGeo as $pid => $geo) {
				$geoData[$pid] = ['lat' => $geo['lat'], 'lng' => $geo['lng']];
			}

			$result = ['postIds' => $postIds, 'geoData' => $geoData];
		}

		set_transient($mapCacheKey, $result, Options::getGeocodeCacheTtl());

		return $result;
	}

	/**
	 * Handles the geocode transient cache clear AJAX request.
	 *
	 * Advances the cache generation number so every previously cached
	 * geocode search result becomes unreachable. Requires manage_options
	 * capability and a valid nonce.
	 *
	 * @return void
	 */
	public function clearGeocodeCache(): void {
		check_ajax_referer('locfinder_clear_geo_cache', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Permission denied.', 'locfinder')]);
		}

		$this->repo->clearGeocodeCache();

		wp_send_json_success([
			'message' => __('Geocode cache cleared.', 'locfinder'),
		]);
	}

	/**
	 * Re-asserts the query constraints that must never be overridable.
	 *
	 * locfinder/query_args is a public filter on an unauthenticated endpoint.
	 * A callback that set post_type, post_status, or fields would bypass the
	 * CPT lock, the publish-only restriction, and the ID-only fetch that the
	 * rest of this class depends on, so those three are reapplied after the
	 * filter runs rather than trusted from its return value.
	 *
	 * @param  array  $queryArgs   Filtered WP_Query args.
	 * @param  string $postType    The only permitted post type.
	 * @param  string $postStatus  The only permitted post status.
	 * @return array               Args with the non-negotiable keys restored.
	 */
	private static function lockQueryArgs(array $queryArgs, string $postType, string $postStatus): array {
		$queryArgs['post_type']   = $postType;
		$queryArgs['post_status'] = $postStatus;
		$queryArgs['fields']      = 'ids';

		return $queryArgs;
	}

	/**
	 * Gets the fallback HTML shown when a location has no data at all.
	 *
	 * Deliberately distinct from an empty locationInfo caused by every
	 * visibility toggle being off, that case should render nothing.
	 *
	 * @return string  Fallback HTML for a location with no underlying data.
	 */
	private static function getNoInfoHtml(): string {
		return '<div class="locfinder-result__no-info">' . esc_html__('No location information available.', 'locfinder') . '</div>';
	}

	/**
	 * Per-IP throttle for the public, nonce-free search and map endpoints.
	 *
	 * Uses a non-atomic transient counter, which is sufficient for a public endpoint.
	 * Fails open if an IP address cannot be determined.
	 *
	 * @return bool  True if allowed, false if the limit is exceeded.
	 */
	private static function checkRateLimit(): bool {
		$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

		if ($ip === '') {
			return true;
		}

		$key   = 'locfinder_search_rl_' . md5($ip);
		$count = (int) get_transient($key);

		if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
			return false;
		}

		set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW_SECONDS);

		return true;
	}

	/**
	 * Normalizes typographic characters in a search term to ASCII equivalents.
	 *
	 * Converts Unicode dashes, quotation marks, prime symbols, and ellipses
	 * to their ASCII representations to improve search matching.
	 *
	 * @param  string $keyword Raw  search term.
	 * @return string  Search term with typographic characters normalized to ASCII.
	 */
	private static function normalizeSearchTerm(string $keyword): string {
		return strtr($keyword, [
			"\u{2013}" => '-',
			"\u{2014}" => '-',
			"\u{2012}" => '-',
			"\u{2015}" => '-',
			"\u{2011}" => '-',
			"\u{2212}" => '-',
			"\u{2018}" => "'",
			"\u{2019}" => "'",
			"\u{201A}" => "'",
			"\u{201B}" => "'",
			"\u{201C}" => '"',
			"\u{201D}" => '"',
			"\u{201E}" => '"',
			"\u{2032}" => "'",
			"\u{2033}" => '"',
			"\u{2026}" => '...',
		]);
	}
}
