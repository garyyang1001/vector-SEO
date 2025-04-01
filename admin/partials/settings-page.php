<?php
/**
 * 顯示OpenAI API設定頁面。
 */

// 如果直接訪問此文件，中止執行
if (!defined('WPINC')) {
    die;
}

// 獲取設置
$token_rate = get_option('wp_seo_vi_token_rate', '0.15');
$budget_limit = get_option('wp_seo_vi_budget_limit', '0');
$enforce_budget_limit = get_option('wp_seo_vi_enforce_budget_limit', 'no');
$batch_size = get_option('wp_seo_vi_batch_size', '5');

// 顯示設置錯誤/更新信息
settings_errors('wp_seo_vi_settings');
?>

<div class="wrap wp-seo-vi-settings-page">
    <h1><?php _e('OpenAI API 設定', 'wp-seo-vector-importer'); ?></h1>
    
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
</div>

<style>
/* 美化標題，讓其與內容對齊 */
.wp-seo-vi-settings-section h2.hndle {
    padding-left: 12px;
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
