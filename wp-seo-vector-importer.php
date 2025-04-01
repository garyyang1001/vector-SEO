
<?php
/**
 * Plugin Name:       WP SEO Vector Importer
 * Plugin URI:        #
 * Description:       Imports WordPress post data into a vector database using OpenAI embeddings for SEO analysis and features.
 * Version:           0.1.0
 * Author:            Cline (AI Assistant)
 * Author URI:        #
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-seo-vector-importer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants
 */
define( 'WP_SEO_VI_VERSION', '0.1.0' );
define( 'WP_SEO_VI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_SEO_VI_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_SEO_VI_DATA_DIR', WP_SEO_VI_PATH . 'data/' );

/**
 * Create data directory on activation
 */
function wp_seo_vi_activate() {
    if ( ! file_exists( WP_SEO_VI_DATA_DIR ) ) {
        wp_mkdir_p( WP_SEO_VI_DATA_DIR );
        // Add a .htaccess file to prevent direct access
        $htaccess_content = "Options -Indexes\ndeny from all";
        file_put_contents( WP_SEO_VI_DATA_DIR . '.htaccess', $htaccess_content );
        // Add an index.php file to prevent directory listing
        $index_content = "<?php // Silence is golden.";
        file_put_contents( WP_SEO_VI_DATA_DIR . 'index.php', $index_content );
    }
    // Placeholder for potential database schema setup on activation
}
register_activation_hook( __FILE__, 'wp_seo_vi_activate' );

/**
 * Load plugin files
 */
require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-database.php';
require_once WP_SEO_VI_PATH . 'admin/class-wp-seo-vi-admin-page.php';
require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-openai-api.php';

/**
 * Initialize admin functionality
 */
function wp_seo_vi_init() {
    if ( is_admin() ) {
        new WP_SEO_VI_Admin_Page();
    }
}
add_action( 'plugins_loaded', 'wp_seo_vi_init' );


// Add more initialization code here as needed

?>
