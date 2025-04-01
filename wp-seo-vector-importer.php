
<?php
/**
 * Plugin Name:       AI向量助手
 * Plugin URI:        https://ohya.co
 * Description:       透過OpenAI的嵌入技術，將WordPress文章轉換為向量資料，提升內容管理和SEO效果。
 * Version:           0.2.0
 * Author:            好事發生數位有限公司
 * Author URI:        https://ohya.co
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
define( 'WP_SEO_VI_VERSION', '0.2.0' );
define( 'WP_SEO_VI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_SEO_VI_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_SEO_VI_DATA_DIR', WP_SEO_VI_PATH . 'data/' ); // 保留此常量以兼容舊資料

// 資料庫類型常量
define( 'WP_SEO_VI_DB_TYPE_SQLITE', 'sqlite' );
define( 'WP_SEO_VI_DB_TYPE_WPDB', 'wpdb' );

/**
 * 初始化外掛資料庫與目錄
 */
function wp_seo_vi_activate() {
    // 確保資料目錄存在 (向後兼容)
    if ( ! file_exists( WP_SEO_VI_DATA_DIR ) ) {
        wp_mkdir_p( WP_SEO_VI_DATA_DIR );
        // 添加 .htaccess 文件防止直接訪問
        $htaccess_content = "Options -Indexes\ndeny from all";
        file_put_contents( WP_SEO_VI_DATA_DIR . '.htaccess', $htaccess_content );
        // 添加 index.php 文件防止目錄列表
        $index_content = "<?php // Silence is golden.";
        file_put_contents( WP_SEO_VI_DATA_DIR . 'index.php', $index_content );
    }
    
    // 設定預設資料庫類型（新安裝預設使用 WordPress 資料庫）
    if ( !get_option( 'wp_seo_vi_db_type' ) ) {
        add_option( 'wp_seo_vi_db_type', WP_SEO_VI_DB_TYPE_WPDB );
    }
    
    // 初始化 WordPress 資料庫表結構
    require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-database.php';
    require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-wp-database.php';
    $wpdb_instance = new WP_SEO_VI_WP_Database();
    $wpdb_instance->initialize_schema();
    
    // 增加版本號以用於後續更新檢查
    update_option( 'wp_seo_vi_version', WP_SEO_VI_VERSION );
}
register_activation_hook( __FILE__, 'wp_seo_vi_activate' );

/**
 * 檢查是否可以使用 SQLite
 */
function wp_seo_vi_can_use_sqlite() {
    return class_exists( 'PDO' ) && in_array( 'sqlite', PDO::getAvailableDrivers() );
}

/**
 * 檢查是否需要資料遷移
 */
function wp_seo_vi_needs_migration() {
    // 如果設定使用 WordPress 資料庫但 SQLite 資料庫存在，則需要遷移
    if ( get_option( 'wp_seo_vi_db_type' ) === WP_SEO_VI_DB_TYPE_WPDB ) {
        $sqlite_file = WP_SEO_VI_DATA_DIR . 'vector_store.db';
        return file_exists( $sqlite_file ) && filesize( $sqlite_file ) > 0;
    }
    return false;
}

/**
 * 取得目前使用的資料庫類型
 */
function wp_seo_vi_get_db_type() {
    $db_type = get_option( 'wp_seo_vi_db_type', WP_SEO_VI_DB_TYPE_WPDB );
    
    // 如果設定為 SQLite 但系統不支援，則切換到 WordPress 資料庫
    if ( $db_type === WP_SEO_VI_DB_TYPE_SQLITE && !wp_seo_vi_can_use_sqlite() ) {
        update_option( 'wp_seo_vi_db_type', WP_SEO_VI_DB_TYPE_WPDB );
        $db_type = WP_SEO_VI_DB_TYPE_WPDB;
    }
    
    return $db_type;
}

/**
 * 取得資料庫操作實例
 */
function wp_seo_vi_get_db_instance() {
    $db_type = wp_seo_vi_get_db_type();
    
    if ( $db_type === WP_SEO_VI_DB_TYPE_SQLITE && wp_seo_vi_can_use_sqlite() ) {
        require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-database.php';
        return new WP_SEO_VI_Database();
    } else {
        require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-wp-database.php';
        return new WP_SEO_VI_WP_Database();
    }
}

/**
 * 更新時檢查是否需要升級資料庫
 */
function wp_seo_vi_check_update() {
    $current_version = get_option( 'wp_seo_vi_version', '0.1.0' );
    
    if ( version_compare( $current_version, WP_SEO_VI_VERSION, '<' ) ) {
        // 如果是從 0.1.x 升級到 0.2.x，添加遷移提示
        if ( version_compare( $current_version, '0.2.0', '<' ) ) {
            add_option( 'wp_seo_vi_show_migration_notice', '1' );
        }
        
        // 更新資料庫結構
        require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-database.php';
        require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-wp-database.php';
        $wpdb_instance = new WP_SEO_VI_WP_Database();
        $wpdb_instance->initialize_schema();
        
        // 更新版本號
        update_option( 'wp_seo_vi_version', WP_SEO_VI_VERSION );
    }
}
add_action( 'plugins_loaded', 'wp_seo_vi_check_update', 5 );

/**
 * 顯示遷移資料提示
 */
function wp_seo_vi_show_migration_notice() {
    // 只在管理介面顯示
    if ( !is_admin() ) {
        return;
    }
    
    // 檢查是否需要顯示遷移提示
    if ( get_option( 'wp_seo_vi_show_migration_notice' ) !== '1' ) {
        return;
    }
    
    // 檢查是否有資料需要遷移
    if ( !wp_seo_vi_needs_migration() ) {
        delete_option( 'wp_seo_vi_show_migration_notice' );
        return;
    }
    
    // 顯示遷移提示
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong><?php _e( 'AI向量助手需要更新資料庫存儲方式', 'wp-seo-vector-importer' ); ?></strong>
        </p>
        <p>
            <?php _e( '為了避免外掛更新時資料丟失，我們建議您將資料從外掛目錄遷移到 WordPress 資料庫。這將使您的向量資料在外掛更新時保持安全。', 'wp-seo-vector-importer' ); ?>
        </p>
        <p>
            <a href="<?php echo admin_url( 'admin.php?page=wp-seo-vi-settings&tab=migration' ); ?>" class="button button-primary">
                <?php _e( '開始資料遷移', 'wp-seo-vector-importer' ); ?>
            </a>
            <a href="#" class="button wp-seo-vi-dismiss-migration-notice">
                <?php _e( '稍後提醒我', 'wp-seo-vector-importer' ); ?>
            </a>
        </p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('.wp-seo-vi-dismiss-migration-notice').on('click', function(e) {
            e.preventDefault();
            $.post(ajaxurl, {
                action: 'wp_seo_vi_dismiss_migration_notice',
                nonce: '<?php echo wp_create_nonce( 'wp_seo_vi_dismiss_migration_notice' ); ?>'
            });
            $(this).closest('.notice').fadeOut();
        });
    });
    </script>
    <?php
}
add_action( 'admin_notices', 'wp_seo_vi_show_migration_notice' );

/**
 * AJAX 處理函數：忽略遷移提示
 */
function wp_seo_vi_ajax_dismiss_migration_notice() {
    check_ajax_referer( 'wp_seo_vi_dismiss_migration_notice', 'nonce' );
    delete_option( 'wp_seo_vi_show_migration_notice' );
    wp_die();
}
add_action( 'wp_ajax_wp_seo_vi_dismiss_migration_notice', 'wp_seo_vi_ajax_dismiss_migration_notice' );

/**
 * 載入外掛文件
 */
// 基本類別
require_once WP_SEO_VI_PATH . 'admin/class-wp-seo-vi-admin-page.php';
require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-openai-api.php';

// 載入資料庫類別 (始終加載 Database 基類，以便 WP_Database 可以繼承它)
require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-database.php';
require_once WP_SEO_VI_PATH . 'includes/class-wp-seo-vi-wp-database.php';

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
