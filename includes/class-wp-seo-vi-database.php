<?php
/**
 * Handles SQLite database interactions for WP SEO Vector Importer.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WP_SEO_VI_Database {

    /**
     * PDO instance.
     *
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * Database file path.
     *
     * @var string
     */
    private $db_file;

    /**
     * Constructor.
     * Initializes the database connection.
     */
    public function __construct() {
        $this->db_file = WP_SEO_VI_DATA_DIR . 'vector_store.db';
        $this->connect();
        $this->initialize_schema(); // Ensure tables exist on instantiation
    }

    /**
     * Establish PDO connection to the SQLite database.
     *
     * @return bool True on success, false on failure.
     */
    private function connect() {
        if ( $this->pdo ) {
            return true;
        }

        try {
            $this->pdo = new PDO( 'sqlite:' . $this->db_file );
            $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            $this->pdo->exec('PRAGMA journal_mode = WAL;'); // Improve concurrency
            return true;
        } catch ( PDOException $e ) {
            // Log error appropriately in a real-world scenario
            error_log( 'WP SEO Vector Importer DB Connection Error: ' . $e->getMessage() );
            $this->pdo = null;
            return false;
        }
    }

    /**
     * Initialize database schema (create tables if they don't exist).
     */
    public function initialize_schema() {
        if ( ! $this->pdo ) {
            return false;
        }

        try {
            // Vectors table
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS vectors (
                    post_id INTEGER PRIMARY KEY,
                    vector TEXT NOT NULL,
                    url TEXT,
                    categories TEXT,
                    last_updated DATETIME NOT NULL
                )
            " );
            
            // 檢查並添加欄位（如果不存在）
            $columns = $this->pdo->query("PRAGMA table_info(vectors)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            if (!in_array('url', $columnNames)) {
                $this->pdo->exec("ALTER TABLE vectors ADD COLUMN url TEXT");
            }
            
            if (!in_array('categories', $columnNames)) {
                $this->pdo->exec("ALTER TABLE vectors ADD COLUMN categories TEXT");
            }

            // Error log table
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS error_log (
                    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER,
                    error_message TEXT NOT NULL,
                    timestamp DATETIME NOT NULL
                )
            " );
            
            // Token usage tracking table
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS token_usage (
                    usage_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER,
                    tokens_used INTEGER NOT NULL,
                    estimated_cost REAL NOT NULL,
                    operation_type TEXT NOT NULL,
                    batch_id TEXT,
                    model_type TEXT DEFAULT 'text-embedding-3-small',
                    timestamp DATETIME NOT NULL
                )
            " );
            
            // 檢查並添加欄位（如果不存在）
            $columns = $this->pdo->query("PRAGMA table_info(token_usage)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            if (!in_array('model_type', $columnNames)) {
                $this->pdo->exec("ALTER TABLE token_usage ADD COLUMN model_type TEXT DEFAULT 'text-embedding-3-small'");
            }
            
            // 重複文章檢測結果表
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS duplicate_checks (
                    check_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    check_date DATETIME NOT NULL,
                    similarity_threshold REAL NOT NULL,
                    model_used TEXT NOT NULL,
                    total_articles_checked INTEGER NOT NULL,
                    duplicate_groups_found INTEGER NOT NULL,
                    batch_id TEXT,
                    status TEXT NOT NULL,
                    timestamp DATETIME NOT NULL
                )
            " );
            
            // 重複文章詳情表
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS duplicate_articles (
                    duplicate_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    check_id INTEGER NOT NULL,
                    group_id INTEGER NOT NULL,
                    post_id INTEGER NOT NULL,
                    post_title TEXT,
                    post_url TEXT,
                    similarity_score REAL,
                    FOREIGN KEY (check_id) REFERENCES duplicate_checks(check_id) ON DELETE CASCADE
                )
            " );
            
            // 重複文章組表
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS duplicate_groups (
                    group_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    check_id INTEGER NOT NULL,
                    group_name TEXT,
                    articles_json TEXT NOT NULL,
                    article_count INTEGER NOT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (check_id) REFERENCES duplicate_checks(check_id) ON DELETE CASCADE
                )
            " );
            
            // Usage summary table - 用於快速查詢和報表
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS usage_summary (
                    summary_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    year INTEGER NOT NULL,
                    month INTEGER NOT NULL,
                    day INTEGER NOT NULL,
                    total_tokens INTEGER NOT NULL,
                    total_cost REAL NOT NULL,
                    last_updated DATETIME NOT NULL
                )
            " );
            
            // Usage settings table
            $this->pdo->exec( "
                CREATE TABLE IF NOT EXISTS usage_settings (
                    setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_name TEXT NOT NULL UNIQUE,
                    setting_value TEXT NOT NULL,
                    last_updated DATETIME NOT NULL
                )
            " );
            
            // 初始化默認設置（如果表是新創建的）
            $settings_count = $this->pdo->query("SELECT COUNT(*) FROM usage_settings")->fetchColumn();
            if ($settings_count == 0) {
                $default_settings = [
                    ['token_rate', '0.15', current_time('mysql', 1)], // text-embedding-3-small的默認費率
                    ['budget_limit', '0', current_time('mysql', 1)], // 0 表示無限制
                    ['enable_alerts', 'no', current_time('mysql', 1)],
                    ['alert_threshold', '80', current_time('mysql', 1)], // 預算警報閾值，百分比
                    ['batch_size', '5', current_time('mysql', 1)] // 批處理大小
                ];
                
                $stmt = $this->pdo->prepare("INSERT INTO usage_settings (setting_name, setting_value, last_updated) VALUES (?, ?, ?)");
                foreach ($default_settings as $setting) {
                    $stmt->execute($setting);
                }
            }
            
            return true;
        } catch ( PDOException $e ) {
            error_log( 'WP SEO Vector Importer DB Schema Error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Insert or update a vector for a given post ID.
     *
     * @param int    $post_id WordPress Post ID.
     * @param string $vector  Vector data (e.g., JSON string).
     * @param string $url     Post URL.
     * @param string $categories Categories as JSON string.
     * @return bool True on success, false on failure.
     */
    public function insert_or_update_vector( $post_id, $vector, $url = '', $categories = '' ) {
        if ( ! $this->pdo ) {
            return false;
        }

        $sql = "INSERT OR REPLACE INTO vectors (post_id, vector, url, categories, last_updated) VALUES (:post_id, :vector, :url, :categories, :last_updated)";
        try {
            $stmt = $this->pdo->prepare( $sql );
            $stmt->execute( [
                ':post_id'      => $post_id,
                ':vector'       => $vector, // Assuming vector is stored as text (JSON)
                ':url'          => $url,
                ':categories'   => $categories,
                ':last_updated' => current_time( 'mysql', 1 ), // Use WordPress current time in GMT
            ] );
            return true;
        } catch ( PDOException $e ) {
            error_log( 'WP SEO Vector Importer DB Insert/Update Error: ' . $e->getMessage() );
            $this->log_error( $post_id, 'Database insert/update failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Delete a vector for a given post ID.
     *
     * @param int $post_id WordPress Post ID.
     * @return bool True on success, false on failure.
     */
    public function delete_vector( $post_id ) {
        if ( ! $this->pdo ) {
            return false;
        }

        $sql = "DELETE FROM vectors WHERE post_id = :post_id";
        try {
            $stmt = $this->pdo->prepare( $sql );
            $stmt->execute( [ ':post_id' => $post_id ] );
            return $stmt->rowCount() > 0; // Return true if a row was deleted
        } catch ( PDOException $e ) {
            error_log( 'WP SEO Vector Importer DB Delete Error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Log an error message.
     *
     * @param int|null $post_id       Associated Post ID (optional).
     * @param string   $error_message The error message.
     * @return bool True on success, false on failure.
     */
    public function log_error( $post_id, $error_message ) {
        if ( ! $this->pdo ) {
            return false;
        }

        $sql = "INSERT INTO error_log (post_id, error_message, timestamp) VALUES (:post_id, :error_message, :timestamp)";
        try {
            $stmt = $this->pdo->prepare( $sql );
            $stmt->execute( [
                ':post_id'       => $post_id,
                ':error_message' => $error_message,
                ':timestamp'     => current_time( 'mysql', 1 ),
            ] );
            return true;
        } catch ( PDOException $e ) {
            // Avoid infinite loop if logging itself fails
            error_log( 'WP SEO Vector Importer DB Log Error Failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Retrieve error logs.
     *
     * @param int $limit Number of logs to retrieve.
     * @param int $offset Offset for pagination.
     * @return array|false Array of log entries or false on failure.
     */
    public function get_error_logs( $limit = 50, $offset = 0 ) {
        if ( ! $this->pdo ) {
            return false;
        }

        $sql = "SELECT * FROM error_log ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
        try {
            $stmt = $this->pdo->prepare( $sql );
            $stmt->bindValue( ':limit', (int) $limit, PDO::PARAM_INT );
            $stmt->bindValue( ':offset', (int) $offset, PDO::PARAM_INT );
            $stmt->execute();
            return $stmt->fetchAll( PDO::FETCH_ASSOC );
        } catch ( PDOException $e ) {
            error_log( 'WP SEO Vector Importer DB Get Logs Error: ' . $e->getMessage() );
            return false;
        }
    }

     /**
     * Get the count of total error logs.
     *
     * @return int|false Total number of logs or false on failure.
     */
    public function get_error_log_count() {
        if (!$this->pdo) {
            return false;
        }
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM error_log");
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Log Count Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all error logs.
     *
     * @return bool True on success, false on failure.
     */
    public function clear_error_logs() {
        if ( ! $this->pdo ) {
            return false;
        }
        try {
            $this->pdo->exec( "DELETE FROM error_log" );
            // Optional: Reset autoincrement counter if needed (SQLite specific)
            // $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name='error_log'");
            return true;
        } catch ( PDOException $e ) {
            error_log( 'WP SEO Vector Importer DB Clear Logs Error: ' . $e->getMessage() );
            return false;
        }
    }


    /**
     * Get vector status (last updated time) for a specific post ID.
     *
     * @param int $post_id WordPress Post ID.
     * @return string|false Last updated timestamp (YYYY-MM-DD HH:MM:SS) or false if not found/error.
     */
    public function get_vector_status( $post_id ) {
        if ( ! $this->pdo ) {
            return false;
        }

        $sql = "SELECT last_updated FROM vectors WHERE post_id = :post_id";
        try {
            $stmt = $this->pdo->prepare( $sql );
            $stmt->execute( [ ':post_id' => $post_id ] );
            $result = $stmt->fetch( PDO::FETCH_ASSOC );
            return $result ? $result['last_updated'] : false;
        } catch ( PDOException $e ) {
            error_log( 'WP SEO Vector Importer DB Get Status Error: ' . $e->getMessage() );
            return false;
        }
    }

     /**
     * Get vector status for multiple post IDs.
     *
     * @param array $post_ids Array of WordPress Post IDs.
     * @return array|false Associative array [post_id => last_updated] or false on error.
     */
    public function get_vector_statuses(array $post_ids) {
        if (!$this->pdo || empty($post_ids)) {
            return false;
        }

        // Sanitize post IDs to ensure they are integers
        $post_ids = array_map('intval', $post_ids);
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

        $sql = "SELECT post_id, last_updated FROM vectors WHERE post_id IN ($placeholders)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($post_ids);
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetches into [post_id => last_updated]
            return $results ?: []; // Return empty array if no results found
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Statuses Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 記錄token使用情況
     *
     * @param int $tokens 使用的token數量
     * @param float $cost 估計成本
     * @param int|null $post_id 相關文章ID（可選）
     * @param string $operation_type 操作類型
     * @param string|null $batch_id 批次處理ID（可選）
     * @return bool 成功或失敗
     */
    public function log_token_usage($tokens, $cost, $post_id = null, $operation_type = 'single_embedding', $batch_id = null) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            // 記錄詳細使用情況
            $sql = "INSERT INTO token_usage (post_id, tokens_used, estimated_cost, operation_type, batch_id, timestamp) 
                    VALUES (:post_id, :tokens, :cost, :operation_type, :batch_id, :timestamp)";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':post_id' => $post_id,
                ':tokens' => $tokens,
                ':cost' => $cost,
                ':operation_type' => $operation_type,
                ':batch_id' => $batch_id,
                ':timestamp' => current_time('mysql', 1)
            ]);
            
            // 更新每日摘要
            $this->update_usage_summary($tokens, $cost);
            
            return true;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Log Token Usage Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新使用量摘要
     *
     * @param int $tokens 使用的token數量
     * @param float $cost 估計成本
     * @return bool 成功或失敗
     */
    private function update_usage_summary($tokens, $cost) {
        if (!$this->pdo) {
            return false;
        }
        
        $now = current_time('timestamp', 1); // 獲取GMT時間戳
        $year = gmdate('Y', $now);
        $month = gmdate('m', $now);
        $day = gmdate('d', $now);
        
        try {
            // 檢查當天摘要是否存在
            $sql = "SELECT summary_id FROM usage_summary 
                    WHERE year = :year AND month = :month AND day = :day";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':year' => $year,
                ':month' => $month,
                ':day' => $day
            ]);
            
            $summary_id = $stmt->fetchColumn();
            
            if ($summary_id) {
                // 更新現有記錄
                $sql = "UPDATE usage_summary SET 
                        total_tokens = total_tokens + :tokens,
                        total_cost = total_cost + :cost,
                        last_updated = :timestamp
                        WHERE summary_id = :summary_id";
                        
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':tokens' => $tokens,
                    ':cost' => $cost,
                    ':timestamp' => current_time('mysql', 1),
                    ':summary_id' => $summary_id
                ]);
            } else {
                // 創建新記錄
                $sql = "INSERT INTO usage_summary (year, month, day, total_tokens, total_cost, last_updated)
                        VALUES (:year, :month, :day, :tokens, :cost, :timestamp)";
                        
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':year' => $year,
                    ':month' => $month,
                    ':day' => $day,
                    ':tokens' => $tokens,
                    ':cost' => $cost,
                    ':timestamp' => current_time('mysql', 1)
                ]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Update Summary Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取使用統計數據
     *
     * @param string $period 時間週期 ('day', 'month', 'year')
     * @param int $limit 限制結果數量
     * @return array|false 統計數據陣列或失敗時返回false
     */
    public function get_usage_statistics($period = 'month', $limit = 30) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            switch ($period) {
                case 'day':
                    $sql = "SELECT year, month, day, total_tokens, total_cost, last_updated
                            FROM usage_summary
                            ORDER BY year DESC, month DESC, day DESC
                            LIMIT :limit";
                    break;
                    
                case 'month':
                    $sql = "SELECT year, month, SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost, 
                            MAX(last_updated) as last_updated
                            FROM usage_summary
                            GROUP BY year, month
                            ORDER BY year DESC, month DESC
                            LIMIT :limit";
                    break;
                    
                case 'year':
                    $sql = "SELECT year, SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost,
                            MAX(last_updated) as last_updated
                            FROM usage_summary
                            GROUP BY year
                            ORDER BY year DESC
                            LIMIT :limit";
                    break;
                    
                default:
                    return false;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Usage Statistics Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取當前月份的使用量統計
     *
     * @return array|false 包含total_tokens和total_cost的陣列，或失敗時返回false
     */
    public function get_current_month_usage() {
        if (!$this->pdo) {
            return false;
        }
        
        $now = current_time('timestamp', 1);
        $year = gmdate('Y', $now);
        $month = gmdate('m', $now);
        
        try {
            $sql = "SELECT SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost
                    FROM usage_summary
                    WHERE year = :year AND month = :month";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':year' => $year,
                ':month' => $month
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: ['total_tokens' => 0, 'total_cost' => 0];
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Month Usage Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取設定值
     *
     * @param string $setting_name 設定名稱
     * @param mixed $default_value 默認值（找不到時返回）
     * @return mixed 設定值或默認值
     */
    public function get_setting($setting_name, $default_value = '') {
        if (!$this->pdo) {
            return $default_value;
        }
        
        try {
            $sql = "SELECT setting_value FROM usage_settings WHERE setting_name = :name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':name' => $setting_name]);
            
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : $default_value;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Setting Error: ' . $e->getMessage());
            return $default_value;
        }
    }
    
    /**
     * 更新設定值
     *
     * @param string $setting_name 設定名稱
     * @param string $setting_value 設定值
     * @return bool 成功或失敗
     */
    public function update_setting($setting_name, $setting_value) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $sql = "INSERT OR REPLACE INTO usage_settings (setting_name, setting_value, last_updated)
                    VALUES (:name, :value, :timestamp)";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':name' => $setting_name,
                ':value' => $setting_value,
                ':timestamp' => current_time('mysql', 1)
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Update Setting Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取所有設定
     *
     * @return array|false 所有設定的陣列，或失敗時返回false
     */
    public function get_all_settings() {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $sql = "SELECT setting_name, setting_value FROM usage_settings";
            $stmt = $this->pdo->query($sql);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_name']] = $row['setting_value'];
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get All Settings Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取批次處理的token統計
     *
     * @param string $batch_id 批次處理ID
     * @return array|false 批次處理的統計數據，或失敗時返回false
     */
    public function get_batch_statistics($batch_id) {
        if (!$this->pdo || empty($batch_id)) {
            return false;
        }
        
        try {
            $sql = "SELECT COUNT(*) as processed_count, SUM(tokens_used) as total_tokens, 
                    SUM(estimated_cost) as total_cost, 
                    MIN(timestamp) as start_time, MAX(timestamp) as end_time
                    FROM token_usage
                    WHERE batch_id = :batch_id";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':batch_id' => $batch_id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [
                'processed_count' => 0,
                'total_tokens' => 0,
                'total_cost' => 0
            ];
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Batch Statistics Error: ' . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * 清理舊的token使用記錄
     *
     * @param int $days_to_keep 保留的天數
     * @return bool 成功或失敗
     */
    public function cleanup_old_token_logs($days_to_keep = 90) {
        if (!$this->pdo) {
            return false;
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days", current_time('timestamp', 1)));
        
        try {
            $sql = "DELETE FROM token_usage WHERE timestamp < :cutoff_date";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':cutoff_date' => $cutoff_date]);
            
            return true;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Cleanup Token Logs Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取向量數據庫中的文章總數
     *
     * @return int|false 向量總數或失敗時返回false
     */
    public function get_vector_count() {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM vectors");
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Vector Count Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 取得所有向量數據
     *
     * @param int $limit 限制結果數量
     * @param int $offset 偏移量，用於分頁
     * @return array|false 向量數據陣列或失敗時返回false
     */
    public function get_all_vectors($limit = 1000, $offset = 0) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $sql = "SELECT post_id, vector, url, categories, last_updated FROM vectors LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get All Vectors Error: ' . $e->getMessage());
            return false;
        }
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
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $sql = "INSERT INTO duplicate_checks (
                        check_date, 
                        similarity_threshold, 
                        model_used, 
                        total_articles_checked, 
                        duplicate_groups_found, 
                        batch_id, 
                        status, 
                        timestamp
                    ) VALUES (
                        :check_date, 
                        :threshold, 
                        :model, 
                        0, 
                        0, 
                        :batch_id, 
                        'processing', 
                        :timestamp
                    )";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':check_date' => current_time('mysql', 1),
                ':threshold' => $threshold,
                ':model' => $model_used,
                ':batch_id' => $batch_id,
                ':timestamp' => current_time('mysql', 1)
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Create Duplicate Check Error: ' . $e->getMessage());
            return false;
        }
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
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $updateFields = ['status = :status'];
            $params = [
                ':check_id' => $check_id,
                ':status' => $status
            ];
            
            if ($total_checked !== null) {
                $updateFields[] = 'total_articles_checked = :total_checked';
                $params[':total_checked'] = $total_checked;
            }
            
            if ($duplicates_found !== null) {
                $updateFields[] = 'duplicate_groups_found = :duplicates_found';
                $params[':duplicates_found'] = $duplicates_found;
            }
            
            $sql = "UPDATE duplicate_checks SET " . implode(', ', $updateFields) . " WHERE check_id = :check_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return true;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Update Duplicate Check Status Error: ' . $e->getMessage());
            return false;
        }
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
        if (!$this->pdo || empty($articles)) {
            return false;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $sql = "INSERT INTO duplicate_articles (
                        check_id, 
                        group_id, 
                        post_id, 
                        post_title, 
                        post_url, 
                        similarity_score
                    ) VALUES (
                        :check_id, 
                        :group_id, 
                        :post_id, 
                        :post_title, 
                        :post_url, 
                        :similarity_score
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($articles as $article) {
                $stmt->execute([
                    ':check_id' => $check_id,
                    ':group_id' => $group_id,
                    ':post_id' => $article['post_id'],
                    ':post_title' => $article['post_title'],
                    ':post_url' => $article['post_url'],
                    ':similarity_score' => isset($article['similarity_score']) ? $article['similarity_score'] : null
                ]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log('WP SEO Vector Importer DB Save Duplicate Group Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取重複檢測記錄
     *
     * @param int $check_id 檢測記錄ID
     * @return array|false 檢測記錄或失敗時返回false
     */
    public function get_duplicate_check($check_id) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $sql = "SELECT * FROM duplicate_checks WHERE check_id = :check_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':check_id' => $check_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Duplicate Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取所有重複檢測記錄
     *
     * @param int $limit 限制結果數量
     * @param int $offset 偏移量，用於分頁
     * @return array|false 檢測記錄陣列或失敗時返回false
     */
    public function get_duplicate_checks($limit = 10, $offset = 0) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $sql = "SELECT * FROM duplicate_checks ORDER BY check_date DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Duplicate Checks Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取一次檢測的所有重複文章組
     *
     * @param int $check_id 檢測記錄ID
     * @return array|false 重複文章組陣列或失敗時返回false
     */
    public function get_duplicate_groups($check_id) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            // 先獲取所有不同的組ID
            $sql = "SELECT DISTINCT group_id FROM duplicate_articles WHERE check_id = :check_id ORDER BY group_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':check_id' => $check_id]);
            $group_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($group_ids)) {
                return [];
            }
            
            // 獲取每個組的文章
            $result = [];
            foreach ($group_ids as $group_id) {
                $sql = "SELECT * FROM duplicate_articles 
                        WHERE check_id = :check_id AND group_id = :group_id 
                        ORDER BY similarity_score DESC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':check_id' => $check_id,
                    ':group_id' => $group_id
                ]);
                
                $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($articles)) {
                    $result[] = $articles;
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log('WP SEO Vector Importer DB Get Duplicate Groups Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Destructor. Closes the database connection.
     */
    public function __destruct() {
        $this->pdo = null;
    }
}
?>
