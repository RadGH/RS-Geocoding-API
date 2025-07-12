<?php

/**
 * Get the Google Geocoding API key
 * @return string
 */
function rsga_get_google_maps_api_key() {
	$api_key = get_field( 'google_geocoding_api_key', 'rs_geocoding_api' );
	
	return $api_key;
}

/**
 * Perform a geocoding request for an address. The result will be stored in the database for subsequent requests.
 *
 * @param string $address
 * @param array  &$response
 *
 * @return array|false {
 *      @type float $lat The latitude of the location
 *      @type float $lng The longitude of the location
 *      @type string $formatted_address The formatted address returned by the API
 *      @type string $place_id The place ID of the location
 * }
 */
function rsga_geocode_address( $address, &$response = [] ) {
	// Address must not be empty
	if ( ! $address ) return false;
	
	// Cache: Check if we already stored the location for the given address
	$result = rsga_get_item_by_address( $address );
	
	if ( $result ) {
		$status = $result['status'];
		
		if ( $status === 'OK' ) {
			// Cached value is OK, return the result
			return array(
				'lat'               => $result['lat'],
				'lng'               => $result['lng'],
				'formatted_address' => $result['formatted_address'],
				'place_id'          => $result['place_id'],
				'cached'            => 'cached',
				'cache_time'        => $result['created_at'],
			);
		}else{
			// If status is not OK, the geocoding failed.
			// Try again if the result is 24 hours old or older
			$diff = time() - strtotime( $result['created_at_gmt'] );
			$age_in_hours = $diff / 3600;
			
			if ( $age_in_hours < 24 ) {
				// Result failed in the last 24 hours. Do not try again yet.
				return false;
			}else{
				// Result failed more than 24 hours ago. Clear previous results and try again.
				rsga_remove_item_by_id( $result['id'] );
			}
		}
	}
	
	// The address hasn't been cached yet, so we need to geocode it
	$api_key = rsga_get_google_maps_api_key();
	if ( ! $api_key ) return false;
	
	$url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $address ) . '&key=' . urlencode( $api_key );
	
	// Try to get the geocoding data
	$error_code = null;
	
	try {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			$error_code = 'wp:remote_get_failed';
			throw new Exception( $response->get_error_message() );
		}
		
		// Parse the response
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! $data ) {
			$error_code = 'wp:json_decode_failed';
			throw new Exception( 'Failed to decode JSON response from API' );
		}
		
		$status = $data['status'];
		if ( $status !== 'OK' ) {
			$error_code = 'api:' . ($status ?: 'NULL');
			throw new Exception( 'API Status Invalid: ' . $status );
		}
		
	} catch ( Exception $e ) {
		
		// Cache: Even though we failed to find the location, store the result to prevent repeating API failures
		rsga_update_item( $address, $error_code, 0, 0, $e->getMessage(), '' );
		return false;
		
	}
	
	// API Successfully returned a result for the address
	$result = $data['results'][0];
	
	$lat = $result['geometry']['location']['lat'];
	$lng = $result['geometry']['location']['lng'];
	$formatted_address = $result['formatted_address'];
	$place_id = $result['place_id'];
	
	// Cache: Store the result for future use
	$cache_result = rsga_update_item( $address, $status, $lat, $lng, $formatted_address, $place_id );
	
	return array(
		'lat' => $lat,
		'lng' => $lng,
		'formatted_address' => $formatted_address,
		'place_id' => $place_id,
		'cached' => $cache_result ? 'added' : 'error',
		'cache_time' => $cache_result ? current_time( 'mysql' ) : null,
	);
}

/**
 * Add or update a geocoded item in the database
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
function rsga_update_item( $address, $status, $lat, $lng, $formatted_address, $place_id, $created_at = null ) {
	return RS_Geocoding_API_Database::update_item( $address, $status, $lat, $lng, $formatted_address, $place_id, $created_at );
}

/**
 * Get geocoded item by its ID
 *
 * @param int $id
 *
 * @return array|bool
 */
function rsga_get_item_by_id( $id ) {
	return RS_Geocoding_API_Database::get_item_by_id( $id );
}

/**
 * Get geocoded item by its address
 *
 * @param string $address
 * @param bool $record_hit Whether to record an API hit for this address (Default: true)
 *
 * @return array|bool
 */
function rsga_get_item_by_address( $address, $record_hit = true ) {
	return RS_Geocoding_API_Database::get_item_by_address( $address, $record_hit );
}

/**
 * Remove a geocoded item from the table by ID
 * @param int $id The ID of the address to remove
 * @return int|false The number of rows affected, or false on failure
 */
function rsga_remove_item_by_id( $id ) {
	return RS_Geocoding_API_Database::remove_item_by_id( $id );
}

/**
 * Remove a geocoded item from the table by the address itself
 * @param string $address The address to remove
 * @return int|false The number of rows affected, or false on failure
 */
function remove_item_by_address( $address ) {
	return RS_Geocoding_API_Database::remove_item_by_address( $address );
}