<?php
/**
 * Handles the admin page creation and rendering for WP SEO Vector Importer.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// We need WP_List_Table class
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Load the WP_List_Table class file
require_once WP_SEO_VI_PATH . 'admin/class-wp-seo-vi-posts-list-table.php';

class WP_SEO_VI_Admin_Page {

    /**
     * Instance of the database class.
     * @var WP_SEO_VI_Database
     */
    private $db;

    /**
      * Instance of the posts list table.
      * @var WP_SEO_VI_Posts_List_Table|null
      */
    private $posts_list_table;
 
     /**
      * Constructor.
     * Adds hooks for admin menu and potentially assets.
     */
    public function __construct() {
        $this->db = new WP_SEO_VI_Database(); // Instantiate the database handler
        add_action( 'admin_menu', [ $this, 'add_plugin_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] ); // Register settings
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] ); // Enqueue scripts
        add_action( 'wp_ajax_wp_seo_vi_validate_api_key', [ $this, 'ajax_validate_api_key' ] ); // AJAX handler for validation
        add_action( 'wp_ajax_wp_seo_vi_update_vector', [ $this, 'ajax_update_vector' ] ); // AJAX handler for single update
        add_action( 'wp_ajax_wp_seo_vi_delete_vector', [ $this, 'ajax_delete_vector' ] ); // AJAX handler for single delete
        add_action( 'wp_ajax_wp_seo_vi_clear_logs', [ $this, 'ajax_clear_logs' ] ); // AJAX handler for clearing logs
        add_action( 'wp_ajax_wp_seo_vi_process_batch', [ $this, 'ajax_process_batch' ] ); // AJAX handler for batch processing
        
        // 註冊統計和設置頁面的AJAX處理器
        add_action( 'wp_ajax_wp_seo_vi_export_report', [ $this, 'ajax_export_report' ] ); // AJAX處理導出報告
        add_action( 'wp_ajax_wp_seo_vi_cleanup_logs', [ $this, 'ajax_cleanup_logs' ] ); // AJAX處理清理舊的token使用記錄
        
        // 註冊重複文章比對的AJAX處理器
        add_action( 'wp_ajax_wp_seo_vi_start_duplicate_check', [ $this, 'ajax_start_duplicate_check' ] );
        add_action( 'wp_ajax_wp_seo_vi_process_duplicate_check_batch', [ $this, 'ajax_process_duplicate_check_batch' ] );
        add_action( 'wp_ajax_wp_seo_vi_get_check_result', [ $this, 'ajax_get_check_result' ] );
        add_action( 'wp_ajax_wp_seo_vi_get_recent_checks', [ $this, 'ajax_get_recent_checks' ] );
        add_action( 'wp_ajax_wp_seo_vi_cancel_check', [ $this, 'ajax_cancel_check' ] );
        add_action( 'wp_ajax_wp_seo_vi_start_secondary_check', [ $this, 'ajax_start_secondary_check' ] );
        add_action( 'wp_ajax_wp_seo_vi_delete_check_record', [ $this, 'ajax_delete_check_record' ] );
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu structure.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'WP SEO Vector Importer', 'wp-seo-vector-importer' ), // Page title
            __( 'SEO Vector Importer', 'wp-seo-vector-importer' ),    // Menu title
            'manage_options',                                       // Capability required
            'wp-seo-vector-importer',                               // Menu slug
            [ $this, 'display_plugin_admin_page' ],                 // Function to display the page
            'dashicons-database-import',                            // Icon URL
            75                                                      // Position
        );
        
        // 添加子菜單
        add_submenu_page(
            'wp-seo-vector-importer',                              // 父菜單 slug
            __( '向量索引', 'wp-seo-vector-importer' ),             // 頁面標題
            __( '向量索引', 'wp-seo-vector-importer' ),             // 菜單標題
            'manage_options',                                      // 權限
            'wp-seo-vector-importer',                              // 菜單 slug（與父菜單相同，成為默認頁面）
            [ $this, 'display_plugin_admin_page' ]                 // 顯示頁面的函數
        );
        
        // 添加重複文章檢測子菜單
        add_submenu_page(
            'wp-seo-vector-importer',                              // 父菜單 slug
            __( '重複文章比對', 'wp-seo-vector-importer' ),          // 頁面標題
            __( '重複文章比對', 'wp-seo-vector-importer' ),          // 菜單標題
            'manage_options',                                      // 權限
            'wp-seo-vi-duplicate-check',                           // 菜單 slug
            [ $this, 'display_duplicate_check_page' ]              // 顯示頁面的函數
        );
        
        // 添加統計子菜單
        add_submenu_page(
            'wp-seo-vector-importer',                              // 父菜單 slug
            __( 'Token 使用統計', 'wp-seo-vector-importer' ),        // 頁面標題
            __( 'Token 使用統計', 'wp-seo-vector-importer' ),        // 菜單標題
            'manage_options',                                      // 權限
            'wp-seo-vi-statistics',                                // 菜單 slug
            [ $this, 'display_statistics_page' ]                   // 顯示頁面的函數
        );
        
        // 添加設置子菜單
        add_submenu_page(
            'wp-seo-vector-importer',                              // 父菜單 slug
            __( 'API 設定', 'wp-seo-vector-importer' ),             // 頁面標題
            __( 'API 設定', 'wp-seo-vector-importer' ),             // 菜單標題
            'manage_options',                                      // 權限
            'wp-seo-vi-settings',                                  // 菜單 slug
            [ $this, 'display_settings_page' ]                     // 顯示頁面的函數
        );
    }

    /**
     * Render the settings page for this plugin.
     */
    public function display_plugin_admin_page() {
        // Security check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-seo-vector-importer' ) );
        }

        // Prepare the list table
        $this->posts_list_table = new WP_SEO_VI_Posts_List_Table($this->db);
        $this->posts_list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <!-- 簡易說明區塊 -->
            <div class="wp-seo-vi-intro notice notice-info" style="background-color: #f0f6fc; border-left-color: #2271b1; padding: 12px 16px; margin: 20px 0;">
                <h3 style="margin-top: 0;"><?php _e( '什麼是向量索引？', 'wp-seo-vector-importer' ); ?></h3>
                <p><?php _e( '向量索引功能使用 OpenAI 的 text-embedding-3-small 模型將您的文章內容轉換為向量資料，並儲存在本地資料庫中。', 'wp-seo-vector-importer' ); ?></p>
                <p><strong><?php _e( '主要功能：', 'wp-seo-vector-importer' ); ?></strong></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e( '將您的文章標題和全文內容轉換為向量資料，讓文章可以被更精準地理解', 'wp-seo-vector-importer' ); ?></li>
                    <li><?php _e( '提供更貼近讀者需求的相關文章推薦', 'wp-seo-vector-importer' ); ?></li>
                    <li><?php _e( '強化網站內搜尋功能，讓您的內容更容易被訪客找到', 'wp-seo-vector-importer' ); ?></li>
                </ul>
                <p><strong><?php _e( '使用方式：', 'wp-seo-vector-importer' ); ?></strong> <?php _e( '輸入您的 OpenAI API Key，然後點擊「Import/Update All Posts」按鈕或選擇特定文章進行向量索引。處理完成後，您的文章就能被更聰明地搜尋和推薦。', 'wp-seo-vector-importer' ); ?></p>
            </div>

            <!-- Nav tabs could go here if needed -->

            <!-- Section 1: API Key Settings -->
            <div id="wp-seo-vi-api-settings" class="wp-seo-vi-section">
                <h2><?php _e( 'OpenAI API Settings', 'wp-seo-vector-importer' ); ?></h2>
                <form method="post" action="options.php" id="wp-seo-vi-settings-form">
                    <?php
                    // 使用自定义nonce名称避免与其他表单冲突
                    $nonce_field_name = 'wp_seo_vi_options_nonce';
                    echo '<input type="hidden" name="_wpnonce" id="' . $nonce_field_name . '" value="' . wp_create_nonce('wp_seo_vi_options_group-options') . '" />';
                    echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(wp_unslash($_SERVER['REQUEST_URI'])) . '" />';
                    echo '<input type="hidden" name="option_page" value="wp_seo_vi_options_group" />';
                    
                    do_settings_sections( 'wp-seo-vector-importer-settings' ); // Print one or more sections of settings fields.
                    submit_button( __( 'Save API Key', 'wp-seo-vector-importer' ) );
                    ?>
                    <button type="button" id="wp-seo-vi-validate-key-btn" class="button">
                        <?php _e( 'Validate API Key', 'wp-seo-vector-importer' ); ?>
                    </button>
                    <span id="wp-seo-vi-validation-status" style="margin-left: 10px;"></span>
                </form>
            </div>

            <!-- Section 2: Post Indexing -->
            <div id="wp-seo-vi-post-indexing" class="wp-seo-vi-section">
                <h2><?php _e( 'Post Vector Indexing', 'wp-seo-vector-importer' ); ?></h2>
                <!-- Progress bar placeholder -->
                <div id="wp-seo-vi-progress-bar-container" style="display: none; margin-bottom: 15px;">
                     <div style="background-color: #eee; border: 1px solid #ccc; padding: 2px;">
                        <div id="wp-seo-vi-progress-bar" style="background-color: #0073aa; height: 20px; width: 0%; text-align: center; color: white; line-height: 20px;">0%</div>
                    </div>
                    <div id="wp-seo-vi-progress-status" style="margin-top: 5px;"></div>
                </div>

                <!-- Batch operations buttons -->
                <div class="wp-seo-vi-batch-actions" style="margin-bottom: 15px;">
                    <button type="button" id="wp-seo-vi-process-all-btn" class="button button-primary">
                        <?php _e( 'Import/Update All Posts', 'wp-seo-vector-importer' ); ?>
                    </button>
                    <span id="wp-seo-vi-batch-action-status" style="margin-left: 10px;"></span>
                </div>

                <!-- WP_List_Table form -->
                <form id="wp-seo-vi-posts-filter" method="get">
                    <!-- Hidden fields for page slug and other necessary params -->
                    <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                    <?php
                    // Custom nonce field with unique ID to avoid duplicate IDs
                    wp_nonce_field('wp_seo_vi_posts_nonce_action', 'wp_seo_vi_posts_nonce', false);
                    
                    // Display search box, filters, and table
                    $this->posts_list_table->search_box( __( 'Search Posts', 'wp-seo-vector-importer' ), 'wp-seo-vi-search-input' );
                    $this->posts_list_table->display();
                    ?>
                </form>
            </div>

             <!-- Section 3: Error Log -->
            <div id="wp-seo-vi-error-log" class="wp-seo-vi-section">
                <h2><?php _e( 'Error Log', 'wp-seo-vector-importer' ); ?></h2>
                <div id="wp-seo-vi-log-container">
                    <?php $this->display_error_log_table(); ?>
                </div>
                 <button type="button" id="wp-seo-vi-clear-logs-btn" class="button button-secondary">
                    <?php _e( 'Clear Error Log', 'wp-seo-vector-importer' ); ?>
                </button>
                 <span id="wp-seo-vi-clear-log-status" style="margin-left: 10px;"></span>
            </div>

        </div> <!-- .wrap -->
        <?php
    }

    // 已移除重複的 enqueue_admin_assets 方法

    /**
     * Register plugin settings using the Settings API.
     */
    public function register_settings() {
        register_setting(
            'wp_seo_vi_options_group',          // Option group
            'wp_seo_vi_openai_api_key',         // Option name
            [ $this, 'sanitize_api_key' ]       // Sanitize callback
        );

        add_settings_section(
            'wp_seo_vi_api_key_section',        // ID
            '',                                 // Title (optional)
            null,                               // Callback (optional)
            'wp-seo-vector-importer-settings'   // Page slug where section is shown
        );

        add_settings_field(
            'wp_seo_vi_openai_api_key_field',   // ID
            __( 'OpenAI API Key', 'wp-seo-vector-importer' ), // Title
            [ $this, 'render_api_key_field' ],  // Callback to render the field
            'wp-seo-vector-importer-settings',  // Page slug
            'wp_seo_vi_api_key_section'         // Section ID
        );
    }

    /**
     * Sanitize the API key input.
     *
     * @param string $input The input string.
     * @return string Sanitized string.
     */
    public function sanitize_api_key( $input ) {
        // Basic sanitization, more specific checks might be needed depending on key format
        return sanitize_text_field( trim( $input ) );
    }

    /**
     * Render the API Key input field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'wp_seo_vi_openai_api_key', '' );
        ?>
        <input type='password' name='wp_seo_vi_openai_api_key' value='<?php echo esc_attr( $api_key ); ?>' class='regular-text' autocomplete='off'>
        <p class="description"><?php _e( 'Enter your OpenAI API key. It will be stored securely.', 'wp-seo-vector-importer' ); ?></p>
        <?php
    }

    /**
     * AJAX handler for validating the OpenAI API Key.
     */
    public function ajax_validate_api_key() {
        check_ajax_referer( 'wp_seo_vi_validate_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-seo-vector-importer' ), 403 );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        if ( empty( $api_key ) ) {
            // Try getting the saved key if none was provided in the request
            $api_key = get_option( 'wp_seo_vi_openai_api_key', '' );
            if ( empty( $api_key ) ) {
                 wp_send_json_error( __( 'API Key is empty.', 'wp-seo-vector-importer' ) );
            }
        }

        $openai_api = new WP_SEO_VI_OpenAI_API( $api_key );
        $result = $openai_api->validate_api_key();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( __( 'API Key is valid!', 'wp-seo-vector-importer' ) );
        }
    }

    /**
     * AJAX handler for updating a single post's vector.
     */
    public function ajax_update_vector() {
        check_ajax_referer( 'wp_seo_vi_process_nonce', 'nonce' ); // Use process nonce for update/delete actions

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-seo-vector-importer' ), 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( $post_id <= 0 ) {
            wp_send_json_error( __( 'Invalid Post ID.', 'wp-seo-vector-importer' ) );
        }

        $api_key = get_option( 'wp_seo_vi_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'OpenAI API Key is not configured.', 'wp-seo-vector-importer' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'post' ) { // Adjust 'post' if supporting other types
             wp_send_json_error( __( 'Post not found or invalid type.', 'wp-seo-vector-importer' ) );
        }

        // Prepare text for embedding - removed excerpt field
        $title = $post->post_title;
        $content = $post->post_content;
        // Simple concatenation with title and full article content
        $text_to_embed = $title . "\n\n" . strip_shortcodes( strip_tags( $content ) );
        $text_to_embed = trim( $text_to_embed );

        if ( empty( $text_to_embed ) ) {
             $this->db->log_error( $post_id, 'Post content (title, excerpt, content) is empty. Cannot generate vector.' );
             wp_send_json_error( __( 'Post content is empty, cannot generate vector.', 'wp-seo-vector-importer' ) );
        }

        $openai_api = new WP_SEO_VI_OpenAI_API( $api_key );
        $embedding_result = $openai_api->get_embedding( $text_to_embed );

        if ( is_wp_error( $embedding_result ) ) {
            $error_message = $embedding_result->get_error_message();
            $this->db->log_error( $post_id, 'OpenAI API Error: ' . $error_message );
            wp_send_json_error( sprintf( __( 'OpenAI API Error: %s', 'wp-seo-vector-importer' ), $error_message ) );
        } else {
            // Assuming get_embedding returns the vector array directly for single input
            $vector_json = wp_json_encode( $embedding_result );
            if ( $vector_json === false ) {
                 $this->db->log_error( $post_id, 'Failed to JSON encode the vector.' );
                 wp_send_json_error( __( 'Failed to encode vector data.', 'wp-seo-vector-importer' ) );
            }
            
            // 獲取文章URL
            $post_url = get_permalink($post_id);
            
            // 獲取文章分類
            $categories = get_the_category($post_id);
            $category_names = array();
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $category_names[] = $category->name;
                }
            }
            $categories_json = wp_json_encode($category_names);

            $save_result = $this->db->insert_or_update_vector( $post_id, $vector_json, $post_url, $categories_json );

            if ( $save_result ) {
                wp_send_json_success( [
                    'message' => __( 'Vector updated successfully!', 'wp-seo-vector-importer' ),
                    'post_id' => $post_id,
                    'new_status_html' => $this->get_vector_status_html( current_time( 'mysql', 1 ) ) // Get updated HTML for the status column
                 ] );
            } else {
                 // Error already logged by insert_or_update_vector
                 wp_send_json_error( __( 'Failed to save vector to database.', 'wp-seo-vector-importer' ) );
            }
        }
    }

     /**
     * Helper function to generate HTML for the vector status column.
     * Used by AJAX response to update the table row dynamically.
     *
     * @param string|false $status_timestamp MySQL timestamp or false.
     * @return string HTML output.
     */
    private function get_vector_status_html( $status_timestamp ) {
         if ( ! empty( $status_timestamp ) ) {
            $timestamp = strtotime( $status_timestamp );
            $time_diff = human_time_diff( $timestamp, current_time( 'timestamp', 1 ) );
            $formatted_date = sprintf( '%s (%s ago)', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ), $time_diff );
            return sprintf( '<span style="color:green;">%s %s</span>', __( 'Indexed:', 'wp-seo-vector-importer' ), $formatted_date );
        } else {
            return sprintf( '<span style="color:orange;">%s</span>', __( 'Not Indexed', 'wp-seo-vector-importer' ) );
        }
    }


    /**
     * AJAX handler for batch processing vectors.
     * 
     * This handles both 'all posts' and 'selected posts' scenarios.
     * The function processes a small batch (e.g., 5-10) of posts at a time to avoid timeouts.
     */
    public function ajax_process_batch() {
        check_ajax_referer( 'wp_seo_vi_process_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-seo-vector-importer' ), 403 );
        }

        // Get the API key once for all posts in this batch
        $api_key = get_option( 'wp_seo_vi_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'OpenAI API Key is not configured.', 'wp-seo-vector-importer' ) );
        }

        // We expect an array of post IDs, current position, and total count
        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) json_decode( wp_unslash( $_POST['post_ids'] ) ) ) : [];
        $current_position = isset( $_POST['position'] ) ? intval( $_POST['position'] ) : 0;
        $total_count = isset( $_POST['total'] ) ? intval( $_POST['total'] ) : count( $post_ids );
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 5; // Process 5 posts at a time by default

        if ( empty( $post_ids ) ) {
            wp_send_json_error( __( 'No posts selected for processing.', 'wp-seo-vector-importer' ) );
        }

        // Process only a small batch at a time to prevent timeouts
        $batch = array_slice( $post_ids, $current_position, $batch_size );
        if ( empty( $batch ) ) {
            // All posts processed - send complete status
            wp_send_json_success( [
                'done' => true,
                'processed' => $current_position,
                'total' => $total_count,
                'message' => sprintf( 
                    __( 'Completed processing %d/%d posts.', 'wp-seo-vector-importer' ), 
                    $current_position, 
                    $total_count 
                )
            ] );
        }

        // Create OpenAI API instance once for all posts in this batch
        $openai_api = new WP_SEO_VI_OpenAI_API( $api_key );
        
        // Process this batch
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ( $batch as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== 'post' ) { // Skip invalid posts
                $this->db->log_error( $post_id, 'Post not found or invalid type.' );
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post_id,
                    'message' => __( 'Post not found or invalid type.', 'wp-seo-vector-importer' )
                ];
                continue;
            }

            // Prepare text for embedding - removed excerpt field
            $title = $post->post_title;
            $content = $post->post_content;
            $text_to_embed = $title . "\n\n" . strip_shortcodes( strip_tags( $content ) );
            $text_to_embed = trim( $text_to_embed );

            if ( empty( $text_to_embed ) ) {
                $this->db->log_error( $post_id, 'Post content is empty. Cannot generate vector.' );
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post_id,
                    'message' => __( 'Post content is empty.', 'wp-seo-vector-importer' )
                ];
                continue;
            }

            $embedding_result = $openai_api->get_embedding( $text_to_embed );

            if ( is_wp_error( $embedding_result ) ) {
                $error_message = $embedding_result->get_error_message();
                $this->db->log_error( $post_id, 'OpenAI API Error: ' . $error_message );
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post_id,
                    'message' => sprintf( __( 'OpenAI API Error: %s', 'wp-seo-vector-importer' ), $error_message )
                ];
                continue;
            }

            // Convert to JSON
            $vector_json = wp_json_encode( $embedding_result );
            if ( $vector_json === false ) {
                $this->db->log_error( $post_id, 'Failed to JSON encode the vector.' );
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post_id,
                    'message' => __( 'Failed to encode vector data.', 'wp-seo-vector-importer' )
                ];
                continue;
            }

            // 獲取文章URL
            $post_url = get_permalink($post_id);
            
            // 獲取文章分類
            $categories = get_the_category($post_id);
            $category_names = array();
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $category_names[] = $category->name;
                }
            }
            $categories_json = wp_json_encode($category_names);
            
            // 保存到資料庫
            $save_result = $this->db->insert_or_update_vector( $post_id, $vector_json, $post_url, $categories_json );
            if ( $save_result ) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post_id,
                    'message' => __( 'Failed to save vector to database.', 'wp-seo-vector-importer' )
                ];
            }
        }

        // Calculate new position
        $new_position = $current_position + count( $batch );
        $progress = ( $new_position / $total_count ) * 100;
        
        // Return progress information and results for this batch
        wp_send_json_success( [
            'done' => false,
            'position' => $new_position,
            'processed' => $new_position,
            'total' => $total_count,
            'progress' => round( $progress, 1 ),
            'success' => $results['success'],
            'failed' => $results['failed'],
            'errors' => $results['errors'],
            'message' => sprintf( 
                __( 'Processed %1$d/%2$d posts (%3$d%%). Success: %4$d, Failed: %5$d', 'wp-seo-vector-importer' ),
                $new_position,
                $total_count,
                round( $progress ),
                $results['success'],
                $results['failed']
            )
        ] );
    }

    /**
     * AJAX handler for deleting a single post's vector.
     */
    public function ajax_delete_vector() {
        check_ajax_referer( 'wp_seo_vi_delete_nonce', 'nonce' ); // Use delete nonce

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-seo-vector-importer' ), 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( $post_id <= 0 ) {
            wp_send_json_error( __( 'Invalid Post ID.', 'wp-seo-vector-importer' ) );
        }

        $delete_result = $this->db->delete_vector( $post_id );

        if ( $delete_result ) {
             wp_send_json_success( [
                'message' => __( 'Vector deleted successfully!', 'wp-seo-vector-importer' ),
                'post_id' => $post_id,
                'new_status_html' => $this->get_vector_status_html( false ) // Get HTML for "Not Indexed"
             ] );
        } else {
             // Failure might mean it didn't exist or DB error (error logged in delete_vector)
             wp_send_json_error( __( 'Failed to delete vector (it might not have existed or a database error occurred).', 'wp-seo-vector-importer' ) );
        }
    }

    /**
     * Display the error log table.
     */
    private function display_error_log_table() {
        $logs = $this->db->get_error_logs( 50 ); // Get latest 50 logs

        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'No errors logged yet.', 'wp-seo-vector-importer' ) . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 50px;"><?php _e( 'Post ID', 'wp-seo-vector-importer' ); ?></th>
                    <th scope="col"><?php _e( 'Error Message', 'wp-seo-vector-importer' ); ?></th>
                    <th scope="col" style="width: 180px;"><?php _e( 'Timestamp (GMT)', 'wp-seo-vector-importer' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo $log['post_id'] ? esc_html( $log['post_id'] ) : '-'; ?></td>
                        <td><?php echo esc_html( $log['error_message'] ); ?></td>
                        <td><?php echo esc_html( $log['timestamp'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
         // Add pagination if needed later based on get_error_log_count()
    }


    /**
     * AJAX handler for clearing the error log.
     */
    public function ajax_clear_logs() {
         check_ajax_referer( 'wp_seo_vi_clear_log_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-seo-vector-importer' ), 403 );
        }

        $clear_result = $this->db->clear_error_logs();

        if ( $clear_result ) {
            wp_send_json_success( __( 'Error log cleared successfully!', 'wp-seo-vector-importer' ) );
        } else {
            wp_send_json_error( __( 'Failed to clear error log.', 'wp-seo-vector-importer' ) );
        }
    }
    
    /**
     * 顯示Token使用統計頁面
     */
    public function display_statistics_page() {
        // 安全檢查
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有足夠權限訪問此頁面。', 'wp-seo-vector-importer'));
        }
        
        // 獲取過濾參數
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 12;
        
        // 獲取統計數據
        $statistics = $this->db->get_usage_statistics($period, $limit);
        $current_month_stats = $this->db->get_current_month_usage();
        
        // 如果返回false或空陣列，設置一個默認的空數據
        if ($current_month_stats === false) {
            $current_month_stats = ['total_tokens' => 0, 'total_cost' => 0];
        }
        
        // 獲取預算設置
        $budget_limit = floatval($this->db->get_setting('budget_limit', '0'));
        
        // 載入統計頁面模板
        include(WP_SEO_VI_PATH . 'admin/partials/statistics-page.php');
    }
    
    /**
     * 顯示OpenAI API設定頁面
     */
    public function display_settings_page() {
        // 安全檢查
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有足夠權限訪問此頁面。', 'wp-seo-vector-importer'));
        }
        
        // 處理表單提交
        if (isset($_POST['wp_seo_vi_settings_submit']) && check_admin_referer('wp_seo_vi_settings_nonce')) {
            // 處理和保存設置
            // 1. 向量嵌入設置
            $token_rate = isset($_POST['wp_seo_vi_token_rate']) ? floatval($_POST['wp_seo_vi_token_rate']) : 0.15;
            $batch_size = isset($_POST['wp_seo_vi_batch_size']) ? intval($_POST['wp_seo_vi_batch_size']) : 5;
            
            // 2. GPT-4o-mini設置
            $use_batch_api = isset($_POST['wp_seo_vi_use_batch_api']) ? 'yes' : 'no';
            $duplicate_threshold = isset($_POST['wp_seo_vi_duplicate_threshold']) ? floatval($_POST['wp_seo_vi_duplicate_threshold']) : 0.7;
            $duplicate_model = isset($_POST['wp_seo_vi_duplicate_model']) ? sanitize_text_field($_POST['wp_seo_vi_duplicate_model']) : 'vector';
            
            // 3. 預算控制設置
            $budget_limit = isset($_POST['wp_seo_vi_budget_limit']) ? floatval($_POST['wp_seo_vi_budget_limit']) : 0;
            $enforce_budget_limit = isset($_POST['wp_seo_vi_enforce_budget_limit']) ? 'yes' : 'no';
            
            // 保存基本設置到WordPress選項
            update_option('wp_seo_vi_token_rate', $token_rate);
            update_option('wp_seo_vi_budget_limit', $budget_limit);
            update_option('wp_seo_vi_enforce_budget_limit', $enforce_budget_limit);
            update_option('wp_seo_vi_batch_size', $batch_size);
            
            // 保存GPT-4o-mini設置
            update_option('wp_seo_vi_use_batch_api', $use_batch_api);
            update_option('wp_seo_vi_duplicate_threshold', $duplicate_threshold);
            update_option('wp_seo_vi_duplicate_model', $duplicate_model);
            
            // 同時更新數據庫中的設置
            $this->db->update_setting('token_rate', $token_rate);
            $this->db->update_setting('budget_limit', $budget_limit);
            $this->db->update_setting('enforce_budget_limit', $enforce_budget_limit);
            $this->db->update_setting('batch_size', $batch_size);
            $this->db->update_setting('use_batch_api', $use_batch_api);
            $this->db->update_setting('duplicate_threshold', $duplicate_threshold);
            $this->db->update_setting('duplicate_model', $duplicate_model);
            
            // 顯示成功消息
            add_settings_error(
                'wp_seo_vi_settings',
                'settings_updated',
                __('設定已成功保存。', 'wp-seo-vector-importer'),
                'updated'
            );
        }
        
        // 載入設置頁面模板
        include(WP_SEO_VI_PATH . 'admin/partials/settings-page.php');
    }
    
    /**
     * 更新管理資產載入邏輯，包括統計頁面和設置頁面的腳本和樣式
     * 
     * @param string $hook 當前管理頁面的鉤子
     */
    /**
     * AJAX處理函數：刪除比對紀錄
     */
    public function ajax_delete_check_record() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $check_id = isset($_POST['check_id']) ? intval($_POST['check_id']) : 0;
        
        if ($check_id <= 0) {
            wp_send_json_error(__('無效的檢查ID。', 'wp-seo-vector-importer'));
        }
        
        // 刪除比對記錄及相關數據
        $delete_result = $this->db->delete_duplicate_check($check_id);
        
        if ($delete_result) {
            wp_send_json_success([
                'message' => __('比對記錄已成功刪除', 'wp-seo-vector-importer')
            ]);
        } else {
            wp_send_json_error(__('刪除比對記錄失敗', 'wp-seo-vector-importer'));
        }
    }
    
    /**
     * AJAX處理函數：取消重複文章比對
     */
    public function ajax_cancel_check() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        // 改為 "cancelled" 狀態
        $check_id = get_transient('wp_seo_vi_current_check_id');
        $batch_id = get_transient('wp_seo_vi_current_batch_id');
        
        if ($check_id) {
            // 更新資料庫中的狀態
            $this->db->update_duplicate_check_status($check_id, 'cancelled');
            
            // 取消 OpenAI 的請求 (如果有 batch_id)
            if ($batch_id) {
                $api_key = get_option('wp_seo_vi_openai_api_key', '');
                if (!empty($api_key)) {
                    $openai_api = new WP_SEO_VI_OpenAI_API($api_key);
                    $openai_api->cancel_batch_requests($batch_id);
                }
            }
            
            // 刪除暫存
            delete_transient('wp_seo_vi_current_check_id');
            delete_transient('wp_seo_vi_current_batch_id');
            
            wp_send_json_success([
                'message' => __('比對已取消，包括 OpenAI 端的請求', 'wp-seo-vector-importer')
            ]);
        } else {
            wp_send_json_error(__('找不到進行中的比對任務', 'wp-seo-vector-importer'));
        }
    }
    
    /**
     * AJAX處理函數：開始二次比對
     */
    public function ajax_start_secondary_check() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $post_ids_json = isset($_POST['post_ids']) ? wp_unslash($_POST['post_ids']) : '[]';
        $post_ids = json_decode($post_ids_json, true);
        $threshold = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 0.7;
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt';
        
        if (empty($post_ids)) {
            wp_send_json_error(__('未選擇文章進行二次比對。', 'wp-seo-vector-importer'));
        }
        
        if ($threshold < 0.5 || $threshold > 1.0) {
            wp_send_json_error(__('相似度閾值必須介於0.5和1.0之間。', 'wp-seo-vector-importer'));
        }
        
        // 創建一個新的檢測記錄
        $batch_id = uniqid('secondary_check_');
        $check_id = $this->db->create_duplicate_check($threshold, $model, $batch_id);
        
        if (!$check_id) {
            wp_send_json_error(__('無法創建檢測記錄。', 'wp-seo-vector-importer'));
        }
        
        // 設置當前檢測的暫存
        set_transient('wp_seo_vi_current_check_id', $check_id, 3600);
        
        wp_send_json_success([
            'message' => __('開始二次比對...', 'wp-seo-vector-importer'),
            'check_id' => $check_id,
            'batch_id' => $batch_id,
            'total_posts' => count($post_ids),
            'post_ids' => $post_ids,
            'threshold' => $threshold,
            'model' => $model
        ]);
    }
    
    public function enqueue_admin_assets($hook) {
        // 判斷是否為插件頁面
        $plugin_pages = [
            'toplevel_page_wp-seo-vector-importer',
            'seo-vector-importer_page_wp-seo-vi-statistics',
            'seo-vector-importer_page_wp-seo-vi-settings',
            'seo-vector-importer_page_wp-seo-vi-duplicate-check'  // 添加重複文章比對頁面
        ];
        
        if (!in_array($hook, $plugin_pages)) {
            return;
        }
        
        // 基本管理腳本
        wp_enqueue_script(
            'wp-seo-vi-admin-script',
            WP_SEO_VI_URL . 'admin/js/wp-seo-vi-admin.js',
            ['jquery'],
            WP_SEO_VI_VERSION,
            true
        );
        
        // 統計頁面 - 添加Chart.js
        if ($hook === 'seo-vector-importer_page_wp-seo-vi-statistics') {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                [],
                '3.9.1',
                true
            );
            
            wp_enqueue_script(
                'wp-seo-vi-statistics',
                WP_SEO_VI_URL . 'admin/js/wp-seo-vi-statistics.js',
                ['jquery', 'chartjs'],
                WP_SEO_VI_VERSION,
                true
            );
            
            // 將統計數據傳遞給JavaScript
            $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
            $statistics = $this->db->get_usage_statistics($period, isset($_GET['limit']) ? intval($_GET['limit']) : 12);
            
            $chart_labels = [];
            $token_data = [];
            $cost_data = [];
            
            if ($statistics) {
                foreach ($statistics as $stat) {
                    if ($period === 'day') {
                        $chart_labels[] = "{$stat['year']}-{$stat['month']}-{$stat['day']}";
                    } elseif ($period === 'month') {
                        $chart_labels[] = "{$stat['year']}-{$stat['month']}";
                    } else {
                        $chart_labels[] = $stat['year'];
                    }
                    
                    $token_data[] = intval($stat['total_tokens']);
                    $cost_data[] = floatval($stat['total_cost']);
                }
            }
            
            // 反轉數據，使其按時間順序顯示
            $chart_labels = array_reverse($chart_labels);
            $token_data = array_reverse($token_data);
            $cost_data = array_reverse($cost_data);
            
            wp_localize_script('wp-seo-vi-statistics', 'wpSeoViStats', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'export_nonce' => wp_create_nonce('wp_seo_vi_export_nonce'),
                'cleanup_nonce' => wp_create_nonce('wp_seo_vi_cleanup_nonce'),
                'chart' => [
                    'labels' => $chart_labels,
                    'tokenData' => $token_data,
                    'costData' => $cost_data,
                    'period' => $period
                ],
                'messages' => [
                    'exportSuccess' => __('導出成功！正在下載文件...', 'wp-seo-vector-importer'),
                    'exportError' => __('導出失敗：', 'wp-seo-vector-importer'),
                    'cleanupSuccess' => __('舊的使用記錄已清理！', 'wp-seo-vector-importer'),
                    'cleanupError' => __('清理失敗：', 'wp-seo-vector-importer'),
                    'confirmCleanup' => __('確定要清理90天以前的過期資料嗎？此操作無法撤消。', 'wp-seo-vector-importer')
                ]
            ]);
        }
        
        // 設置頁面
        if ($hook === 'seo-vector-importer_page_wp-seo-vi-settings') {
            wp_enqueue_script(
                'wp-seo-vi-settings',
                WP_SEO_VI_URL . 'admin/js/wp-seo-vi-settings.js',
                ['jquery'],
                WP_SEO_VI_VERSION,
                true
            );
            
            wp_localize_script('wp-seo-vi-settings', 'wpSeoViSettings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'cleanup_nonce' => wp_create_nonce('wp_seo_vi_cleanup_nonce'),
                'messages' => [
                    'cleanupSuccess' => __('舊的使用記錄已清理！', 'wp-seo-vector-importer'),
                    'cleanupError' => __('清理失敗：', 'wp-seo-vector-importer'),
                    'confirmCleanup' => __('確定要清理90天之前的使用記錄嗎？此操作無法撤消。', 'wp-seo-vector-importer')
                ]
            ]);
        }
        
        // 重複文章比對頁面
        if ($hook === 'seo-vector-importer_page_wp-seo-vi-duplicate-check') {
            wp_enqueue_script(
                'wp-seo-vi-duplicate-check',
                WP_SEO_VI_URL . 'admin/js/wp-seo-vi-duplicate-check.js',
                ['jquery'],
                WP_SEO_VI_VERSION,
                true
            );
            
            wp_localize_script('wp-seo-vi-duplicate-check', 'wpSeoViDuplicate', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'duplicate_check_nonce' => wp_create_nonce('wp_seo_vi_duplicate_check_nonce'),
                'processing_text' => __('處理中...', 'wp-seo-vector-importer'),
                'check_failed_text' => __('比對失敗', 'wp-seo-vector-importer'),
                'check_complete_text' => __('比對完成！', 'wp-seo-vector-importer'),
                'loading_text' => __('載入中...', 'wp-seo-vector-importer'),
                'load_failed_text' => __('載入結果失敗', 'wp-seo-vector-importer'),
                'no_duplicates_text' => __('未找到重複文章。', 'wp-seo-vector-importer'),
                'duplicate_group_text' => __('重複文章組', 'wp-seo-vector-importer'),
                'articles_text' => __('篇文章', 'wp-seo-vector-importer'),
                'similarity_text' => __('相似度', 'wp-seo-vector-importer'),
                'reference_article_text' => __('參考文章', 'wp-seo-vector-importer'),
                // 取消功能相關文本
                'cancel_confirm_text' => __('確定要取消比對操作嗎？', 'wp-seo-vector-importer'),
                'cancelling_text' => __('正在取消...', 'wp-seo-vector-importer'),
                'cancelled_text' => __('比對已取消', 'wp-seo-vector-importer'),
                // 二次比對相關文本
                'no_posts_selected_text' => __('未選擇要比對的文章', 'wp-seo-vector-importer'),
                'recheck_text' => __('二次比對', 'wp-seo-vector-importer'),
                'secondary_check_title' => __('選擇模式進行二次比對', 'wp-seo-vector-importer'),
                // 結果顯示相關文本
                'check_result_title' => __('重複文章比對結果', 'wp-seo-vector-importer'),
                'check_info_text' => __('比對資訊', 'wp-seo-vector-importer'),
                'time_text' => __('比對時間', 'wp-seo-vector-importer'),
                'threshold_text' => __('相似度閾值', 'wp-seo-vector-importer'),
                'model_text' => __('使用模型', 'wp-seo-vector-importer'),
                'total_articles_text' => __('檢查文章數', 'wp-seo-vector-importer'),
                'duplicate_groups_text' => __('找到重複組數', 'wp-seo-vector-importer'),
                'vector_mode_text' => __('快速模式', 'wp-seo-vector-importer'),
                'hybrid_mode_text' => __('智慧模式', 'wp-seo-vector-importer'),
                'gpt_mode_text' => __('精準模式', 'wp-seo-vector-importer')
            ]);
        }
        
        // 基本管理頁面
        if ($hook === 'toplevel_page_wp-seo-vector-importer') {
            // Pass data to script, like AJAX URL and nonces
            wp_localize_script('wp-seo-vi-admin-script', 'wpSeoVi', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'validate_nonce' => wp_create_nonce('wp_seo_vi_validate_nonce'),
                'process_nonce' => wp_create_nonce('wp_seo_vi_process_nonce'),
                'delete_nonce' => wp_create_nonce('wp_seo_vi_delete_nonce'),
                'clear_log_nonce' => wp_create_nonce('wp_seo_vi_clear_log_nonce'),
                'validating_text' => __('驗證中...', 'wp-seo-vector-importer'),
                'validation_success_text' => __('API Key 有效！', 'wp-seo-vector-importer'),
                'validation_error_text' => __('驗證失敗：', 'wp-seo-vector-importer'),
                'processing_text' => __('處理中...', 'wp-seo-vector-importer'),
                'deleting_text' => __('刪除中...', 'wp-seo-vector-importer'),
                'confirm_delete' => __('確定要刪除該文章的向量嗎？', 'wp-seo-vector-importer'),
                'confirm_bulk_delete' => __('確定要刪除所選文章的向量嗎？', 'wp-seo-vector-importer'),
                'confirm_bulk_update' => __('確定要更新所選文章的向量嗎？', 'wp-seo-vector-importer'),
                'confirm_clear_logs' => __('確定要清除所有錯誤日誌嗎？', 'wp-seo-vector-importer'),
                'error_text' => __('發生錯誤。', 'wp-seo-vector-importer'),
                'update_vector_text' => __('更新向量', 'wp-seo-vector-importer'),
                'delete_vector_text' => __('刪除向量', 'wp-seo-vector-importer'),
                'deleted_text' => __('已刪除', 'wp-seo-vector-importer'),
                'no_posts_found_text' => __('未找到要處理的文章。', 'wp-seo-vector-importer'),
                'start_batch_text' => __('開始批量處理...', 'wp-seo-vector-importer'),
                'prepare_process_text' => __('正在準備處理文章...', 'wp-seo-vector-importer'),
                'api_key_required_text' => __('請先輸入並保存您的 OpenAI API Key。', 'wp-seo-vector-importer'),
                'batch_confirm_text' => __('這將為表中顯示的所有 %d 篇文章導入/更新向量。繼續？', 'wp-seo-vector-importer'),
                'processing_complete_text' => __('處理完成！刷新頁面以查看更新的向量狀態？', 'wp-seo-vector-importer'),
                'select_posts_text' => __('請至少選擇一篇文章。', 'wp-seo-vector-importer'),
                'no_errors_text' => __('尚無錯誤記錄。', 'wp-seo-vector-importer'),
                'batch_size' => intval($this->db->get_setting('batch_size', '5'))
            ]);
        }
    }
    
    /**
     * AJAX處理函數：導出統計數據為CSV
     */
    public function ajax_export_report() {
        check_ajax_referer('wp_seo_vi_export_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        
        $statistics = $this->db->get_usage_statistics($period, 100); // 獲取更多數據用於報告
        
        if (empty($statistics)) {
            wp_send_json_error(__('沒有可用的統計數據。', 'wp-seo-vector-importer'));
        }
        
        if ($format === 'csv') {
            $csv_data = "Period,Tokens Used,Estimated Cost (USD)\n";
            
            foreach ($statistics as $stat) {
                $period_label = '';
                
                switch ($period) {
                    case 'day':
                        $period_label = "{$stat['year']}-{$stat['month']}-{$stat['day']}";
                        break;
                    case 'month':
                        $period_label = "{$stat['year']}-{$stat['month']}";
                        break;
                    case 'year':
                        $period_label = "{$stat['year']}";
                        break;
                }
                
                $csv_data .= "{$period_label},{$stat['total_tokens']},{$stat['total_cost']}\n";
            }
            
            wp_send_json_success([
                'data' => $csv_data, 
                'filename' => "openai-usage-{$period}-" . date('Y-m-d') . ".csv"
            ]);
        } else {
            wp_send_json_error(__('不支持的導出格式。', 'wp-seo-vector-importer'));
        }
    }
    
    /**
     * AJAX處理函數：清理舊的token使用記錄
     */
    public function ajax_cleanup_logs() {
        check_ajax_referer('wp_seo_vi_cleanup_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $days_to_keep = isset($_POST['days']) ? intval($_POST['days']) : 90;
        if ($days_to_keep < 30) {
            $days_to_keep = 30; // 最少保留30天的數據
        }
        
        $cleanup_result = $this->db->cleanup_old_token_logs($days_to_keep);
        
        if ($cleanup_result) {
            wp_send_json_success(__('舊的使用記錄已成功清理。', 'wp-seo-vector-importer'));
        } else {
            wp_send_json_error(__('清理舊記錄失敗。', 'wp-seo-vector-importer'));
        }
    }
    
    /**
     * 顯示重複文章比對頁面
     */
    public function display_duplicate_check_page() {
        // 安全檢查
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有足夠權限訪問此頁面。', 'wp-seo-vector-importer'));
        }
        
        // 獲取設置
        $similarity_threshold = floatval($this->db->get_setting('duplicate_threshold', '0.7'));
        $comparison_model = $this->db->get_setting('duplicate_model', 'vector'); // 'vector' 或 'gpt'
        
        // 獲取最近的檢測記錄
        $recent_checks = $this->db->get_duplicate_checks(5);
        
        // 載入重複文章比對頁面模板
        include(WP_SEO_VI_PATH . 'admin/partials/duplicate-check-page.php');
    }
    
    /**
     * AJAX處理函數：開始重複文章比對
     */
    public function ajax_start_duplicate_check() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $threshold = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 0.7;
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'vector';
        
        // 驗證閾值
        if ($threshold < 0.5 || $threshold > 1.0) {
            wp_send_json_error(__('相似度閾值必須介於0.5和1.0之間。', 'wp-seo-vector-importer'));
        }
        
        // 獲取所有向量數據
        $all_vectors_data = $this->db->get_all_vectors(PHP_INT_MAX); // 獲取所有向量
        
        if (empty($all_vectors_data) || count($all_vectors_data) < 2) {
            wp_send_json_error(__('資料庫中需要至少兩篇文章的向量才能進行比對。', 'wp-seo-vector-importer'));
        }
        
        // 創建一個新的檢測記錄
        $batch_id = uniqid('dup_check_');
        $check_id = $this->db->create_duplicate_check($threshold, $model, $batch_id);
        
        if (!$check_id) {
            wp_send_json_error(__('無法創建檢測記錄。', 'wp-seo-vector-importer'));
        }
        
        // 準備要傳遞給批次處理的數據
        $post_ids = array_column($all_vectors_data, 'post_id');
        
        wp_send_json_success([
            'message' => __('開始重複文章比對...', 'wp-seo-vector-importer'),
            'check_id' => $check_id,
            'batch_id' => $batch_id,
            'total_posts' => count($post_ids),
            'post_ids' => $post_ids, // 傳遞所有文章ID
            'threshold' => $threshold,
            'model' => $model
        ]);
    }
    
    /**
     * AJAX處理函數：處理重複文章比對批次
     */
    public function ajax_process_duplicate_check_batch() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        // 獲取參數
        $check_id = isset($_POST['check_id']) ? intval($_POST['check_id']) : 0;
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        $post_ids_json = isset($_POST['post_ids']) ? wp_unslash($_POST['post_ids']) : '[]';
        $post_ids = json_decode($post_ids_json, true);
        $current_index = isset($_POST['current_index']) ? intval($_POST['current_index']) : 0;
        $threshold = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 0.7;
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'vector';
        $batch_size = 10; // 每次處理的文章數量（主循環）
        
        if ($check_id <= 0 || empty($batch_id) || empty($post_ids)) {
            wp_send_json_error(__('無效的請求參數。', 'wp-seo-vector-importer'));
        }
        
        $total_posts = count($post_ids);
        
        // 檢查是否已完成
        if ($current_index >= $total_posts) {
            // 更新最終狀態
            $final_groups = $this->db->get_duplicate_groups($check_id);
            $this->db->update_duplicate_check_status($check_id, 'completed', $total_posts, count($final_groups));
            
            wp_send_json_success([
                'done' => true,
                'message' => __('重複文章比對完成。', 'wp-seo-vector-importer'),
                'check_id' => $check_id,
                'total_processed' => $total_posts,
                'duplicate_groups_found' => count($final_groups)
            ]);
        }
        
        // 獲取當前批次處理的文章ID
        $current_post_id = $post_ids[$current_index];
        
        // 獲取所有向量數據（可以考慮優化，只獲取需要的）
        $all_vectors_data = $this->db->get_all_vectors(PHP_INT_MAX);
        $vectors_map = [];
        foreach ($all_vectors_data as $data) {
            $vectors_map[$data['post_id']] = json_decode($data['vector'], true);
        }
        
        $current_vector = isset($vectors_map[$current_post_id]) ? $vectors_map[$current_post_id] : null;
        
        if (!$current_vector) {
            // 如果當前文章沒有向量，跳過並處理下一篇
            $next_index = $current_index + 1;
            $progress = ($next_index / $total_posts) * 100;
            wp_send_json_success([
                'done' => false,
                'current_index' => $next_index,
                'progress' => round($progress, 1),
                'message' => sprintf(__('正在比對 %d / %d...', 'wp-seo-vector-importer'), $next_index, $total_posts)
            ]);
        }
        
        $duplicates = [];
        $processed_pairs = 0;
        
        // 與後續的文章進行比對
        for ($j = $current_index + 1; $j < $total_posts; $j++) {
            $compare_post_id = $post_ids[$j];
            $compare_vector = isset($vectors_map[$compare_post_id]) ? $vectors_map[$compare_post_id] : null;
            
            if (!$compare_vector) continue;
            
            // 排除與自己比較的情況
            if ($current_post_id === $compare_post_id) {
                continue;
            }
            
            // 根據選擇的模型使用不同的比對方法
            $similarity = 0;
            
            if ($model === 'vector') {
                // 使用向量相似度計算
                $similarity = $this->db->calculate_cosine_similarity($current_vector, $compare_vector);
            } elseif ($model === 'hybrid') {
                // 智慧模式：先向量預篩選，後GPT精確比對
                
                // 1. 先計算向量相似度
                $vector_similarity = $this->db->calculate_cosine_similarity($current_vector, $compare_vector);
                
                // 2. 獲取預篩選閾值（比最終閾值低一些）
                $hybrid_threshold_reduction = floatval(get_option('wp_seo_vi_hybrid_threshold_reduction', '0.25'));
                $pre_screening_threshold = max(0.4, $threshold - $hybrid_threshold_reduction);
                
                // 3. 決定是否需要使用GPT進行進一步比對
                if ($vector_similarity >= $pre_screening_threshold) {
                    // 通過預篩選，使用GPT進行精確比對
                    $current_post = get_post($current_post_id);
                    $compare_post = get_post($compare_post_id);
                    
                    if ($current_post && $compare_post) {
                        // 獲取文章內容
                        $text1 = $current_post->post_title . "\n\n" . strip_shortcodes(strip_tags($current_post->post_content));
                        $text2 = $compare_post->post_title . "\n\n" . strip_shortcodes(strip_tags($compare_post->post_content));
                        
                        // 創建API實例
                        $api_key = get_option('wp_seo_vi_openai_api_key', '');
                        if (empty($api_key)) {
                            // 如果沒有API key，使用向量相似度結果
                            $similarity = $vector_similarity;
                        } else {
                            $openai_api = new WP_SEO_VI_OpenAI_API($api_key);
                            
                            // 使用批處理標識
                            $use_batch_api = $this->db->get_setting('use_batch_api', 'no') === 'yes';
                            $batch_id_for_gpt = $use_batch_api ? $batch_id : null;
                            
                            // 調用GPT比對
                            $result = $openai_api->compare_texts_with_gpt($text1, $text2, $current_post_id, $batch_id_for_gpt);
                            
                            if (!is_wp_error($result) && isset($result['similarity_score'])) {
                                $similarity = floatval($result['similarity_score']);
                            } else {
                                // 如果GPT比對失敗，使用向量相似度結果
                                $similarity = $vector_similarity;
                                
                                // 記錄錯誤
                                $error_message = is_wp_error($result) ? $result->get_error_message() : 'Invalid GPT comparison result';
                                $this->db->log_error($current_post_id, '智慧模式GPT比對失敗，使用向量相似度結果：' . $error_message);
                            }
                        }
                    } else {
                        // 如果無法獲取文章內容，使用向量相似度結果
                        $similarity = $vector_similarity;
                    }
                } else {
                    // 沒有通過預篩選，直接使用向量相似度結果
                    $similarity = $vector_similarity;
                }
            } elseif ($model === 'gpt') {
                // 使用 GPT-4o-mini 進行文章比對
                $current_post = get_post($current_post_id);
                $compare_post = get_post($compare_post_id);
                
                if ($current_post && $compare_post) {
                    // 獲取文章內容
                    $text1 = $current_post->post_title . "\n\n" . strip_shortcodes(strip_tags($current_post->post_content));
                    $text2 = $compare_post->post_title . "\n\n" . strip_shortcodes(strip_tags($compare_post->post_content));
                    
                    // 創建 API 實例
                    $api_key = get_option('wp_seo_vi_openai_api_key', '');
                    if (empty($api_key)) {
                        // 如果沒有 API key，退回到向量相似度
                        $similarity = $this->db->calculate_cosine_similarity($current_vector, $compare_vector);
                    } else {
                        $openai_api = new WP_SEO_VI_OpenAI_API($api_key);
                        
                        // 使用批處理標識
                        $use_batch_api = $this->db->get_setting('use_batch_api', 'no') === 'yes';
                        $batch_id_for_gpt = $use_batch_api ? $batch_id : null;
                        
                        // 調用 GPT 比對
                        $result = $openai_api->compare_texts_with_gpt($text1, $text2, $current_post_id, $batch_id_for_gpt);
                        
                        if (!is_wp_error($result) && isset($result['similarity_score'])) {
                            $similarity = floatval($result['similarity_score']);
                        } else {
                            // 如果 GPT 比對失敗，退回到向量相似度
                            $similarity = $this->db->calculate_cosine_similarity($current_vector, $compare_vector);
                            
                            // 記錄錯誤
                            $error_message = is_wp_error($result) ? $result->get_error_message() : 'Invalid GPT comparison result';
                            $this->db->log_error($current_post_id, 'GPT比對失敗，退回到向量相似度：' . $error_message);
                        }
                    }
                } else {
                    // 如果無法獲取文章內容，退回到向量相似度
                    $similarity = $this->db->calculate_cosine_similarity($current_vector, $compare_vector);
                }
            }
            
            if ($similarity >= $threshold) {
                // 找到相似文章
                $duplicates[] = [
                    'post_id' => $compare_post_id,
                    'similarity_score' => $similarity
                ];
            }
            $processed_pairs++;
            
            // 可以在這裡添加一個小的延遲或檢查執行時間，以防止超時
            // if ($processed_pairs % 50 == 0) { usleep(10000); }
        }
        
        // 如果找到重複項，將它們與當前文章一起保存為一個組
        if (!empty($duplicates)) {
            $group_articles = [];
            $current_post_info = get_post($current_post_id);
            $group_articles[] = [
                'post_id' => $current_post_id,
                'post_title' => $current_post_info ? $current_post_info->post_title : 'N/A',
                'post_url' => get_permalink($current_post_id),
                'similarity_score' => 1.0 // 自身相似度為1
            ];
            
            foreach ($duplicates as $dup) {
                $dup_post_info = get_post($dup['post_id']);
                $group_articles[] = [
                    'post_id' => $dup['post_id'],
                    'post_title' => $dup_post_info ? $dup_post_info->post_title : 'N/A',
                    'post_url' => get_permalink($dup['post_id']),
                    'similarity_score' => $dup['similarity_score']
                ];
            }
            
            // 使用 check_id 和 current_post_id 作為組ID（或生成唯一組ID）
            $group_id = $current_post_id; 
            $this->db->save_duplicate_group($check_id, $group_id, $group_articles);
        }
        
        // 準備下一次請求
        $next_index = $current_index + 1;
        $progress = ($next_index / $total_posts) * 100;
        
        wp_send_json_success([
            'done' => false,
            'current_index' => $next_index,
            'progress' => round($progress, 1),
            'message' => sprintf(__('正在比對 %d / %d...', 'wp-seo-vector-importer'), $next_index, $total_posts)
        ]);
    }
    
    /**
     * AJAX處理函數：獲取重複文章比對結果
     */
    public function ajax_get_check_result() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $check_id = isset($_POST['check_id']) ? intval($_POST['check_id']) : 0;
        
        if ($check_id <= 0) {
            wp_send_json_error(__('無效的檢查ID。', 'wp-seo-vector-importer'));
        }
        
        // 獲取檢查記錄狀態
        $check_info = $this->db->get_duplicate_check($check_id);
        
        if (!$check_info) {
            wp_send_json_error(__('找不到指定的比對記錄。', 'wp-seo-vector-importer'));
        }
        
        // 獲取此檢查ID下的所有重複文章組
        $duplicate_groups = $this->db->get_duplicate_groups($check_id);
        
        wp_send_json_success([
            'check_info' => $check_info,
            'groups' => $duplicate_groups
        ]);
    }
    
    /**
     * AJAX處理函數：獲取最近的重複文章比對記錄列表
     */
    public function ajax_get_recent_checks() {
        check_ajax_referer('wp_seo_vi_duplicate_check_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足。', 'wp-seo-vector-importer'), 403);
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        $recent_checks = $this->db->get_duplicate_checks($limit);
        
        ob_start();
        if (!empty($recent_checks)) {
            foreach ($recent_checks as $check) {
                ?>
                <tr data-check-id="<?php echo esc_attr($check['check_id']); ?>">
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($check['check_date']))); ?></td>
                    <td><?php echo esc_html($check['similarity_threshold']); ?></td>
                    <td><?php 
                        if ($check['model_used'] === 'vector') {
                            echo __('快速模式', 'wp-seo-vector-importer');
                        } elseif ($check['model_used'] === 'hybrid') {
                            echo __('智慧模式', 'wp-seo-vector-importer');
                        } else {
                            echo __('精準模式', 'wp-seo-vector-importer');
                        }
                    ?></td>
                    <td><?php echo esc_html($check['total_articles_checked']); ?></td>
                    <td><?php echo esc_html($check['duplicate_groups_found']); ?></td>
                    <td>
                        <?php 
                        $status = $check['status'];
                        $status_text = '';
                        $status_class = '';
                        
                        if ($status === 'processing') {
                            $status_text = __('處理中', 'wp-seo-vector-importer');
                            $status_class = 'wp-seo-vi-status-processing';
                        } elseif ($status === 'completed') {
                            $status_text = __('已完成', 'wp-seo-vector-importer');
                            $status_class = 'wp-seo-vi-status-completed';
                        } elseif ($status === 'cancelled') {
                            $status_text = __('已取消', 'wp-seo-vector-importer');
                            $status_class = 'wp-seo-vi-status-cancelled';
                        } else {
                            $status_text = __('未知', 'wp-seo-vector-importer');
                            $status_class = 'wp-seo-vi-status-unknown';
                        }
                        
                        echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
                        ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small wp-seo-vi-view-result-btn" data-check-id="<?php echo esc_attr($check['check_id']); ?>">
                            <?php _e('查看結果', 'wp-seo-vector-importer'); ?>
                        </button>
                        <?php if ($status !== 'processing'): ?>
                        <button type="button" class="button button-small wp-seo-vi-delete-record-btn" data-check-id="<?php echo esc_attr($check['check_id']); ?>" style="margin-left: 5px; color: #a00;">
                            <?php _e('刪除', 'wp-seo-vector-importer'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="7"><?php _e('尚無比對記錄。', 'wp-seo-vector-importer'); ?></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html
        ]);
    }

}
?>
