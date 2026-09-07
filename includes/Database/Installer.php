<?php
/**
 * Handles database table creation and version management.
 *
 * @package Locfinder
 */

namespace Locfinder\Database;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles database installation and schema upgrades.
 */
final class Installer {

	/**
	 * Current database schema version.
	 *
	 * Increment when a schema change requires an upgrade.
	 *
	 * @var string
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Option name used to store the installed database version.
	 *
	 * @var string
	 */
	public const OPTION_DB_VERSION = 'locfinder_db_version';

	/**
	 * Creates or updates the database tables and records the schema version.
	 *
	 * The version is updated only after all required tables are verified,
	 * allowing failed installations or upgrades to be retried.
	 *
	 * @return bool True on success, false if the required tables do not exist.
	 */
	public static function install(): bool {
		self::createTables();

		if (!self::tablesExist()) {
			return false;
		}

		self::addCapabilities();
		update_option(self::OPTION_DB_VERSION, self::DB_VERSION, true);

		return true;
	}

	/**
	 * Runs the database installer when the stored schema version is outdated.
	 *
	 * Failed upgrades leave the stored version unchanged and are retried on a
	 * subsequent request.
	 *
	 * @return void
	 */
	public static function maybeUpgrade(): void {
		if (self::DB_VERSION !== get_option(self::OPTION_DB_VERSION)) {
			self::install();
		}
	}

	/**
	 * Checks whether all required database tables exist.
	 *
	 * @return bool True if all required tables exist.
	 */
	public static function tablesExist(): bool {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'locfinder_locations',
			$wpdb->prefix . 'locfinder_location_contacts',
			$wpdb->prefix . 'locfinder_location_hours',
		];

		foreach ($tables as $table) {
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

			if ($found !== $table) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Grants Location capabilities to the configured roles.
	 *
	 * Administrator and editor are included by default. Additional roles can
	 * be provided through the locfinder/capable_roles filter.
	 *
	 * @return void
	 */
	private static function addCapabilities(): void {
		$capabilities = [
			'edit_locfinder_location',
			'read_locfinder_location',
			'delete_locfinder_location',
			'edit_locfinder_locations',
			'edit_others_locfinder_locations',
			'publish_locfinder_locations',
			'read_private_locfinder_locations',
			'delete_locfinder_locations',
			'delete_private_locfinder_locations',
			'delete_published_locfinder_locations',
			'delete_others_locfinder_locations',
			'edit_private_locfinder_locations',
			'edit_published_locfinder_locations',
		];

		/**
		 * Filters which roles can manage Locations.
		 *
		 * @param string[] $roles Role slugs to grant Location capabilities to.
		 */
		$roles = apply_filters('locfinder/capable_roles', ['administrator', 'editor']);

		foreach ($roles as $roleSlug) {
			$role = get_role($roleSlug);

			if (!$role) {
				continue;
			}

			foreach ($capabilities as $cap) {
				$role->add_cap($cap);
			}
		}
	}

	/**
	 * Creates or updates the plugin database tables using dbDelta().
	 *
	 * @return void
	 */
	private static function createTables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charsetCollate = $wpdb->get_charset_collate();
		$locationsTable = $wpdb->prefix . 'locfinder_locations';
		$contactsTable  = $wpdb->prefix . 'locfinder_location_contacts';
		$hoursTable     = $wpdb->prefix . 'locfinder_location_hours';

		$sql = [];

		$sql[] = "CREATE TABLE {$locationsTable} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			address TEXT NULL,
			address_2 VARCHAR(255) NULL,
			city VARCHAR(120) NULL,
			state VARCHAR(120) NULL,
			postal_code VARCHAR(30) NULL,
			country_code VARCHAR(10) NULL,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			place_id VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY city (city),
			KEY state (state),
			KEY postal_code (postal_code),
			KEY country_code (country_code),
			KEY lat_lng (latitude, longitude)
		) {$charsetCollate};";

		$sql[] = "CREATE TABLE {$contactsTable} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			location_id BIGINT UNSIGNED NOT NULL,
			contact_type VARCHAR(50) NOT NULL,
			value VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY location_contact (location_id, contact_type),
			KEY location_id (location_id),
			KEY contact_type (contact_type)
		) {$charsetCollate};";

		$sql[] = "CREATE TABLE {$hoursTable} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			location_id BIGINT UNSIGNED NOT NULL,
			day_of_week TINYINT UNSIGNED NOT NULL,
			open_time TIME NULL,
			close_time TIME NULL,
			is_closed TINYINT(1) NOT NULL DEFAULT 0,
			is_24_hours TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY location_day (location_id, day_of_week),
			KEY location_id (location_id),
			KEY day_of_week (day_of_week),
			KEY is_closed (is_closed),
			KEY is_24_hours (is_24_hours),
			KEY sort_order (sort_order)
		) {$charsetCollate};";

		foreach ($sql as $tableSql) {
			dbDelta($tableSql);
		}
	}
}
