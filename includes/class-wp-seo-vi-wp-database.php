<?php
/**
 * 處理 WordPress 資料庫互動，用於 WP SEO Vector Importer。
 * 新版本使用 WordPress $wpdb 替代 SQLite+PDO。
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WP_SEO_VI_WP_Database extends WP_SEO_VI_Database {

    /**
     * $wpdb 實例。
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 資料表前綴，包含 WordPress 前綴。
     *
     * @var string
     */
    private $table_prefix;

    /**
     * 資料表名稱。
     *
     * @var array
     */
    private $tables = array();

    /**
     * 建構子。
     * 初始化資料庫連線。
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'seo_vi_';
        
        // 定義各個資料表名稱
        $this->tables = array(
            'vectors'           => $this->table_prefix . 'vectors',
            'error_log'         => $this->table_prefix . 'error_log',
            'token_usage'       => $this->table_prefix . 'token_usage',
            'duplicate_checks'  => $this->table_prefix . 'duplicate_checks',
            'duplicate_articles'=> $this->table_prefix . 'duplicate_articles',
            'duplicate_groups'  => $this->table_prefix . 'duplicate_groups',
            'usage_summary'     => $this->table_prefix . 'usage_summary',
            'usage_settings'    => $this->table_prefix . 'usage_settings'
        );
        
        // 確保資料表已建立
        $this->initialize_schema();
    }

    /**
     * 初始化資料庫架構（如果資料表不存在則創建）。
     *
     * @return bool 成功時返回 true，失敗時返回 false。
     */
    public function initialize_schema() {
        // 需要使用 dbDelta 函數
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        $charset_collate = $this->wpdb->get_charset_collate();
        $success = true;

        // 向量資料表
        $sql = "CREATE TABLE {$this->tables['vectors']} (
            post_id bigint(20) NOT NULL,
            vector longtext NOT NULL,
            url varchar(2083) DEFAULT '',
            categories text DEFAULT '',
            last_updated datetime NOT NULL,
            PRIMARY KEY  (post_id)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 錯誤日誌資料表
        $sql = "CREATE TABLE {$this->tables['error_log']} (
            log_id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            error_message text NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY  (log_id)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // Token 使用追蹤資料表
        $sql = "CREATE TABLE {$this->tables['token_usage']} (
            usage_id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            tokens_used int(11) NOT NULL,
            estimated_cost decimal(10,6) NOT NULL,
            operation_type varchar(50) NOT NULL,
            batch_id varchar(50) DEFAULT NULL,
            model_type varchar(50) DEFAULT 'text-embedding-3-small',
            timestamp datetime NOT NULL,
            PRIMARY KEY  (usage_id)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 重複文章檢測結果資料表
        $sql = "CREATE TABLE {$this->tables['duplicate_checks']} (
            check_id bigint(20) NOT NULL AUTO_INCREMENT,
            check_date datetime NOT NULL,
            similarity_threshold decimal(5,2) NOT NULL,
            model_used varchar(50) NOT NULL,
            total_articles_checked int(11) NOT NULL,
            duplicate_groups_found int(11) NOT NULL,
            batch_id varchar(50) DEFAULT NULL,
            status varchar(20) NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY  (check_id)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 重複文章詳情資料表
        $sql = "CREATE TABLE {$this->tables['duplicate_articles']} (
            duplicate_id bigint(20) NOT NULL AUTO_INCREMENT,
            check_id bigint(20) NOT NULL,
            group_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            post_title text DEFAULT NULL,
            post_url varchar(2083) DEFAULT NULL,
            similarity_score decimal(5,2) DEFAULT NULL,
            PRIMARY KEY  (duplicate_id),
            KEY check_id (check_id)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 重複文章組資料表
        $sql = "CREATE TABLE {$this->tables['duplicate_groups']} (
            group_id bigint(20) NOT NULL AUTO_INCREMENT,
            check_id bigint(20) NOT NULL,
            group_name text DEFAULT NULL,
            articles_json longtext NOT NULL,
            article_count int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (group_id),
            KEY check_id (check_id)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 使用量摘要資料表 - 用於快速查詢和報表
        $sql = "CREATE TABLE {$this->tables['usage_summary']} (
            summary_id bigint(20) NOT NULL AUTO_INCREMENT,
            year int(4) NOT NULL,
            month int(2) NOT NULL,
            day int(2) NOT NULL,
            total_tokens bigint(20) NOT NULL,
            total_cost decimal(10,6) NOT NULL,
            last_updated datetime NOT NULL,
            PRIMARY KEY  (summary_id),
            UNIQUE KEY year_month_day (year, month, day)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 使用設定資料表
        $sql = "CREATE TABLE {$this->tables['usage_settings']} (
            setting_id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_name varchar(50) NOT NULL,
            setting_value text NOT NULL,
            last_updated datetime NOT NULL,
            PRIMARY KEY  (setting_id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";
        dbDelta($sql);
        $success = $success && !empty($this->wpdb->last_error);

        // 初始化默認設定（如果表是新創建的）
        $settings_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['usage_settings']}");
        if ($settings_count == 0) {
            $default_settings = array(
                array('token_rate', '0.15', current_time('mysql', 1)), // text-embedding-3-small的默認費率
                array('budget_limit', '0', current_time('mysql', 1)), // 0 表示無限制
                array('enable_alerts', 'no', current_time('mysql', 1)),
                array('alert_threshold', '80', current_time('mysql', 1)), // 預算警報閾值，百分比
                array('batch_size', '5', current_time('mysql', 1)) // 批處理大小
            );
            
            foreach ($default_settings as $setting) {
                $this->wpdb->insert(
                    $this->tables['usage_settings'],
                    array(
                        'setting_name' => $setting[0],
                        'setting_value' => $setting[1],
                        'last_updated' => $setting[2]
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }

        return $success;
    }

    /**
     * 插入或更新文章向量資料。
     *
     * @param int    $post_id WordPress 文章 ID。
     * @param string $vector  向量資料（例如JSON字串）。
     * @param string $url     文章網址。
     * @param string $categories 分類（JSON字串）。
     * @return bool 成功時返回 true，失敗時返回 false。
     */
    public function insert_or_update_vector($post_id, $vector, $url = '', $categories = '') {
        // 檢查向量是否已存在
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT post_id FROM {$this->tables['vectors']} WHERE post_id = %d",
                $post_id
            )
        );

        $data = array(
            'post_id' => $post_id,
            'vector' => $vector,
            'url' => $url,
            'categories' => $categories,
            'last_updated' => current_time('mysql', 1)
        );
        
        $format = array('%d', '%s', '%s', '%s', '%s');

        if ($exists) {
            // 更新現有記錄
            $result = $this->wpdb->update(
                $this->tables['vectors'],
                $data,
                array('post_id' => $post_id),
                $format,
                array('%d')
            );
        } else {
            // 插入新記錄
            $result = $this->wpdb->insert(
                $this->tables['vectors'],
                $data,
                $format
            );
        }

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Insert/Update Error: ' . $this->wpdb->last_error);
            $this->log_error($post_id, 'Database insert/update failed: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 刪除指定文章 ID 的向量資料。
     *
     * @param int $post_id WordPress 文章 ID。
     * @return bool 成功時返回 true，失敗時返回 false。
     */
    public function delete_vector($post_id) {
        $result = $this->wpdb->delete(
            $this->tables['vectors'],
            array('post_id' => $post_id),
            array('%d')
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Delete Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $result > 0;
    }

    /**
     * 記錄錯誤訊息。
     *
     * @param int|null $post_id       相關文章 ID（可選）。
     * @param string   $error_message 錯誤訊息。
     * @return bool 成功時返回 true，失敗時返回 false。
     */
    public function log_error($post_id, $error_message) {
        $result = $this->wpdb->insert(
            $this->tables['error_log'],
            array(
                'post_id' => $post_id,
                'error_message' => $error_message,
                'timestamp' => current_time('mysql', 1)
            ),
            array('%d', '%s', '%s')
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Log Error Failed: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 獲取錯誤日誌。
     *
     * @param int $limit 要獲取的日誌數量。
     * @param int $offset 分頁偏移量。
     * @return array|false 日誌記錄陣列或失敗時返回 false。
     */
    public function get_error_logs($limit = 50, $offset = 0) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['error_log']} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            error_log('WP SEO Vector Importer DB Get Logs Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $results;
    }

    /**
     * 獲取錯誤日誌總數。
     *
     * @return int|false 日誌總數或失敗時返回 false。
     */
    public function get_error_log_count() {
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['error_log']}");

        if ($count === null) {
            error_log('WP SEO Vector Importer DB Get Log Count Error: ' . $this->wpdb->last_error);
            return false;
        }

        return (int) $count;
    }

    /**
     * 清除所有錯誤日誌。
     *
     * @return bool 成功時返回 true，失敗時返回 false。
     */
    public function clear_error_logs() {
        $result = $this->wpdb->query("TRUNCATE TABLE {$this->tables['error_log']}");

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Clear Logs Error: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 獲取特定文章 ID 的向量狀態（最後更新時間）。
     *
     * @param int $post_id WordPress 文章 ID。
     * @return string|false 最後更新時間戳（YYYY-MM-DD HH:MM:SS）或在未找到/錯誤時返回 false。
     */
    public function get_vector_status($post_id) {
        $last_updated = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT last_updated FROM {$this->tables['vectors']} WHERE post_id = %d",
                $post_id
            )
        );

        if ($last_updated === null && !empty($this->wpdb->last_error)) {
            error_log('WP SEO Vector Importer DB Get Status Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $last_updated;
    }

    /**
     * 獲取多個文章 ID 的向量狀態。
     *
     * @param array $post_ids WordPress 文章 ID 陣列。
     * @return array|false 關聯陣列 [post_id => last_updated] 或錯誤時返回 false。
     */
    public function get_vector_statuses(array $post_ids) {
        if (empty($post_ids)) {
            return array();
        }

        // 將所有ID轉為整數
        $post_ids = array_map('intval', $post_ids);
        
        // 建立安全的 ID 列表用於 IN 子句
        $id_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        
        $query = $this->wpdb->prepare(
            "SELECT post_id, last_updated FROM {$this->tables['vectors']} WHERE post_id IN ($id_placeholders)",
            $post_ids
        );
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        if ($results === null) {
            error_log('WP SEO Vector Importer DB Get Statuses Error: ' . $this->wpdb->last_error);
            return false;
        }
        
        // 轉換結果為 [post_id => last_updated] 格式
        $statuses = array();
        foreach ($results as $row) {
            $statuses[$row['post_id']] = $row['last_updated'];
        }
        
        return $statuses;
    }

    /**
     * 記錄 token 使用情況。
     *
     * @param int $tokens 使用的 token 數量。
     * @param float $cost 估計成本。
     * @param int|null $post_id 相關文章 ID（可選）。
     * @param string $operation_type 操作類型。
     * @param string|null $batch_id 批次處理 ID（可選）。
     * @return bool 成功或失敗。
     */
    public function log_token_usage($tokens, $cost, $post_id = null, $operation_type = 'single_embedding', $batch_id = null) {
        $result = $this->wpdb->insert(
            $this->tables['token_usage'],
            array(
                'post_id' => $post_id,
                'tokens_used' => $tokens,
                'estimated_cost' => $cost,
                'operation_type' => $operation_type,
                'batch_id' => $batch_id,
                'timestamp' => current_time('mysql', 1)
            ),
            array('%d', '%d', '%f', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Log Token Usage Error: ' . $this->wpdb->last_error);
            return false;
        }

        // 更新每日摘要
        $this->update_usage_summary($tokens, $cost);

        return true;
    }

    /**
     * 更新使用量摘要。
     *
     * @param int $tokens 使用的 token 數量。
     * @param float $cost 估計成本。
     * @return bool 成功或失敗。
     */
    private function update_usage_summary($tokens, $cost) {
        $now = current_time('timestamp', 1); // 獲取 GMT 時間戳
        $year = gmdate('Y', $now);
        $month = gmdate('m', $now);
        $day = gmdate('d', $now);

        // 嘗試更新現有記錄
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->tables['usage_summary']} 
                 (year, month, day, total_tokens, total_cost, last_updated) 
                 VALUES (%d, %d, %d, %d, %f, %s)
                 ON DUPLICATE KEY UPDATE 
                 total_tokens = total_tokens + VALUES(total_tokens),
                 total_cost = total_cost + VALUES(total_cost),
                 last_updated = VALUES(last_updated)",
                $year,
                $month,
                $day,
                $tokens,
                $cost,
                current_time('mysql', 1)
            )
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Update Summary Error: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 獲取使用統計數據。
     *
     * @param string $period 時間週期 ('day', 'month', 'year')。
     * @param int $limit 限制結果數量。
     * @return array|false 統計數據陣列或失敗時返回 false。
     */
    public function get_usage_statistics($period = 'month', $limit = 30) {
        $query = '';

        switch ($period) {
            case 'day':
                $query = $this->wpdb->prepare(
                    "SELECT year, month, day, total_tokens, total_cost, last_updated
                     FROM {$this->tables['usage_summary']}
                     ORDER BY year DESC, month DESC, day DESC
                     LIMIT %d",
                    $limit
                );
                break;

            case 'month':
                $query = $this->wpdb->prepare(
                    "SELECT year, month, SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost, 
                     MAX(last_updated) as last_updated
                     FROM {$this->tables['usage_summary']}
                     GROUP BY year, month
                     ORDER BY year DESC, month DESC
                     LIMIT %d",
                    $limit
                );
                break;

            case 'year':
                $query = $this->wpdb->prepare(
                    "SELECT year, SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost,
                     MAX(last_updated) as last_updated
                     FROM {$this->tables['usage_summary']}
                     GROUP BY year
                     ORDER BY year DESC
                     LIMIT %d",
                    $limit
                );
                break;

            default:
                return false;
        }

        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            error_log('WP SEO Vector Importer DB Get Usage Statistics Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $results;
    }

    /**
     * 獲取當前月份的使用量統計。
     *
     * @return array|false 包含 total_tokens 和 total_cost 的陣列，或失敗時返回 false。
     */
    public function get_current_month_usage() {
        $now = current_time('timestamp', 1);
        $year = gmdate('Y', $now);
        $month = gmdate('m', $now);

        $query = $this->wpdb->prepare(
            "SELECT SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost
             FROM {$this->tables['usage_summary']}
             WHERE year = %d AND month = %d",
            $year,
            $month
        );

        $result = $this->wpdb->get_row($query, ARRAY_A);

        if ($result === null && !empty($this->wpdb->last_error)) {
            error_log('WP SEO Vector Importer DB Get Month Usage Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $result ?: array('total_tokens' => 0, 'total_cost' => 0);
    }

    /**
     * 獲取設定值。
     *
     * @param string $setting_name 設定名稱。
     * @param mixed $default_value 默認值（找不到時返回）。
     * @return mixed 設定值或默認值。
     */
    public function get_setting($setting_name, $default_value = '') {
        $query = $this->wpdb->prepare(
            "SELECT setting_value FROM {$this->tables['usage_settings']} WHERE setting_name = %s",
            $setting_name
        );

        $result = $this->wpdb->get_var($query);

        if ($result === null && !empty($this->wpdb->last_error)) {
            error_log('WP SEO Vector Importer DB Get Setting Error: ' . $this->wpdb->last_error);
            return $default_value;
        }

        return $result !== null ? $result : $default_value;
    }

    /**
     * 更新設定值。
     *
     * @param string $setting_name 設定名稱。
     * @param string $setting_value 設定值。
     * @return bool 成功或失敗。
     */
    public function update_setting($setting_name, $setting_value) {
        // 檢查設定是否存在
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT setting_id FROM {$this->tables['usage_settings']} WHERE setting_name = %s",
                $setting_name
            )
        );

        $data = array(
            'setting_value' => $setting_value,
            'last_updated' => current_time('mysql', 1)
        );

        if ($exists) {
            // 更新現有設定
            $result = $this->wpdb->update(
                $this->tables['usage_settings'],
                $data,
                array('setting_name' => $setting_name),
                array('%s', '%s'),
                array('%s')
            );
        } else {
            // 插入新設定
            $data['setting_name'] = $setting_name;
            $result = $this->wpdb->insert(
                $this->tables['usage_settings'],
                $data,
                array('%s', '%s', '%s')
            );
        }

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Update Setting Error: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 獲取所有設定。
     *
     * @return array|false 所有設定的陣列，或失敗時返回 false。
     */
    public function get_all_settings() {
        $query = "SELECT setting_name, setting_value FROM {$this->tables['usage_settings']}";
        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            error_log('WP SEO Vector Importer DB Get All Settings Error: ' . $this->wpdb->last_error);
            return false;
        }

        $settings = array();
        foreach ($results as $row) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * 獲取批次處理的 token 統計。
     *
     * @param string $batch_id 批次處理 ID。
     * @return array|false 批次處理的統計數據，或失敗時返回 false。
     */
    public function get_batch_statistics($batch_id) {
        if (empty($batch_id)) {
            return false;
        }

        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) as processed_count, SUM(tokens_used) as total_tokens, 
             SUM(estimated_cost) as total_cost, 
             MIN(timestamp) as start_time, MAX(timestamp) as end_time
             FROM {$this->tables['token_usage']}
             WHERE batch_id = %s",
            $batch_id
        );

        $result = $this->wpdb->get_row($query, ARRAY_A);

        if ($result === null) {
            error_log('WP SEO Vector Importer DB Get Batch Statistics Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $result ?: array(
            'processed_count' => 0,
            'total_tokens' => 0,
            'total_cost' => 0
        );
    }

    /**
     * 清理舊的 token 使用記錄。
     *
     * @param int $days_to_keep 保留的天數。
     * @return bool 成功或失敗。
     */
    public function cleanup_old_token_logs($days_to_keep = 90) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days", current_time('timestamp', 1)));

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['token_usage']} WHERE timestamp < %s",
                $cutoff_date
            )
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Cleanup Token Logs Error: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 獲取向量資料庫中的文章總數。
     *
     * @return int|false 向量總數或失敗時返回 false。
     */
    public function get_vector_count() {
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['vectors']}");

        if ($count === null) {
            error_log('WP SEO Vector Importer DB Get Vector Count Error: ' . $this->wpdb->last_error);
            return false;
        }

        return (int) $count;
    }

    /**
     * 取得所有向量資料。
     *
     * @param int $limit 限制結果數量。
     * @param int $offset 偏移量，用於分頁。
     * @return array|false 向量資料陣列或失敗時返回 false。
     */
    public function get_all_vectors($limit = 1000, $offset = 0) {
        $query = $this->wpdb->prepare(
            "SELECT post_id, vector, url, categories, last_updated FROM {$this->tables['vectors']} LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            error_log('WP SEO Vector Importer DB Get All Vectors Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $results;
    }

    /**
     * 計算兩個向量之間的餘弦相似度
     *
     * @param array $vector1 第一個向量
     * @param array $vector2 第二個向量
     * @return float 相似度（介於-1到1之間，越接近1表示越相似）
     */
    public function calculate_cosine_similarity($vector1, $vector2) {
        if (empty($vector1) || empty($vector2) || count($vector1) != count($vector2)) {
            return 0.0;
        }
        
        $dot_product = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;
        
        foreach ($vector1 as $i => $value) {
            $dot_product += $value * $vector2[$i];
            $magnitude1 += $value * $value;
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0.0 || $magnitude2 == 0.0) {
            return 0.0;
        }
        
        return $dot_product / ($magnitude1 * $magnitude2);
    }
    
    /**
     * 創建一個新的重複檢測記錄
     *
     * @param float $threshold 相似度閾值
     * @param string $model_used 使用的模型
     * @param string $batch_id 批次ID
     * @return int|false 新建檢測記錄的ID或失敗時返回false
     */
    public function create_duplicate_check($threshold, $model_used, $batch_id = null) {
        $result = $this->wpdb->insert(
            $this->tables['duplicate_checks'],
            array(
                'check_date' => current_time('mysql', 1),
                'similarity_threshold' => $threshold,
                'model_used' => $model_used,
                'total_articles_checked' => 0,
                'duplicate_groups_found' => 0,
                'batch_id' => $batch_id,
                'status' => 'processing',
                'timestamp' => current_time('mysql', 1)
            ),
            array('%s', '%f', '%s', '%d', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Create Duplicate Check Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $this->wpdb->insert_id;
    }
    
    /**
     * 更新重複檢測記錄的狀態
     *
     * @param int $check_id 檢測記錄ID
     * @param string $status 狀態
     * @param int $total_checked 檢查的文章總數
     * @param int $duplicates_found 找到的重複組數
     * @return bool 成功或失敗
     */
    public function update_duplicate_check_status($check_id, $status, $total_checked = null, $duplicates_found = null) {
        $data = array('status' => $status);
        $data_formats = array('%s');

        if ($total_checked !== null) {
            $data['total_articles_checked'] = $total_checked;
            $data_formats[] = '%d';
        }

        if ($duplicates_found !== null) {
            $data['duplicate_groups_found'] = $duplicates_found;
            $data_formats[] = '%d';
        }

        $result = $this->wpdb->update(
            $this->tables['duplicate_checks'],
            $data,
            array('check_id' => $check_id),
            $data_formats,
            array('%d')
        );

        if ($result === false) {
            error_log('WP SEO Vector Importer DB Update Duplicate Check Status Error: ' . $this->wpdb->last_error);
            return false;
        }

        return true;
    }
    
    /**
     * 儲存重複文章組
     *
     * @param int $check_id 檢測記錄ID
     * @param int $group_id 重複文章組ID
     * @param array $articles 重複文章資訊陣列，每項包含post_id, post_title, post_url和similarity_score
     * @return bool 成功或失敗
     */
    public function save_duplicate_group($check_id, $group_id, $articles) {
        if (empty($articles)) {
            return false;
        }

        // 開始事務處理
        $this->wpdb->query('START TRANSACTION');
        $success = true;

        foreach ($articles as $article) {
            $result = $this->wpdb->insert(
                $this->tables['duplicate_articles'],
                array(
                    'check_id' => $check_id,
                    'group_id' => $group_id,
                    'post_id' => $article['post_id'],
                    'post_title' => isset($article['post_title']) ? $article['post_title'] : '',
                    'post_url' => isset($article['post_url']) ? $article['post_url'] : '',
                    'similarity_score' => isset($article['similarity_score']) ? $article['similarity_score'] : null
                ),
                array('%d', '%d', '%d', '%s', '%s', '%f')
            );

            if ($result === false) {
                $success = false;
                break;
            }
        }

        // 如果失敗，回滾事務
        if (!$success) {
            $this->wpdb->query('ROLLBACK');
            error_log('WP SEO Vector Importer DB Save Duplicate Group Error: ' . $this->wpdb->last_error);
            return false;
        }

        // 成功則提交事務
        $this->wpdb->query('COMMIT');
        return true;
    }
    
    /**
     * 獲取重複檢測記錄
     *
     * @param int $check_id 檢測記錄ID
     * @return array|false 檢測記錄或失敗時返回false
     */
    public function get_duplicate_check($check_id) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['duplicate_checks']} WHERE check_id = %d",
            $check_id
        );

        $result = $this->wpdb->get_row($query, ARRAY_A);

        if ($result === null && !empty($this->wpdb->last_error)) {
            error_log('WP SEO Vector Importer DB Get Duplicate Check Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $result;
    }
    
    /**
     * 獲取所有重複檢測記錄
     *
     * @param int $limit 限制結果數量
     * @param int $offset 偏移量，用於分頁
     * @return array|false 檢測記錄陣列或失敗時返回false
     */
    public function get_duplicate_checks($limit = 10, $offset = 0) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['duplicate_checks']} ORDER BY check_date DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            error_log('WP SEO Vector Importer DB Get Duplicate Checks Error: ' . $this->wpdb->last_error);
            return false;
        }

        return $results;
    }
    
    /**
     * 獲取一次檢測的所有重複文章組
     *
     * @param int $check_id 檢測記錄ID
     * @return array|false 重複文章組陣列或失敗時返回false
     */
    public function get_duplicate_groups($check_id) {
        // 先獲取所有不同的組ID
        $query = $this->wpdb->prepare(
            "SELECT DISTINCT group_id FROM {$this->tables['duplicate_articles']} WHERE check_id = %d ORDER BY group_id",
            $check_id
        );

        $group_ids = $this->wpdb->get_col($query);

        if ($group_ids === null) {
            error_log('WP SEO Vector Importer DB Get Duplicate Groups Error: ' . $this->wpdb->last_error);
            return false;
        }

        if (empty($group_ids)) {
            return array();
        }

        // 獲取每個組的文章
        $result = array();
        foreach ($group_ids as $group_id) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['duplicate_articles']} 
                 WHERE check_id = %d AND group_id = %d 
                 ORDER BY similarity_score DESC",
                $check_id,
                $group_id
            );

            $articles = $this->wpdb->get_results($query, ARRAY_A);

            if ($articles !== null && !empty($articles)) {
                $result[] = $articles;
            }
        }

        return $result;
    }
    
    /**
     * 檢查預算是否已超過
     *
     * @param float $additional_cost 額外的成本（可選）
     * @return bool 是否超過預算
     */
    public function is_budget_exceeded($additional_cost = 0) {
        $budget_limit = floatval($this->get_setting('budget_limit', '0'));
        
        // 如果沒有設置預算限制，則不會超過
        if ($budget_limit <= 0) {
            return false;
        }
        
        // 獲取當前月份的使用量
        $current_usage = $this->get_current_month_usage();
        if ($current_usage === false) {
            return false; // 無法確定，假設不超過
        }
        
        $current_cost = floatval($current_usage['total_cost']);
        
        // 檢查當前成本加上額外成本是否超過預算限制
        return ($current_cost + $additional_cost) > $budget_limit;
    }
    
    /**
     * 從 SQLite 資料庫遷移資料到 WordPress 資料庫
     *
     * @param WP_SEO_VI_Database $sqlite_db SQLite 資料庫實例
     * @param array $tables_to_migrate 要遷移的資料表名稱陣列
     * @param int $batch_size 每批處理的記錄數量
     * @return array 遷移統計資訊
     */
    public function migrate_from_sqlite(WP_SEO_VI_Database $sqlite_db, $tables_to_migrate = array(), $batch_size = 100) {
        // 預設遷移所有資料表
        if (empty($tables_to_migrate)) {
            $tables_to_migrate = array(
                'vectors', 'error_log', 'token_usage', 'duplicate_checks',
                'duplicate_articles', 'duplicate_groups', 'usage_summary', 'usage_settings'
            );
        }
        
        $stats = array(
            'total_tables' => count($tables_to_migrate),
            'tables_migrated' => 0,
            'total_records' => 0,
            'records_migrated' => 0,
            'errors' => array()
        );
        
        foreach ($tables_to_migrate as $table) {
            $method_name = "migrate_{$table}_table";
            
            if (method_exists($this, $method_name)) {
                $result = $this->$method_name($sqlite_db, $batch_size);
                
                if ($result === false) {
                    $stats['errors'][] = "遷移資料表 {$table} 失敗";
                    continue;
                }
                
                $stats['tables_migrated']++;
                $stats['total_records'] += $result['total'];
                $stats['records_migrated'] += $result['migrated'];
                
                if (!empty($result['errors'])) {
                    $stats['errors'] = array_merge($stats['errors'], $result['errors']);
                }
            } else {
                $stats['errors'][] = "找不到資料表 {$table} 的遷移方法";
            }
        }
        
        return $stats;
    }
    
    /**
     * 遷移向量資料表
     *
     * @param WP_SEO_VI_Database $sqlite_db SQLite 資料庫實例
     * @param int $batch_size 每批處理的記錄數量
     * @return array|false 遷移統計資訊或失敗時返回 false
     */
    private function migrate_vectors_table(WP_SEO_VI_Database $sqlite_db, $batch_size = 100) {
        $offset = 0;
        $total = $sqlite_db->get_vector_count();
        $migrated = 0;
        $errors = array();
        
        if ($total === false) {
            return false;
        }
        
        while ($offset < $total) {
            $records = $sqlite_db->get_all_vectors($batch_size, $offset);
            
            if ($records === false) {
                $errors[] = "獲取向量資料失敗，偏移量: {$offset}";
                break;
            }
            
            foreach ($records as $record) {
                $result = $this->insert_or_update_vector(
                    $record['post_id'],
                    $record['vector'],
                    $record['url'],
                    $record['categories']
                );
                
                if ($result) {
                    $migrated++;
                } else {
                    $errors[] = "插入向量資料失敗，文章 ID: {$record['post_id']}";
                }
            }
            
            $offset += $batch_size;
        }
        
        return array(
            'total' => $total,
            'migrated' => $migrated,
            'errors' => $errors
        );
    }
}
?>
