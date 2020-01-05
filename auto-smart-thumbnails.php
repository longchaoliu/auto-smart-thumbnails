<?php

/**
 * Plugin Name: Auto Smart Thumbnails
 * Plugin URI: 
 * Description: Create thumbnails on demand with face detection. Remove unused thumbnails. Free up server storage. 
 * Author: longchaoliu 
 * Author URI: 
 * Text Domain: auto-smart-thumbnails
 * Domain Path: /languages
 * Version: 1.1.0
 */

class AutoSmartThumbnails {

	// Will hold the only instance of our main plugin class
	private static $instance;

	// Instantiate the class and set up stuff
	public static function instantiate() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AutoSmartThumbnails ) ) {

			self::$instance = new AutoSmartThumbnails();
			self::$instance->define_constants();
			self::$instance->include_files();
		}
		return self::$instance;
	}

	public function __construct() {

		// load textdomain
    	add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_link_to_plugin_page' ) );
	}

	// Defines plugin constants
	private function define_constants() {

		// Plugin version
    	if ( ! defined( 'AST_VERSION' ) )
			define( 'AST_VERSION', '0.1' );

		// Plugin Folder Path
    	if ( ! defined( 'AST_PLUGIN_DIR' ) )
			define( 'AST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

		// Plugin Include Path
    	if ( ! defined( 'AST_PLUGIN_DIR_INC' ) )
			define( 'AST_PLUGIN_DIR_INC', AST_PLUGIN_DIR . 'inc/' );

		// Plugin Folder URL
    	if ( ! defined( 'AST_PLUGIN_URL' ) )
			define( 'AST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

		// Plugin JS Folder URL
    	if ( ! defined( 'AST_JS_URL' ) )
			define( 'AST_JS_URL', AST_PLUGIN_URL . 'js/' );

		// Plugin CSS Folder URL
    	if ( ! defined( 'AST_CSS_URL' ) )
			define( 'AST_CSS_URL', AST_PLUGIN_URL . 'css/' );

		// Plugin Root File
    	if ( ! defined( 'AST_PLUGIN_FILE' ) )
			define( 'AST_PLUGIN_FILE', __FILE__ );
	}

	// Includes necessary files
	private function include_files() {

		require_once AST_PLUGIN_DIR_INC . 'class-ast-resize-image.php';

		if ( is_admin() ) {
			require_once AST_PLUGIN_DIR_INC . 'class-ast-remove-image-sizes.php';
		}
	}

	// adds our own links to the plugins table
	public function add_link_to_plugin_page( $links ) {

		$links[] = '<a href="'. esc_url( get_admin_url( null, 'tools.php?page=auto-smart-thumbnails' ) ) .'">' . __( 'Remove Unused Thumbnails', 'auto-smart-thumbnails' ) . '</a>';
		return $links;
	}

	// sets up textdomain
	public static function load_textdomain() {

		$lang_dir = dirname( plugin_basename( AST_PLUGIN_FILE ) ) . '/languages/';
		$lang_dir = trailingslashit( apply_filters( 'st_textdomain_location', $lang_dir ) );

		load_plugin_textdomain( 'auto-smart-thumbnails', false, $lang_dir );
	}
}

AutoSmartThumbnails::instantiate();
