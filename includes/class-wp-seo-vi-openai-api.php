<?php
/**
 * Handles interactions with the OpenAI API.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WP_SEO_VI_OpenAI_API {

    /**
     * OpenAI API Key.
     * @var string
     */
    private $api_key;

    /**
     * OpenAI API base URL.
     * @var string
     */
    private $base_url = 'https://api.openai.com/v1/';

    /**
     * Embedding model identifier.
     * @var string
     */
    private $embedding_model = 'text-embedding-3-small'; // Default to small
    
    /**
     * 不同模型的費率（根據2025年最新定價）
     * @var array
     */
    private $model_rates = [
        'text-embedding-3-small' => 0.15,  // $0.15/百萬tokens
        'text-embedding-3-large' => 1.3,   // $1.30/百萬tokens
        'gpt-4o-mini' => [
            'input' => 0.15,              // $0.15/百萬input tokens
            'cached_input' => 0.075,      // $0.075/百萬cached input tokens
            'output' => 0.60,             // $0.60/百萬output tokens
            'batch_input' => 0.075,       // 批處理：$0.075/百萬input tokens (50% off)
            'batch_output' => 0.30        // 批處理：$0.30/百萬output tokens (50% off)
        ]
    ];
    
    /**
     * GPT模型標識符
     * @var string
     */
    private $gpt_model = 'gpt-4o-mini';

    /**
     * Constructor.
     *
     * @param string $api_key OpenAI API Key.
     */
    public function __construct( $api_key ) {
        $this->api_key = trim( $api_key );
        
        // 從數據庫獲取模型費率設置（如果存在）
        $db = new WP_SEO_VI_Database();
        $embedding_rate = floatval($db->get_setting('token_rate', '0.15'));
        $this->model_rates['text-embedding-3-small'] = $embedding_rate;
        
        // 嘗試獲取 GPT-4o-mini 費率
        $gpt4o_mini_rate = floatval($db->get_setting('gpt4o_mini_rate', '5.0'));
        $this->model_rates['gpt-4o-mini'] = $gpt4o_mini_rate;
    }

    /**
     * Make a request to the OpenAI API.
     *
     * @param string $endpoint API endpoint (e.g., 'embeddings', 'models').
     * @param array  $data     Request data.
     * @param string $method   HTTP method ('POST', 'GET').
     * @return array|WP_Error Decoded JSON response on success, WP_Error on failure.
     */
    private function make_request( $endpoint, $data = [], $method = 'POST' ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'api_key_missing', __( 'OpenAI API Key is not set.', 'wp-seo-vector-importer' ) );
        }

        $url = $this->base_url . $endpoint;
        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 60, // Increase timeout for potentially long requests
        ];

        if ( ! empty( $data ) && $method === 'POST' ) {
            $args['body'] = wp_json_encode( $data );
        } elseif ( ! empty( $data ) && $method === 'GET' ) {
             $url = add_query_arg( $data, $url );
        }


        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response; // Return WP_Error from wp_remote_request
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $decoded_body = json_decode( $response_body, true );

        if ( $response_code >= 200 && $response_code < 300 ) {
            return $decoded_body;
        } else {
            $error_message = isset( $decoded_body['error']['message'] ) ? $decoded_body['error']['message'] : __( 'Unknown API error', 'wp-seo-vector-importer' );
            $error_code = isset( $decoded_body['error']['code'] ) ? $decoded_body['error']['code'] : 'api_error';
             if ($response_code === 401) {
                 $error_message = __('Invalid OpenAI API Key or insufficient permissions.', 'wp-seo-vector-importer');
                 $error_code = 'invalid_api_key';
             } elseif ($response_code === 429) {
                 $error_message = __('OpenAI API rate limit exceeded. Please try again later.', 'wp-seo-vector-importer');
                 $error_code = 'rate_limit_exceeded';
             }
            return new WP_Error( $error_code, $error_message, [ 'status' => $response_code ] );
        }
    }

    /**
     * Validate the API key by trying to list models.
     *
     * @return bool|WP_Error True if valid, WP_Error on failure.
     */
    public function validate_api_key() {
        $result = $this->make_request( 'models', [], 'GET' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        // If the request didn't return an error, the key is considered valid
        return true;
    }

    /**
     * 計算token使用成本
     *
     * @param int $tokens Token數量
     * @return float 估計成本（美元）
     */
    private function calculate_cost($tokens) {
        // 獲取從數據庫設置的費率，如果不存在則使用默認費率
        global $wpdb;
        $db = new WP_SEO_VI_Database();
        $token_rate = floatval($db->get_setting('token_rate', '0.15')); // text-embedding-3-small的默認費率是$0.15/百萬token
        
        // 計算成本：tokens / 1,000,000 * rate
        return ($tokens / 1000000) * $token_rate;
    }
    
    /**
     * 記錄token使用情況
     *
     * @param int $tokens Token數量
     * @param int|null $post_id 相關文章ID（可選）
     * @param string $operation_type 操作類型
     * @param string|null $batch_id 批次處理ID（可選）
     * @param string $model_type 使用的模型類型
     * @param string $token_type 令牌類型 ('input', 'output', 'batch_input', 'batch_output')
     * @return bool 成功或失敗
     */
    private function log_usage($tokens, $post_id = null, $operation_type = 'single_embedding', $batch_id = null, $model_type = 'text-embedding-3-small', $token_type = 'input') {
        // 根據模型類型和token類型計算估計成本
        $rate = 0;
        
        if ($model_type === 'gpt-4o-mini' && is_array($this->model_rates['gpt-4o-mini'])) {
            // GPT-4o-mini 使用不同的費率結構
            if (isset($this->model_rates['gpt-4o-mini'][$token_type])) {
                $rate = $this->model_rates['gpt-4o-mini'][$token_type];
            } else {
                // 默認使用input費率
                $rate = $this->model_rates['gpt-4o-mini']['input'];
            }
        } else {
            // 向量嵌入模型使用單一費率
            $rate = isset($this->model_rates[$model_type]) ? $this->model_rates[$model_type] : $this->model_rates['text-embedding-3-small'];
        }
        
        $cost = ($tokens / 1000000) * $rate;
        
        // 檢查是否會超過預算上限
        $db = new WP_SEO_VI_Database();
        
        // 如果開啟了強制預算限制，並且當前操作會超過預算，則記錄失敗並返回 false
        if ($db->get_setting('enforce_budget_limit', 'no') === 'yes' && $db->is_budget_exceeded($cost)) {
            $error_message = __('Operation was not performed due to budget limit restrictions.', 'wp-seo-vector-importer');
            if ($post_id) {
                $db->log_error($post_id, $error_message);
            } else {
                error_log('WP SEO Vector Importer: ' . $error_message);
            }
            
            // 發送預算警報（如果啟用）
            if ($db->get_setting('enable_alerts', 'no') === 'yes') {
                $this->send_budget_alert();
            }
            
            return false;
        }
        
        // 記錄使用情況
        return $db->log_token_usage($tokens, $cost, $post_id, $operation_type, $batch_id, $model_type . '_' . $token_type);
    }
    
    /**
     * 發送預算警報
     */
    private function send_budget_alert() {
        // 檢查是否已經發送過當日警報（避免頻繁發送）
        $db = new WP_SEO_VI_Database();
        $last_alert_date = $db->get_setting('last_alert_date', '');
        $current_date = date('Y-m-d');
        
        if ($last_alert_date === $current_date) {
            return; // 當日已發送過警報
        }
        
        // 獲取當前使用和預算
        $current_usage = $db->get_current_month_usage();
        $budget_limit = floatval($db->get_setting('budget_limit', '0'));
        
        if ($budget_limit <= 0 || $current_usage === false) {
            return; // 無預算限制或獲取使用量失敗
        }
        
        $current_cost = floatval($current_usage['total_cost']);
        $usage_percent = ($current_cost / $budget_limit) * 100;
        $alert_threshold = intval($db->get_setting('alert_threshold', '80'));
        
        // 如果使用百分比超過閾值，發送警報
        if ($usage_percent >= $alert_threshold) {
            $admin_email = get_option('admin_email');
            $subject = __('OpenAI API 預算警報 - WP SEO Vector Importer', 'wp-seo-vector-importer');
            $message = sprintf(
                __("您的OpenAI API使用量已接近或超過預設的預算閾值。\n\n當前月份使用: $%s\n預算上限: $%s\n使用百分比: %s%%\n\n請登入WordPress管理介面查看詳細統計資訊。", 'wp-seo-vector-importer'),
                number_format($current_cost, 2),
                number_format($budget_limit, 2),
                number_format($usage_percent, 2)
            );
            
            wp_mail($admin_email, $subject, $message);
            
            // 更新最後警報日期
            $db->update_setting('last_alert_date', $current_date);
        }
    }

    /**
     * Get embedding for a given text input.
     *
     * @param string|array $input Text or array of texts to embed.
     * @param int|null $post_id Associated post ID (optional).
     * @param string $operation_type Operation type for logging.
     * @param string|null $batch_id Batch ID for grouped operations.
     * @return array|WP_Error Array containing the embedding vector(s) on success, WP_Error on failure.
     */
    public function get_embedding($input, $post_id = null, $operation_type = 'single_embedding', $batch_id = null) {
        if (empty($input)) {
            return new WP_Error('empty_input', __('Input text cannot be empty.', 'wp-seo-vector-importer'));
        }
        
        // 如果輸入文本太長，進行極度保守的智能截斷確保不超過Token限制
        if (is_string($input)) {
            $input = $this->truncate_text_to_token_limit($input, 6000); // 更激進的截斷 - 只使用約73%的上限
        } elseif (is_array($input)) {
            foreach ($input as $key => $text) {
                if (is_string($text)) {
                    $input[$key] = $this->truncate_text_to_token_limit($text, 6000);
                }
            }
        }

        $data = [
            'input' => $input,
            'model' => $this->embedding_model,
            // 'encoding_format' => 'float' // Default
            // 'dimensions' => 1536 // Default for text-embedding-3-small
        ];

        $result = $this->make_request('embeddings', $data, 'POST');

        if (is_wp_error($result)) {
            return $result;
        }

        // Check if the expected data structure is present
        if (isset($result['data']) && is_array($result['data']) && isset($result['data'][0]['embedding'])) {
            // 記錄token使用情況
            if (isset($result['usage']) && isset($result['usage']['total_tokens'])) {
                $this->log_usage(
                    $result['usage']['total_tokens'],
                    $post_id,
                    $operation_type,
                    $batch_id
                );
            }
            
            // If input was a single string, return the single embedding array
            if (is_string($input)) {
                return $result['data'][0]['embedding'];
            }
            // If input was an array, return the array of embeddings
            return $result['data']; // Contains array of objects like { index: 0, object: 'embedding', embedding: [...] }
        } else {
            return new WP_Error('invalid_response_format', __('Unexpected response format from OpenAI API.', 'wp-seo-vector-importer'), $result);
        }
    }
    
    /**
     * 獲取文本的token數量估算（更加嚴格的版本）
     *
     * @param string $text 要估算的文本
     * @return int 估算的token數量
     */
    public function estimate_tokens($text) {
        // 更加保守的估算方法：
        // 1. 對於中文字符，使用更高的token消耗率
        // 2. 增加更大的安全係數
        // 3. 特別處理長文本
        
        $text = trim($text);
        $chars = mb_strlen($text);
        
        // 檢測中文字符的比例
        $chinese_chars = preg_match_all('/\p{Han}/u', $text, $matches);
        $chinese_ratio = $chars > 0 ? $chinese_chars / $chars : 0;
        
        // 對中文內容使用更保守的係數：中文字符可能產生更多的tokens
        // 中文字符比例越高，每個字符的平均token成本越高
        $chars_per_token = 2.0; // 基本估算：每2個字符約1個token
        
        if ($chinese_ratio > 0.5) { // 如果中文佔比超過50%
            $chars_per_token = 1.5; // 每1.5個字符約1個token (更保守)
        }
        
        // 計算基本估算
        $estimated_tokens = ceil($chars / $chars_per_token);
        
        // 根據文本長度增加更大的安全係數
        $safety_factor = 1.3; // 基本安全係數為30%
        if ($chars > 10000) {
            $safety_factor = 1.5; // 非常長的文本使用50%的安全係數
        } elseif ($chars > 5000) {
            $safety_factor = 1.4; // 較長文本使用40%的安全係數
        }
        
        $estimated_tokens = ceil($estimated_tokens * $safety_factor);
        
        return (int)$estimated_tokens;
    }
    
    /**
     * 智能截取文本以控制token數量（極度保守版本）
     *
     * @param string $text 原始文本
     * @param int $max_tokens 最大token數量
     * @return string 截取後的文本
     */
    public function truncate_text_to_token_limit($text, $max_tokens = 6000) {
        // 更大幅度降低默認最大token限制到6000，為API限制(8192)留出非常充足的安全餘量
        // 使用百分比：約為API限制的73%，確保安全餘量
        
        // 先進行估算
        $estimated_tokens = $this->estimate_tokens($text);
        
        // 如果已在安全範圍內，直接返回
        if ($estimated_tokens <= $max_tokens) {
            return $text;
        }
        
        // 計算截斷比例 - 保守處理
        $truncation_ratio = ($max_tokens / $estimated_tokens) * 0.85; // 再增加15%的安全餘量
        
        // 計算字符限制
        $total_chars = mb_strlen($text);
        $target_length = floor($total_chars * $truncation_ratio);
        
        // 避免截取太少
        $target_length = max($target_length, 1000); // 確保至少保留1000個字符
        $target_length = min($target_length, $total_chars); // 但不超過原始長度
        
        // 初步截取
        $truncated_text = mb_substr($text, 0, $target_length);
        
        // 優先在自然段落結束處截斷
        $last_double_newline = mb_strrpos($truncated_text, "\n\n");
        if ($last_double_newline !== false && $last_double_newline > $target_length * 0.7) {
            return mb_substr($text, 0, $last_double_newline);
        }
        
        // 嘗試在句子結束處截斷（優先考慮中文句號）
        $punctuations = ['。', '.', '！', '!', '？', '?'];
        foreach ($punctuations as $punct) {
            $last_punct = mb_strrpos($truncated_text, $punct);
            if ($last_punct !== false && $last_punct > $target_length * 0.7) {
                return mb_substr($text, 0, $last_punct + 1);
            }
        }
        
        // 如果沒有找到合適的句子結束處，保守地進一步截斷
        return mb_substr($truncated_text, 0, floor($target_length * 0.95));
    }
    
    /**
     * 使用 GPT-4o-mini 模型比較兩篇文章的相似度
     *
     * @param string $text1 第一篇文章的內容
     * @param string $text2 第二篇文章的內容
     * @param int|null $post_id 相關文章ID（可選）
     * @param string $batch_id 批次ID（可選）
     * @return array|WP_Error 分析結果或錯誤
     */
    public function compare_texts_with_gpt($text1, $text2, $post_id = null, $batch_id = null) {
        if (empty($text1) || empty($text2)) {
            return new WP_Error('empty_input', __('文章內容不能為空', 'wp-seo-vector-importer'));
        }
        
        // 截斷文本以避免超出token限制
        $text1 = $this->truncate_text_to_token_limit($text1, 2000);
        $text2 = $this->truncate_text_to_token_limit($text2, 2000);
        
        $prompt = sprintf(
            "你是一個專業文章比對系統。請分析以下兩篇文章內容的相似度。\n\n" .
            "文章1:\n%s\n\n" .
            "文章2:\n%s\n\n" .
            "請提供以下格式的分析：\n" .
            "1. 相似度評分：一個從0到1的數字，0表示完全不同，1表示完全相同\n" .
            "2. 重複內容：列出主要重複的部分\n" .
            "3. 分析：簡要說明為什麼判斷這些文章相似或不相似\n" .
            "請以JSON格式回覆，包含以下欄位：similarity_score, duplicate_content, analysis",
            $text1,
            $text2
        );
        
        $data = [
            'model' => $this->gpt_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是一個專業的文章相似度比對系統，提供客觀準確的分析。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'response_format' => [
                'type' => 'json_object'
            ],
            'temperature' => 0.2 // 使用較低的溫度獲得更確定的結果
        ];
        
        $result = $this->make_request('chat/completions', $data, 'POST');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // 檢查回應格式
        if (isset($result['choices']) && !empty($result['choices'][0]['message']['content'])) {
            // 記錄token使用情況（分別記錄輸入和輸出tokens）
            if (isset($result['usage'])) {
                // 記錄輸入tokens
                if (isset($result['usage']['prompt_tokens'])) {
                    $token_type = $batch_id ? 'batch_input' : 'input';
                    $this->log_usage(
                        $result['usage']['prompt_tokens'],
                        $post_id,
                        'gpt_compare',
                        $batch_id,
                        'gpt-4o-mini',
                        $token_type
                    );
                }
                
                // 記錄輸出tokens
                if (isset($result['usage']['completion_tokens'])) {
                    $token_type = $batch_id ? 'batch_output' : 'output';
                    $this->log_usage(
                        $result['usage']['completion_tokens'],
                        $post_id,
                        'gpt_compare',
                        $batch_id,
                        'gpt-4o-mini',
                        $token_type
                    );
                }
            }
            
            // 解析JSON回應
            $response_content = $result['choices'][0]['message']['content'];
            $parsed_response = json_decode($response_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && !empty($parsed_response)) {
                return $parsed_response;
            } else {
                // 如果不是有效的JSON，嘗試解析文本回應
                return [
                    'raw_response' => $response_content,
                    'error' => 'Invalid JSON response format'
                ];
            }
        }
        
        return new WP_Error('invalid_response', __('無效的API回應格式', 'wp-seo-vector-importer'));
    }
    
    /**
     * 設置使用的模型
     *
     * @param string $embedding_model 嵌入模型名稱
     * @param string $gpt_model GPT模型名稱
     */
    public function set_models($embedding_model = null, $gpt_model = null) {
        if ($embedding_model) {
            $this->embedding_model = $embedding_model;
        }
        
        if ($gpt_model) {
            $this->gpt_model = $gpt_model;
        }
    }
}
?>
