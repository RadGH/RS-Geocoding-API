<?php
/*
Plugin Name: RS Geocoding API
Description: Allows you to perform Geocoding API requests using a Google Geocoding API key. Results are stored in the database for future use of the same address.
Version: 1.2.2
Author: Radley Sustaire
Author URI: https://radleysustaire.com/
GitHub Plugin URI: https://github.com/RadGH/RS-Geocoding-API/
GitHub Branch: master
*/

define( 'RSGA_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'RSGA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'RSGA_VERSION', '1.2.2' );

class RS_Geocoding_API_Plugin {
	
	// Constructor
	public function __construct() {
		
		// Load the plugin after other plugins have loaded, to ensure required plugins are available
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		
		// Add a link to the plugin screen linking to the settings page
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_settings_link' ) );
		
		// When the plugin is activated, set up the post types and refresh permalinks
		register_activation_hook( __FILE__, array( $this, 'plugin_activated' ) );
		
		// After plugin has been activated, install roles and flush permalinks
		add_action( 'admin_init', array( $this, 'after_plugin_activated' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Hooks
	public function init() {
		
		// Check for required plugins
		$missing_plugins = array();
		
		if ( ! class_exists('ACF') ) {
			$missing_plugins[] = 'Advanced Custom Fields PRO';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>RS Geocoding API:</strong> The following plugins are required: '. implode(', ', $missing_plugins) . '.', 'error' );
			return;
		}
		
		// Load the ACF field groups
		require_once( RSGA_PATH . '/assets/acf-fields.php' );
		
		// Load the plugin files
		require_once( RSGA_PATH . '/includes/database.php' );
		require_once( RSGA_PATH . '/includes/settings.php' );
		require_once( RSGA_PATH . '/includes/utility.php' );
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php echo $type; ?> bbearg-crm-notice">
				<?php echo $format ? wpautop($message) : $message; ?>
			</div>
			<?php
		});
	}
	
	/**
	 * Add a link to the settings page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function add_settings_link( $links ) {
		// $settings_link = '<a href="edit.php?post_type=page&page=my-settings">Settings</a>';
		// array_unshift( $links, $settings_link );
		return $links;
	}
	
	/**
	 * When the plugin is activated, set up the post types and refresh permalinks
	 */
	public function plugin_activated() {
		update_option( 'rsga_plugin_activated', 1, true );
	}
	
	/**
	 * After plugin has been activated, install roles and flush permalinks
	 *
	 * @return void
	 */
	public function after_plugin_activated() {
		if ( get_option( 'rsga_plugin_activated' ) ) {
			delete_option( 'rsga_plugin_activated' );
			
			// Install custom database tables
			require_once( RSGA_PATH . '/includes/database.php' );
			RS_Geocoding_API_Database::upgrade_database();
			
			// Flush permalinks
			flush_rewrite_rules();
		}
	}
}

// Initialize the plugin
RS_Geocoding_API_Plugin::get_instance();
