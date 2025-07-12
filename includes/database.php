<?php

class RS_Geocoding_API_Database {
	
	// If you change the database structure below, or add any version-specific actions, you must increment this version number.
	public static $version = '1.0.0';
	
	// Utilities
	
	public static function upgrade_database() {
		$installed_version = get_option( 'rsga_database_version', '0.0.0' );
		
		// Upgrade the database whenever the version changes
		if ( version_compare( $installed_version, self::$version, '!=' ) ) {
			self::setup_database();
		}
		
		// [EXAMPLE]
		// Custom actions when upgrading to a specific version
		// This can be used to do things such as adding new capabilities to users, etc.
		/*
		if ( version_compare( $installed_version, '1.0.2', '<' ) ) {
			$this->install_1_0_2();
		}
		*/
		// [END OF EXAMPLE]
		
		// Save the new version number
		// update_option( 'rsga_database_version', $this->version, true );
	}
	
	/**
	 * Set up the database table
	 */
	protected static function setup_database() {
		global $wpdb;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Create a database table
		$table_name = self::get_table_name();
		
		$sql = <<<MySQL
CREATE TABLE $table_name (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    hits INT(11) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    created_at_gmt DATETIME NOT NULL,
    status VARCHAR(255) NOT NULL,
    address VARCHAR(255) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    formatted_address VARCHAR(255) NOT NULL,
    place_id VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    KEY created_at (created_at),
    KEY address (address)
) $charset_collate
MySQL;
		
		dbDelta( $sql );
		
		$last_error = $wpdb->last_error;
		
		if ( $last_error ) {
			echo '<p>[RS Geocoding API] There was an error creating the database table:</p>';
			echo '<pre>' . $last_error . '</pre>';
			exit;
		}
	}
	
	/**
	 * Get the database table name
	 */
	public static function get_table_name() {
		global $wpdb;
		
		return $wpdb->prefix . 'rsga_geocode';
	}
	
	// Geocoding Functions
	
	/**
	 * Cleans an input address for storage in the database.
	 *
	 * @param string $address The address to clean
	 *
	 * @return string The cleaned address
	 */
	protected static function clean_address( $address ) {
		$address = trim( $address );
		$address = preg_replace( '/\s+/', ' ', $address );
		
		return $address;
	}
	
	/**
	 * Get a geocoded address from the database by ID
	 *
	 * @param int $id The ID of the address to retrieve
	 *
	 * @return array|false The address array, or false if not found {
	 *     @type int $id The ID of the address
	 *     @type int $hits The number of times this address has been geocoded
	 *     @type string $created_at The date and time the address was created
	 *     @type string $created_at_gmt The date and time the address was created in GMT
	 *     @type string $address The address
	 *     @type string $status The status from the geocoded result
	 *     @type float $lat The latitude of the address
	 *     @type float $lng The longitude of the address
	 *     @type string $formatted_address The formatted address
	 *     @type string $place_id The Google Place ID of the address
	 * }
	 */
	public static function get_item_by_id( $id ) {
		global $wpdb;
		
		$table_name = self::get_table_name();
		
		$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id );
		$result = $wpdb->get_row( $query, ARRAY_A );
		
		return $result;
	}
	
	/**
	 * Get a geocoded address from the database by address
	 *
	 * @param string $address The address to retrieve
	 * @param bool $record_hit Whether to record a hit on the address for this request (Default: true)
	 *
	 * @return array|false The address array, or false if not found {
	 *     @type int $id The ID of the address
	 *     @type int $hits The number of times this address has been geocoded
	 *     @type string $created_at The date and time the address was created
	 *     @type string $created_at_gmt The date and time the address was created in GMT
	 *     @type string $address The address
	 *     @type string $status The status from the geocoded result
	 *     @type float $lat The latitude of the address
	 *     @type float $lng The longitude of the address
	 *     @type string $formatted_address The formatted address
	 *     @type string $place_id The Google Place ID of the address
	 * }
	 */
	public static function get_item_by_address( $address, $record_hit = true ) {
		global $wpdb;
		
		$address = self::clean_address( $address );
		
		$table_name = self::get_table_name();
		
		$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE address = %s", $address );
		$result = $wpdb->get_row( $query, ARRAY_A );
		
		// If found, record a hit on the address (regardless if the geocode result is valid)
		if ( $record_hit && $result ) {
			self::increment_hits( $result['id'] );
		}
		
		return $result;
	}
	
	/**
	 * Add or update a geocoded address in the database
	 *
	 * @param string $address The address to add
	 * @param string $status The status from the geocoded result
	 * @param float $lat The latitude of the address
	 * @param float $lng The longitude of the address
	 * @param string $formatted_address The formatted address
	 * @param string $place_id The Google Place ID of the address
	 * @param string|null $created_at Optional date and time the address was created. Defaults to the current time.
	 *
	 * @return int|false The ID of the item that was added, or false on failure
	 */
	public static function update_item( $address, $status, $lat, $lng, $formatted_address, $place_id, $created_at = null ) {
		global $wpdb;
		
		$table_name = self::get_table_name();
		
		$address = self::clean_address( $address );
		
		return $wpdb->insert(
			$table_name,
			array(
				'hits' => 1,
				'created_at' => current_time( 'mysql' ),
				'created_at_gmt' => current_time( 'mysql', 1 ),
				'address' => $address,
				'status' => $status,
				'lat' => $lat,
				'lng' => $lng,
				'formatted_address' => $formatted_address,
				'place_id' => $place_id
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%f', // float
				'%f', // float
				'%s',
				'%s'
			)
		);
	}
	
	/**
	 * Remove an address from the table by ID
	 * @param int $id The ID of the address to remove
	 * @return int|false The number of rows affected, or false on failure
	 */
	public static function remove_item_by_id( $id ) {
		global $wpdb;
		
		$table_name = self::get_table_name();
		
		return $wpdb->delete(
			$table_name,
			array( 'id' => $id )
		);
	}
	
	/**
	 * Remove an address from the table by the address itself
	 * @param string $address The address to remove
	 * @return int|false The number of rows affected, or false on failure
	 */
	public static function remove_item_by_address( $address ) {
		global $wpdb;
		
		$table_name = self::get_table_name();
		
		$address = self::clean_address( $address );
		
		return $wpdb->delete(
			$table_name,
			array( 'address' => $address )
		);
	}
	
	/**
	 * Increment the hit count for an address
	 * @param int $id The ID of the address to increment
	 * @return int|false The number of rows affected, or false on failure
	 */
	public static function increment_hits( $id ) {
		global $wpdb;
		
		$table_name = self::get_table_name();
		
		return $wpdb->query(
			$wpdb->prepare( "UPDATE $table_name SET hits = hits + 1 WHERE id = %d", $id )
		);
	}
	
}