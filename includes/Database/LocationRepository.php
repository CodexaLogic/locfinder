<?php
/**
 * Database query layer for location data.
 *
 * @package Locfinder
 */

namespace Locfinder\Database;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles database operations for location data.
 */
class LocationRepository {

	private string $locationsTable;
	private string $contactsTable;
	private string $hoursTable;

	/**
	 * Allowed location columns and their SQL formats.
	 *
	 * @var array<string,string>
	 */
	private const LOCATION_COLUMNS = [
		'post_id'      => '%d',
		'address'      => '%s',
		'address_2'    => '%s',
		'city'         => '%s',
		'state'        => '%s',
		'postal_code'  => '%s',
		'country_code' => '%s',
		'latitude'     => '%f',
		'longitude'    => '%f',
		'place_id'     => '%s',
	];

	/**
	 * Allowed hours columns and their SQL formats.
	 *
	 * @var array<string,string>
	 */
	private const HOURS_COLUMNS = [
		'day_of_week' => '%d',
		'open_time'   => '%s',
		'close_time'  => '%s',
		'is_closed'   => '%d',
		'is_24_hours' => '%d',
		'sort_order'  => '%d',
	];

	/**
	 * Option name for storing the geocode cache generation number.
	 *
	 * @var string
	 */
	private const GEOCODE_CACHE_GEN_OPTION = 'locfinder_geo_cache_gen';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->locationsTable = $wpdb->prefix . 'locfinder_locations';
		$this->contactsTable  = $wpdb->prefix . 'locfinder_location_contacts';
		$this->hoursTable     = $wpdb->prefix . 'locfinder_location_hours';
	}

	// =========================================================================
	// Location reads
	// =========================================================================

	/**
	 * Gets the internal location ID for a post ID.
	 *
	 * Contacts and hours reference the internal location ID rather than post_id.
	 *
	 * @param  int      $postId  Post ID.
	 * @return int|null          Internal location ID, or null if not found.
	 */
	public function getIdByPostId(int $postId): ?int {
		if (!$postId) {
			return null;
		}

		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE post_id = %d LIMIT 1',
				$this->locationsTable,
				$postId
			)
		);

		return $id !== null ? (int) $id : null;
	}

	/**
	 * Fetches a single location row by post ID.
	 *
	 * @param  int        $postId  Post ID.
	 * @return array|null          Associative row array or null if not found.
	 */
	public function getLocationByPostId(int $postId): ?array {
		if (!$postId) {
			return null;
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, post_id, address, address_2, city, state, postal_code,
				        country_code, latitude, longitude, place_id, created_at, updated_at
				 FROM %i
				 WHERE post_id = %d
				 LIMIT 1',
				$this->locationsTable,
				$postId
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Gets internal location IDs for multiple post IDs in a single query.
	 *
	 * @param  int[] $postIds  Post IDs.
	 * @return array           Map of post_id => location_id.
	 */
	public function getLocationIdsByPostIds(array $postIds): array {
		if (empty($postIds)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($postIds), '%d'));

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$placeholders} contains only %d tokens generated from count($postIds); every value is bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, post_id FROM %i
				WHERE post_id IN ({$placeholders})",
				$this->locationsTable,
				...$postIds
			),
			ARRAY_A
		) ?: [];
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$map = [];

		foreach ($rows as $row) {
			$map[(int) $row['post_id']] = (int) $row['id'];
		}

		return $map;
	}

	/**
	 * Gets coordinates for multiple locations in a single query.
	 *
	 * @param  int[] $postIds  Post IDs.
	 * @return array           Map of post_id => ['lat' => float|null, 'lng' => float|null].
	 */
	public function getGeoDataForLocations(array $postIds): array {
		if (empty($postIds)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($postIds), '%d'));

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$placeholders} contains only %d tokens generated from count($postIds); every value is bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, latitude, longitude
				 FROM %i
				 WHERE post_id IN ({$placeholders})",
				$this->locationsTable,
				...$postIds
			),
			ARRAY_A
		) ?: [];
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$geoData = [];

		foreach ($rows as $row) {
			$geoData[(int) $row['post_id']] = [
				'lat' => is_numeric($row['latitude'])  ? (float) $row['latitude']  : null,
				'lng' => is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
			];
		}

		return $geoData;
	}

	/**
	 * Gets the average coordinates of all published, geocoded locations.
	 *
	 * Used as the fallback map center when no explicit center is configured.
	 *
	 * @return array|null ['lat' => float, 'lng' => float], or null if unavailable.
	 */
	public function getAverageCoordinates(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(l.latitude) AS lat, AVG(l.longitude) AS lng
				 FROM %i l
				 INNER JOIN {$wpdb->posts} p ON p.ID = l.post_id
				 WHERE p.post_type = 'locfinder_location'
				 AND p.post_status = 'publish'
				 AND l.latitude IS NOT NULL
				 AND l.longitude IS NOT NULL",
				$this->locationsTable
			)
		);

		if (!$row || !is_numeric($row->lat) || !is_numeric($row->lng)) {
			return null;
		}

		return [
			'lat' => (float) $row->lat,
			'lng' => (float) $row->lng,
		];
	}

	/**
	 * Counts published locations.
	 *
	 * Used to distinguish an empty site from a search with no matches.
	 *
	 * @return int Number of published locations.
	 */
	public function countPublishedLocations(): int {
		$counts = wp_count_posts('locfinder_location');

		return isset($counts->publish) ? (int) $counts->publish : 0;
	}

	/**
	 * Gets address strings for multiple post IDs in a single query.
	 *
	 * @param  int[]             $postIds  Post IDs.
	 * @return array<int,string>           Addresses keyed by post ID.
	 */
	public function getAddressesByPostIds(array $postIds): array {
		if (empty($postIds)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($postIds), '%d'));

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$placeholders} contains only %d tokens generated from count($postIds); every value is bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, address
				 FROM %i
				 WHERE post_id IN ({$placeholders})",
				$this->locationsTable,
				...$postIds
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return array_column($rows ?? [], 'address', 'post_id');
	}

	// =========================================================================
	// Location writes
	// =========================================================================

	/**
	 * Inserts or updates a location row for the given post ID.
	 *
	 * Expects $data to contain any subset of:
	 *   address, address_2, city, state, postal_code, country_code,
	 *   latitude, longitude, place_id
	 *
	 * @param  int   $postId  Post ID.
	 * @param  array $data    Sanitized column => value pairs.
	 * @return bool           True on success, false on failure.
	 */
	public function upsert(int $postId, array $data): bool {
		if (!$postId) {
			return false;
		}

		// Only allow the expected columns to reach the SQL query.
		$data            = array_intersect_key($data, self::LOCATION_COLUMNS);
		$data['post_id'] = $postId;

		global $wpdb;

		$columns         = array_keys($data);
		$columnList      = implode(', ', $columns);
		$placeholderList = implode(', ', array_map(
			static fn ($col) => in_array($col, ['latitude', 'longitude'], true)
				? "NULLIF(%s, '')"
				: self::LOCATION_COLUMNS[$col],
			$columns
		));

		$values = array_map(
			static fn ($col) => in_array($col, ['latitude', 'longitude'], true)
				? ($data[$col] === null ? '' : (string) $data[$col])
				: $data[$col],
			$columns
		);

		$updateColumns = array_diff($columns, ['post_id']);
		$updateClause  = $updateColumns
			? implode(', ', array_map(fn ($col) => "{$col} = VALUES({$col})", $updateColumns))
			: 'post_id = VALUES(post_id)';

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SQL fragments are built exclusively from the fixed LOCATION_COLUMNS whitelist; values are prepared below.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i ({$columnList}) VALUES ({$placeholderList})
				ON DUPLICATE KEY UPDATE {$updateClause}",
				array_merge([$this->locationsTable], $values)
			)
		);
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result !== false;
	}

	/**
	 * Deletes a location and its related contacts and hours.
	 *
	 * Related rows are deleted first because the custom tables do not use
	 * foreign key constraints.
	 *
	 * @param  int  $postId  Post ID.
	 * @return bool          True on success, false on failure.
	 */
	public function delete(int $postId): bool {
		if (!$postId) {
			return false;
		}

		$locationId = $this->getIdByPostId($postId);

		if ($locationId) {
			$this->deleteAllContacts($locationId);
			$this->deleteHours($locationId);
		}

		global $wpdb;

		$result = $wpdb->delete(
			$this->locationsTable,
			['post_id' => $postId],
			['%d']
		);

		return $result !== false;
	}

	// =========================================================================
	// Contact reads
	// =========================================================================

	/**
	 * Gets all contacts for a location.
	 *
	 * Contacts are returned in insertion order. Display ordering by contact type
	 * is handled by the caller.
	 *
	 * @param  int   $locationId  Internal location ID.
	 * @return array              Contact rows, or an empty array if none exist.
	 */
	public function getContactsByLocationId(int $locationId): array {
		if (!$locationId) {
			return [];
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, location_id, contact_type, value
				FROM %i
				WHERE location_id = %d
				ORDER BY id ASC',
				$this->contactsTable,
				$locationId
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Gets contacts for multiple locations in a single query.
	 *
	 * @param  int[] $locationIds  Internal location IDs.
	 * @return array               Contact rows grouped by location ID.
	 */
	public function getContactsForLocations(array $locationIds): array {
		if (empty($locationIds)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($locationIds), '%d'));

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$placeholders} contains only %d tokens generated from count($locationIds); every value is bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, location_id, contact_type, value
					FROM %i
					WHERE location_id IN ({$placeholders})
					ORDER BY location_id ASC, id ASC",
				$this->contactsTable,
				...$locationIds
			),
			ARRAY_A
		) ?: [];
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$grouped = [];

		foreach ($rows as $row) {
			$grouped[(int) $row['location_id']][] = $row;
		}

		return $grouped;
	}

	// =========================================================================
	// Contact writes
	// =========================================================================

	/**
	 * Inserts or updates a contact for a location.
	 *
	 * Uses the location and contact type unique key to update an existing row.
	 * An empty value removes the contact instead.
	 *
	 * @param  int    $postId       Post ID.
	 * @param  string $contactType  Contact type slug: 'phone', 'email', or 'website'.
	 * @param  string $value        Contact value.
	 * @return bool                 True on success, false on failure.
	 */
	public function upsertContact(int $postId, string $contactType, string $value): bool {
		if (!$postId || $contactType === '') {
			return false;
		}

		$locationId = $this->getIdByPostId($postId);
		if (!$locationId) {
			return false;
		}

		if ($value === '') {
			return $this->deleteContact($locationId, $contactType);
		}

		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (location_id, contact_type, value)
				VALUES (%d, %s, %s)
				ON DUPLICATE KEY UPDATE value = VALUES(value)",
				$this->contactsTable,
				$locationId,
				$contactType,
				$value
			)
		);

		return $result !== false;
	}

	/**
	 * Deletes a single contact by internal location ID and contact type.
	 *
	 * @param  int    $locationId   Internal location ID.
	 * @param  string $contactType  Contact type slug: 'phone', 'email', 'website'.
	 * @return bool                 True on success, false on failure.
	 */
	public function deleteContact(int $locationId, string $contactType): bool {
		if (!$locationId || $contactType === '') {
			return false;
		}

		global $wpdb;

		$result = $wpdb->delete(
			$this->contactsTable,
			['location_id' => $locationId, 'contact_type' => $contactType],
			['%d', '%s']
		);

		return $result !== false;
	}

	/**
	 * Deletes all contacts for a location.
	 *
	 * @param  int  $locationId  Internal location ID.
	 * @return bool              True on success, false on failure.
	 */
	public function deleteAllContacts(int $locationId): bool {
		if (!$locationId) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->delete(
			$this->contactsTable,
			['location_id' => $locationId],
			['%d']
		);

		return $result !== false;
	}

	// =========================================================================
	// Hours reads
	// =========================================================================

	/**
	 * Gets all hours for a location in display order.
	 *
	 * @param  int   $locationId  Internal location ID.
	 * @return array              Hours rows, or an empty array if none exist.
	 */
	public function getHoursByLocationId(int $locationId): array {
		if (!$locationId) {
			return [];
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, location_id, day_of_week, open_time, close_time,
						is_closed, is_24_hours, sort_order
				FROM %i
				WHERE location_id = %d
				ORDER BY sort_order ASC, day_of_week ASC',
				$this->hoursTable,
				$locationId
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Gets hours for multiple locations in a single query.
	 *
	 * @param  int[] $locationIds  Internal location IDs.
	 * @return array               Hours rows grouped by location ID.
	 */
	public function getHoursForLocations(array $locationIds): array {
		if (empty($locationIds)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($locationIds), '%d'));

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$placeholders} contains only %d tokens generated from count($locationIds); every value is bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, location_id, day_of_week, open_time, close_time,
						is_closed, is_24_hours, sort_order
				FROM %i
				WHERE location_id IN ({$placeholders})
				ORDER BY location_id ASC, sort_order ASC, day_of_week ASC",
				$this->hoursTable,
				...$locationIds
			),
			ARRAY_A
		) ?: [];
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$grouped = [];

		foreach ($rows as $row) {
			$grouped[(int) $row['location_id']][] = $row;
		}

		return $grouped;
	}

	// =========================================================================
	// Hours writes
	// =========================================================================

	/**
	 * Inserts or updates a day's hours for a location.
	 *
	 * The location ID and day of week uniquely identify each hours row.
	 *
	 * @param  int   $locationId  Internal location ID.
	 * @param  array $data        Sanitized hours data.
	 * @return bool               True on success, false on failure.
	 */
	public function upsertHours(int $locationId, array $data): bool {
		if (!$locationId || empty($data['day_of_week'])) {
			return false;
		}

		// Only allow the expected columns to reach the SQL query.
		$data = array_intersect_key($data, self::HOURS_COLUMNS);

		global $wpdb;

		$openTime  = $data['open_time'] ?? '';
		$closeTime = $data['close_time'] ?? '';

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i
					(location_id, day_of_week, open_time, close_time, is_closed, is_24_hours, sort_order)
				VALUES (%d, %d, NULLIF(%s, ''), NULLIF(%s, ''), %d, %d, %d)
				ON DUPLICATE KEY UPDATE
					open_time   = VALUES(open_time),
					close_time  = VALUES(close_time),
					is_closed   = VALUES(is_closed),
					is_24_hours = VALUES(is_24_hours),
					sort_order  = VALUES(sort_order)",
				$this->hoursTable,
				$locationId,
				(int) $data['day_of_week'],
				$openTime,
				$closeTime,
				(int) ($data['is_closed'] ?? 0),
				(int) ($data['is_24_hours'] ?? 0),
				(int) ($data['sort_order'] ?? (int) $data['day_of_week'])
			)
		);

		return $result !== false;
	}

	/**
	 * Deletes all hours for a location.
	 *
	 * @param  int  $locationId  Internal location ID.
	 * @return bool              True on success, false on failure.
	 */
	public function deleteHours(int $locationId): bool {
		if (!$locationId) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->delete(
			$this->hoursTable,
			['location_id' => $locationId],
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Deletes a day's hours for a location.
	 *
	 * Used when all fields for a previously saved day are cleared.
	 *
	 * @param  int $locationId  Internal location ID.
	 * @param  int $dayNum      ISO weekday number, 1 (Monday) through 7 (Sunday).
	 * @return bool             True on success, false on failure.
	 */
	public function deleteHoursForDay(int $locationId, int $dayNum): bool {
		if (!$locationId || $dayNum < 1 || $dayNum > 7) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->delete(
			$this->hoursTable,
			['location_id' => $locationId, 'day_of_week' => $dayNum],
			['%d', '%d']
		);

		return $result !== false;
	}

	// =========================================================================
	// Radius search
	// =========================================================================

	/**
	 * Calculates a bounding box for a radius search.
	 *
	 * The bounding box allows indexed latitude/longitude filtering before the
	 * exact Haversine distance calculation.
	 *
	 * @param  float $lat          Origin latitude.
	 * @param  float $lng          Origin longitude.
	 * @param  float $radius       Search radius.
	 * @param  float $earthRadius  Earth's radius in the same unit as $radius.
	 * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
	 */
	private function calculateBoundingBox(float $lat, float $lng, float $radius, float $earthRadius): array {
		$lat     = max(-90.0, min(90.0, $lat));
		$angular = $radius / $earthRadius;
		$minLat  = $lat - rad2deg($angular);
		$maxLat  = $lat + rad2deg($angular);

		$cosLat = cos(deg2rad($lat));
		$sinAng = sin($angular);

		if ($minLat <= -90.0 || $maxLat >= 90.0 || $cosLat <= 0.0 || $sinAng >= $cosLat) {
			return [
				'minLat' => max(-90.0, $minLat),
				'maxLat' => min(90.0, $maxLat),
				'minLng' => -180.0,
				'maxLng' => 180.0,
			];
		}

		$lngDelta = rad2deg(asin($sinAng / $cosLat));
		$minLng   = $lng - $lngDelta;
		$maxLng   = $lng + $lngDelta;

		if ($minLng < -180.0 || $maxLng > 180.0) {
			$minLng = -180.0;
			$maxLng = 180.0;
		}

		return [
			'minLat' => $minLat,
			'maxLat' => $maxLat,
			'minLng' => $minLng,
			'maxLng' => $maxLng,
		];
	}

	/**
	 * Builds the shared filters used by the radius result and count queries.
	 *
	 * @param  float  $lat      Origin latitude.
	 * @param  float  $lng      Origin longitude.
	 * @param  ?float $radius   Search radius, or null for no distance cutoff.
	 * @param  string $unit     'mi' or 'km'.
	 * @param  int[]  $postIds  Optional post ID whitelist.
	 * @return array            Query fragments and prepared values.
	 */
	private function buildRadiusQueryParts(float $lat, float $lng, ?float $radius, string $unit, array $postIds): array {
		$earthRadius = $unit === 'km' ? 6371.0 : 3959.0;

		$bbox = $radius !== null
			? $this->calculateBoundingBox($lat, $lng, $radius, $earthRadius)
			: ['minLat' => -90.0, 'maxLat' => 90.0, 'minLng' => -180.0, 'maxLng' => 180.0];

		$postIdClause = '';
		$postIdValues = [];

		if (!empty($postIds)) {
			$placeholders = implode(',', array_fill(0, count($postIds), '%d'));
			$postIdClause = "AND post_id IN ({$placeholders})";
			$postIdValues = $postIds;
		}

		$havingClause = $radius !== null ? 'HAVING distance <= %f' : '';
		$havingValues = $radius !== null ? [$radius] : [];

		return [
			'sql' => [
				'postIdClause' => $postIdClause,
				'havingClause' => $havingClause,
			],
			'values' => [
				'distance' => [$earthRadius, $lat, $lng, $lat],
				'table'    => [$this->locationsTable],
				'bbox'     => [$bbox['minLat'], $bbox['maxLat'], $bbox['minLng'], $bbox['maxLng']],
				'postIds'  => $postIdValues,
				'having'   => $havingValues,
			],
		];
	}

	/**
	 * Gets locations within a radius of a coordinate.
	 *
	 * Distance is calculated in SQL using the Haversine formula and results are
	 * returned nearest first. A null radius removes the distance cutoff while
	 * preserving distance sorting.
	 *
	 * When provided, $postIds restricts results to a caller-supplied whitelist.
	 * Post status is not enforced by this method.
	 *
	 * @param  float  $lat      Origin latitude.
	 * @param  float  $lng      Origin longitude.
	 * @param  ?float $radius   Search radius, or null for no distance cutoff.
	 * @param  string $unit     'mi' or 'km'.
	 * @param  int    $limit    Maximum results. 0 for no limit.
	 * @param  int    $offset   Rows to skip.
	 * @param  int[]  $postIds  Optional post ID whitelist.
	 * @return array            Matching locations with coordinates and distance.
	 */
	public function getLocationsInRadius(
		float $lat,
		float $lng,
		?float $radius = null,
		string $unit = 'mi',
		int $limit = 0,
		int $offset = 0,
		array $postIds = []
	): array {
		if ($radius !== null && $radius <= 0) {
			return [];
		}

		if ($limit < 0) {
			return [];
		}

		$lat = max(-90.0, min(90.0, $lat));
		$lng = max(-180.0, min(180.0, $lng));

		$parts = $this->buildRadiusQueryParts($lat, $lng, $radius, $unit, $postIds);

		$limitClause = $limit > 0 ? 'LIMIT %d OFFSET %d' : '';
		$limitValues = $limit > 0 ? [$limit, max(0, $offset)] : [];

		global $wpdb;

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- postIdClause/havingClause hold only %d/%f placeholders generated in buildRadiusQueryParts(); the actual values are bound via prepare() in the array_merge() below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					post_id,
					latitude,
					longitude,
					ROUND(
						%f * ACOS(
							GREATEST(-1, LEAST(1,
								COS(RADIANS(%f)) * COS(RADIANS(latitude)) *
								COS(RADIANS(longitude) - RADIANS(%f)) +
								SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
							))
						),
					2) AS distance
				FROM %i
				WHERE latitude IS NOT NULL
				AND longitude IS NOT NULL
				AND latitude BETWEEN %f AND %f
				AND longitude BETWEEN %f AND %f
				{$parts['sql']['postIdClause']}
				{$parts['sql']['havingClause']}
				ORDER BY distance ASC
				{$limitClause}",
				array_merge(
					$parts['values']['distance'],
					$parts['values']['table'],
					$parts['values']['bbox'],
					$parts['values']['postIds'],
					$parts['values']['having'],
					$limitValues
				)
			),
			ARRAY_A
		);
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return $rows ?: [];
	}

	/**
	 * Counts locations matching a radius search.
	 *
	 * Uses the same filtering and null-radius behavior as getLocationsInRadius().
	 *
	 * @param  float  $lat      Origin latitude.
	 * @param  float  $lng      Origin longitude.
	 * @param  ?float $radius   Search radius, or null for no distance cutoff.
	 * @param  string $unit     'mi' or 'km'.
	 * @param  int[]  $postIds  Optional post ID whitelist.
	 * @return int              Number of matching locations.
	 */
	public function countLocationsInRadius(
		float $lat,
		float $lng,
		?float $radius = null,
		string $unit = 'mi',
		array $postIds = []
	): int {
		if ($radius !== null && $radius <= 0) {
			return 0;
		}

		$lat = max(-90.0, min(90.0, $lat));
		$lng = max(-180.0, min(180.0, $lng));

		$parts = $this->buildRadiusQueryParts($lat, $lng, $radius, $unit, $postIds);

		global $wpdb;

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- postIdClause/havingClause hold only %d/%f placeholders generated in buildRadiusQueryParts(); the actual values are bound via prepare() in the array_merge() below.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT
						ROUND(
							%f * ACOS(
								GREATEST(-1, LEAST(1,
									COS(RADIANS(%f)) * COS(RADIANS(latitude)) *
									COS(RADIANS(longitude) - RADIANS(%f)) +
									SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
								))
							),
						2) AS distance
					FROM %i
					WHERE latitude IS NOT NULL
					AND longitude IS NOT NULL
					AND latitude BETWEEN %f AND %f
					AND longitude BETWEEN %f AND %f
					{$parts['sql']['postIdClause']}
					{$parts['sql']['havingClause']}
				) AS radius_results",
				array_merge(
					$parts['values']['distance'],
					$parts['values']['table'],
					$parts['values']['bbox'],
					$parts['values']['postIds'],
					$parts['values']['having']
				)
			)
		);
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	// =========================================================================
	// Cache management
	// =========================================================================

	/**
	 * Gets the current geocode cache generation.
	 *
	 * The generation is included in search cache keys, allowing all existing
	 * results to be invalidated without deleting individual transients.
	 *
	 * @return int Cache generation number.
	 */
	public function getGeocodeCacheGeneration(): int {
		return (int) get_option(self::GEOCODE_CACHE_GEN_OPTION, 0);
	}

	/**
	 * Invalidates cached geocode search results.
	 *
	 * Advances the cache generation once per request, avoiding redundant option
	 * writes when multiple location changes occur during the same request.
	 *
	 * @return void
	 */
	public function clearGeocodeCache(): void {
		static $advancedThisRequest = false;

		if ($advancedThisRequest) {
			return;
		}

		update_option(self::GEOCODE_CACHE_GEN_OPTION, $this->getGeocodeCacheGeneration() + 1, true);
		$advancedThisRequest = true;
	}
}
