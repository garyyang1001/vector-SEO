<?php
/**
 * 顯示OpenAI API設定頁面。
 */

// 如果直接訪問此文件，中止執行
if (!defined('WPINC')) {
    die;
}

// 獲取設定
$token_rate = get_option('wp_seo_vi_token_rate', '0.15');
$budget_limit = get_option('wp_seo_vi_budget_limit', '0');
$enforce_budget_limit = get_option('wp_seo_vi_enforce_budget_limit', 'no');
$batch_size = get_option('wp_seo_vi_batch_size', '5');

// 顯示設置錯誤/更新信息
settings_errors('wp_seo_vi_settings');

// 檢查是否需要資料遷移
$needs_migration = wp_seo_vi_needs_migration();
$db_type = wp_seo_vi_get_db_type();
$can_use_sqlite = wp_seo_vi_can_use_sqlite();

// 取得當前分頁
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
?>

<div class="wrap wp-seo-vi-settings-page">
    <h1><?php _e('AI向量助手設定', 'wp-seo-vector-importer'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=wp-seo-vi-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API設定', 'wp-seo-vector-importer'); ?>
        </a>
        <a href="?page=wp-seo-vi-settings&tab=migration" class="nav-tab <?php echo $current_tab === 'migration' ? 'nav-tab-active' : ''; ?> <?php echo $needs_migration ? 'wp-seo-vi-tab-attention' : ''; ?>">
            <?php _e('資料遷移', 'wp-seo-vector-importer'); ?>
            <?php if ($needs_migration): ?>
                <span class="wp-seo-vi-notification-dot"></span>
            <?php endif; ?>
        </a>
    </h2>
    
    <?php if ($current_tab === 'general'): ?>
    <!-- API設定標籤 -->
    <form method="post" action="">
        <?php wp_nonce_field('wp_seo_vi_settings_nonce'); ?>
        <input type="hidden" name="wp_seo_vi_settings_submit" value="1">
        
        <div class="wp-seo-vi-settings-section postbox">
            <h2 class="hndle"><span><?php _e('向量嵌入API設定', 'wp-seo-vector-importer'); ?></span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wp_seo_vi_token_rate"><?php _e('Embedding費率 (USD/百萬token)', 'wp-seo-vector-importer'); ?></label></th>
                        <td>
                            <input type="number" id="wp_seo_vi_token_rate" name="wp_seo_vi_token_rate" value="<?php echo esc_attr($token_rate); ?>" step="0.001" min="0" class="regular-text">
                            <p class="description"><?php _e('text-embedding-3-small的預設費率是$0.15/百萬token', 'wp-seo-vector-importer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_seo_vi_batch_size"><?php _e('批次處理數量', 'wp-seo-vector-importer'); ?></label></th>
                        <td>
                            <input type="number" id="wp_seo_vi_batch_size" name="wp_seo_vi_batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1" max="50" class="small-text">
                            <p class="description"><?php _e('每批處理的文章數量，更大的數字可能更有效率但可能導致超時', 'wp-seo-vector-importer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_seo_vi_hybrid_threshold_reduction"><?php _e('智慧模式預篩選閾值降低', 'wp-seo-vector-importer'); ?></label></th>
                        <td>
                            <input type="range" id="wp_seo_vi_hybrid_threshold_reduction" name="wp_seo_vi_hybrid_threshold_reduction" 
                                   min="0.1" max="0.3" step="0.05" 
                                   value="<?php echo esc_attr(get_option('wp_seo_vi_hybrid_threshold_reduction', '0.25')); ?>" 
                                   oninput="document.getElementById('hybrid-threshold-value').textContent = this.value">
                            <span id="hybrid-threshold-value"><?php echo esc_html(get_option('wp_seo_vi_hybrid_threshold_reduction', '0.25')); ?></span>
                            <p class="description">
                                <?php _e('設定智慧模式中預篩選閾值比最終閾值低多少。值越大，預篩選越寬鬆，越不會遺漏相似文章，但GPT比對次數越多。', 'wp-seo-vector-importer'); ?><br>
                                <?php _e('推薦設置：0.25', 'wp-seo-vector-importer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="wp-seo-vi-settings-section postbox">
            <h2 class="hndle"><span><?php _e('GPT-4o-mini 設定', 'wp-seo-vector-importer'); ?></span></h2>
            <div class="inside">
                <p><?php _e('這些設置用於重複文章比對功能中的GPT-4o-mini模型', 'wp-seo-vector-importer'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('啟用批次處理API', 'wp-seo-vector-importer'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('啟用批次處理API', 'wp-seo-vector-importer'); ?></span></legend>
                                <label for="wp_seo_vi_use_batch_api">
                                    <input type="checkbox" id="wp_seo_vi_use_batch_api" name="wp_seo_vi_use_batch_api" value="yes" <?php checked(get_option('wp_seo_vi_use_batch_api', 'no'), 'yes'); ?>>
                                    <?php _e('使用OpenAI批次處理API（可節省50%費用）', 'wp-seo-vector-importer'); ?>
                                </label>
                                <p class="description"><?php _e('批次處理API將在24小時內非同步處理請求，可大幅節省費用但處理時間較長', 'wp-seo-vector-importer'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_seo_vi_duplicate_threshold"><?php _e('預設相似度閾值', 'wp-seo-vector-importer'); ?></label></th>
                        <td>
                            <input type="range" id="wp_seo_vi_duplicate_threshold" name="wp_seo_vi_duplicate_threshold" 
                                   min="0.5" max="1.0" step="0.01" 
                                   value="<?php echo esc_attr(get_option('wp_seo_vi_duplicate_threshold', '0.7')); ?>" 
                                   oninput="document.getElementById('duplicate-threshold-value').textContent = this.value">
                            <span id="duplicate-threshold-value"><?php echo esc_html(get_option('wp_seo_vi_duplicate_threshold', '0.7')); ?></span>
                            <p class="description"><?php _e('設定文章相似度的判定閾值（0.5-1.0），值越高要求越嚴格', 'wp-seo-vector-importer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_seo_vi_duplicate_model"><?php _e('預設比對模型', 'wp-seo-vector-importer'); ?></label></th>
                        <td>
                            <select id="wp_seo_vi_duplicate_model" name="wp_seo_vi_duplicate_model">
                                <option value="vector" <?php selected(get_option('wp_seo_vi_duplicate_model', 'vector'), 'vector'); ?>><?php _e('快速模式（節省API費用）', 'wp-seo-vector-importer'); ?></option>
                                <option value="hybrid" <?php selected(get_option('wp_seo_vi_duplicate_model', 'vector'), 'hybrid'); ?>><?php _e('智慧模式（平衡速度與準確度）', 'wp-seo-vector-importer'); ?></option>
                                <option value="gpt" <?php selected(get_option('wp_seo_vi_duplicate_model', 'vector'), 'gpt'); ?>><?php _e('精準模式（最高準確度，費用較高）', 'wp-seo-vector-importer'); ?></option>
                            </select>
                            <p class="description">
                                <strong><?php _e('快速模式', 'wp-seo-vector-importer'); ?></strong>: <?php _e('只使用向量比對，速度最快，適合大量文章初步篩選', 'wp-seo-vector-importer'); ?><br>
                                <strong><?php _e('智慧模式', 'wp-seo-vector-importer'); ?></strong>: <?php _e('系統自動優化處理方式，平衡成本與準確度（推薦）', 'wp-seo-vector-importer'); ?><br>
                                <strong><?php _e('精準模式', 'wp-seo-vector-importer'); ?></strong>: <?php _e('全部使用GPT分析，最準確但API費用較高', 'wp-seo-vector-importer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="wp-seo-vi-settings-section postbox">
            <h2 class="hndle"><span><?php _e('預算控制', 'wp-seo-vector-importer'); ?></span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wp_seo_vi_budget_limit"><?php _e('月度預算上限 (USD)', 'wp-seo-vector-importer'); ?></label></th>
                        <td>
                            <input type="number" id="wp_seo_vi_budget_limit" name="wp_seo_vi_budget_limit" value="<?php echo esc_attr($budget_limit); ?>" step="0.01" min="0" class="regular-text">
                            <p class="description"><?php _e('設置為0表示無限制，否則到達預算上限時將停止API請求', 'wp-seo-vector-importer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('強制執行預算限制', 'wp-seo-vector-importer'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('強制執行預算限制', 'wp-seo-vector-importer'); ?></span></legend>
                                <label for="wp_seo_vi_enforce_budget_limit">
                                    <input type="checkbox" id="wp_seo_vi_enforce_budget_limit" name="wp_seo_vi_enforce_budget_limit" value="yes" <?php checked($enforce_budget_limit, 'yes'); ?>>
                                    <?php _e('當達到預算上限時停止API呼叫', 'wp-seo-vector-importer'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="wp-seo-vi-settings-section postbox">
            <h2 class="hndle"><span><?php _e('數據管理', 'wp-seo-vector-importer'); ?></span></h2>
            <div class="inside">
                <p><?php _e('這些選項可以幫助管理插件收集的數據。請謹慎使用，因為一些操作無法撤消。', 'wp-seo-vector-importer'); ?></p>
                
                <button type="button" id="wp-seo-vi-cleanup-logs" class="button button-secondary">
                    <?php _e('清理過期資料', 'wp-seo-vector-importer'); ?>
                </button>
                <span id="wp-seo-vi-cleanup-status" style="margin-left: 10px;"></span>
                <p class="description"><?php _e('僅保留近90天的數據資料', 'wp-seo-vector-importer'); ?></p>
            </div>
        </div>
        
        <?php submit_button(__('保存設定', 'wp-seo-vector-importer')); ?>
    </form>

    <?php elseif ($current_tab === 'migration'): ?>
    <!-- 資料遷移標籤 -->
    <div class="wp-seo-vi-settings-section postbox">
        <h2 class="hndle"><span><?php _e('資料庫類型', 'wp-seo-vector-importer'); ?></span></h2>
        <div class="inside">
            <p>
                <strong><?php _e('目前使用的資料庫類型：', 'wp-seo-vector-importer'); ?></strong>
                <?php if ($db_type === WP_SEO_VI_DB_TYPE_SQLITE): ?>
                    <span class="wp-seo-vi-db-type-sqlite"><?php _e('SQLite（存儲在外掛目錄）', 'wp-seo-vector-importer'); ?></span>
                <?php else: ?>
                    <span class="wp-seo-vi-db-type-wpdb"><?php _e('WordPress 資料庫', 'wp-seo-vector-importer'); ?></span>
                <?php endif; ?>
            </p>
            
            <?php if (!$can_use_sqlite): ?>
                <div class="notice notice-warning">
                    <p><?php _e('您的伺服器環境不支援 SQLite。請使用 WordPress 資料庫以確保相容性。', 'wp-seo-vector-importer'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($needs_migration): ?>
                <div class="notice notice-warning">
                    <p><?php _e('檢測到 SQLite 資料庫中存在向量資料需要遷移到 WordPress 資料庫。', 'wp-seo-vector-importer'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" id="wp-seo-vi-db-type-form">
                <?php wp_nonce_field('wp_seo_vi_db_type_nonce', 'wp_seo_vi_db_type_nonce'); ?>
                <input type="hidden" name="wp_seo_vi_db_type_submit" value="1">
                
                <fieldset>
                    <legend class="screen-reader-text"><span><?php _e('資料庫類型', 'wp-seo-vector-importer'); ?></span></legend>
                    <label>
                        <input type="radio" name="wp_seo_vi_db_type" value="<?php echo WP_SEO_VI_DB_TYPE_WPDB; ?>" <?php checked($db_type, WP_SEO_VI_DB_TYPE_WPDB); ?> <?php disabled(!$can_use_sqlite); ?>>
                        <?php _e('WordPress 資料庫（推薦）', 'wp-seo-vector-importer'); ?>
                    </label>
                    <p class="description"><?php _e('使用 WordPress 的資料庫儲存向量資料，可以避免外掛更新時遺失資料。', 'wp-seo-vector-importer'); ?></p>
                    
                    <br>
                    
                    <label>
                        <input type="radio" name="wp_seo_vi_db_type" value="<?php echo WP_SEO_VI_DB_TYPE_SQLITE; ?>" <?php checked($db_type, WP_SEO_VI_DB_TYPE_SQLITE); ?> <?php disabled(!$can_use_sqlite); ?>>
                        <?php _e('SQLite 資料庫（不推薦）', 'wp-seo-vector-importer'); ?>
                    </label>
                    <p class="description"><?php _e('使用 SQLite 資料庫儲存在外掛目錄中，在外掛更新時可能會遺失資料。', 'wp-seo-vector-importer'); ?></p>
                </fieldset>
                
                <?php submit_button(__('保存資料庫設定', 'wp-seo-vector-importer')); ?>
            </form>
        </div>
    </div>
    
    <?php if ($needs_migration): ?>
    <div class="wp-seo-vi-settings-section postbox">
        <h2 class="hndle"><span><?php _e('資料遷移', 'wp-seo-vector-importer'); ?></span></h2>
        <div class="inside">
            <p><?php _e('將您的向量資料從 SQLite 遷移到 WordPress 資料庫，以避免在外掛更新時遺失資料。', 'wp-seo-vector-importer'); ?></p>
            
            <div id="wp-seo-vi-migration-form">
                <?php wp_nonce_field('wp_seo_vi_migration_nonce', 'wp_seo_vi_migration_nonce'); ?>
                
                <div id="wp-seo-vi-migration-progress-container" style="display: none; margin: 15px 0;">
                    <div style="background-color: #eee; border: 1px solid #ccc; padding: 2px;">
                        <div id="wp-seo-vi-migration-progress-bar" style="background-color: #0073aa; height: 20px; width: 0%; text-align: center; color: white; line-height: 20px;">0%</div>
                    </div>
                    <p id="wp-seo-vi-migration-progress-status"></p>
                </div>
                
                <div id="wp-seo-vi-migration-result" style="display: none; margin: 15px 0; padding: 10px; background-color: #f8f8f8; border-left: 4px solid #46b450;">
                    <h3><?php _e('遷移完成', 'wp-seo-vector-importer'); ?></h3>
                    <p id="wp-seo-vi-migration-summary"></p>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php _e('資料表', 'wp-seo-vector-importer'); ?></th>
                                <th><?php _e('總記錄數', 'wp-seo-vector-importer'); ?></th>
                                <th><?php _e('成功遷移', 'wp-seo-vector-importer'); ?></th>
                                <th><?php _e('錯誤', 'wp-seo-vector-importer'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="wp-seo-vi-migration-details">
                        </tbody>
                    </table>
                </div>
                
                <div id="wp-seo-vi-migration-actions">
                    <p class="submit">
                        <button type="button" id="wp-seo-vi-start-migration" class="button button-primary">
                            <?php _e('開始資料遷移', 'wp-seo-vector-importer'); ?>
                        </button>
                        <span id="wp-seo-vi-migration-status" style="margin-left: 10px;"></span>
                    </p>
                    <p class="description"><?php _e('遷移過程可能需要一些時間，具體取決於您的資料量。請勿關閉此頁面直至遷移完成。', 'wp-seo-vector-importer'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<style>
/* 美化標題，讓其與內容對齊 */
.wp-seo-vi-settings-section h2.hndle {
    padding-left: 12px;
}

/* 遷移標籤樣式 */
.wp-seo-vi-tab-attention {
    font-weight: bold;
    position: relative;
}

.wp-seo-vi-notification-dot {
    position: absolute;
    top: 0;
    right: 0;
    background-color: #d63638;
    border-radius: 50%;
    width: 8px;
    height: 8px;
    display: inline-block;
}

/* 資料庫類型標籤 */
.wp-seo-vi-db-type-sqlite, .wp-seo-vi-db-type-wpdb {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.wp-seo-vi-db-type-sqlite {
    background-color: #ffecec;
    color: #d63638;
}

.wp-seo-vi-db-type-wpdb {
    background-color: #edfaef;
    color: #2a9d3f;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 當強制執行預算限制時，確保預算限制不為0
    document.getElementById('wp_seo_vi_enforce_budget_limit').addEventListener('change', function() {
        if (this.checked && parseFloat(document.getElementById('wp_seo_vi_budget_limit').value) <= 0) {
            alert('<?php _e('要強制執行預算限制，請先設置有效的預算上限。', 'wp-seo-vector-importer'); ?>');
            this.checked = false;
        }
    });
});
</script>
