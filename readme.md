# RS Geocoding API

This WordPress plugin provides the ability to geocode addresses using the Google Geocoding API. The plugin includes a function to geocode an address and return the latitude, longitude, formatted address, and place ID.

Results are stored in the database to reduce the number of requests to the Google Geocoding API.

This plugin **requires** a Google Geocoding API key. Add it under Settings > RS Geocoding API.

- [View this plugin on ZingMap.com](https://zingmap.com/plugin/rs-geocoding-api/)

### Usage:

```
$item = rsga_geocode_address( $address );

// Output example:

$item = array(
    'lat'               => 123.456,
    'lng'               => 123.456,
    'formatted_address' => '123 Main St, Anytown, USA',
    'place_id'          => '1234567890',
);
```

## Changelog

### 1.2.3
- Added Git Updater support

### 1.2.2
- Initial release
