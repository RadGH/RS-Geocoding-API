<?php

class RS_Geocoding_API_Settings {
	
	// Constructor
	public function __construct() {
		
		// Add an ACF settings page for the plugin
		add_action( 'init', array( $this, 'add_acf_settings_page' ) );
		
		// Add Google Maps API Key from Settings
		// add_action( 'acf/init', array( $this, 'add_google_maps_api_key' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	// Hooks
	
	/**
	 * Add an ACF settings page for the plugin
	 */
	public function add_acf_settings_page() {
		if( function_exists('acf_add_options_sub_page') ) {
			acf_add_options_sub_page(array(
				'parent_slug'	=> 'options-general.php',
				'page_title' 	=> 'RS Geocoding API Settings',
				'menu_title'	=> 'RS Geocoding API',
				'menu_slug' 	=> 'rsga-settings',
				'redirect'		=> false,
				'post_id'       => 'rs_geocoding_api', // get_field( 'something', 'rs_geocoding_api' );
			));
		}
	}
	
	/**
	 * Add Google Maps API Key from Settings
	 *
	 * @return void
	 */
	/*
	public function add_google_maps_api_key() {
		// If already provided, do not change the ACF API key
		$acf_key = acf_get_setting( 'google_api_key' );
		if ( $acf_key ) return;
		
		// Get the API key from the settings to use for ACF
		$key = rsga_get_google_maps_api_key();
		if ( $key ) {
			acf_update_setting( 'google_api_key', $key );
		}
	}
	*/
	
}


// Initialize the plugin
RS_Geocoding_API_Settings::get_instance();