<?php
/**
 * 重複文章比對頁面模板
 */
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- 說明區塊 -->
    <div class="wp-seo-vi-intro notice notice-info" style="background-color: #f0f6fc; border-left-color: #2271b1; padding: 12px 16px; margin: 20px 0;">
        <h3 style="margin-top: 0;"><?php _e('重複文章比對功能', 'wp-seo-vector-importer'); ?></h3>
        <p><?php _e('本功能利用向量數據庫中的文章向量，計算文章之間的相似度，幫助您找出可能重複或內容相近的文章。', 'wp-seo-vector-importer'); ?></p>
        <p><strong><?php _e('系統會自動:', 'wp-seo-vector-importer'); ?></strong></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><?php _e('計算所有文章之間的向量相似度', 'wp-seo-vector-importer'); ?></li>
            <li><?php _e('根據設定的相似度閾值（默認0.7）識別重複內容', 'wp-seo-vector-importer'); ?></li>
            <li><?php _e('將相似文章分組顯示，方便您進行合併或修改', 'wp-seo-vector-importer'); ?></li>
        </ul>
        <p><strong><?php _e('使用方式：', 'wp-seo-vector-importer'); ?></strong> <?php _e('設定相似度閾值（越高表示要求越嚴格），選擇比對模型，然後點擊「開始比對」按鈕。比對完成後，系統會顯示所有相似文章組。', 'wp-seo-vector-importer'); ?></p>
        <p><?php _e('注意：此功能需要先將文章建立向量索引後才能使用。', 'wp-seo-vector-importer'); ?></p>
    </div>

    <!-- 向量數據統計信息 -->
    <div class="wp-seo-vi-summary-card" style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 15px; margin: 15px 0;">
        <h3><?php _e('向量數據庫統計', 'wp-seo-vector-importer'); ?></h3>
        <?php 
        $vector_count = $this->db->get_vector_count();
        ?>
        <p>
            <?php 
            if ($vector_count) {
                echo sprintf(__('向量數據庫中共有 <strong>%d</strong> 篇文章的向量數據。', 'wp-seo-vector-importer'), $vector_count);
            } else {
                echo __('向量數據庫中尚無文章數據，請先至「向量索引」頁面索引文章。', 'wp-seo-vector-importer');
            }
            ?>
        </p>
    </div>

    <!-- 比對設置和控制區域 -->
    <div class="wp-seo-vi-control-panel" style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 15px; margin: 15px 0;">
        <h3><?php _e('比對設置', 'wp-seo-vector-importer'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('相似度閾值', 'wp-seo-vector-importer'); ?></th>
                <td>
                    <input type="range" id="wp-seo-vi-similarity-threshold" 
                           min="0.5" max="1.0" step="0.01" 
                           value="<?php echo esc_attr($similarity_threshold); ?>" 
                           oninput="document.getElementById('threshold-value').textContent = this.value">
                    <span id="threshold-value"><?php echo esc_html($similarity_threshold); ?></span>
                    <p class="description">
                        <?php _e('設定文章相似度的判定閾值（0.5-1.0），值越高要求越嚴格。', 'wp-seo-vector-importer'); ?><br>
                        <?php _e('推薦設置：0.7-0.8', 'wp-seo-vector-importer'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('比對模型', 'wp-seo-vector-importer'); ?></th>
                <td>
                    <select id="wp-seo-vi-comparison-model">
                        <option value="vector" <?php selected($comparison_model, 'vector'); ?>><?php _e('快速模式（節省API費用）', 'wp-seo-vector-importer'); ?></option>
                        <option value="hybrid" <?php selected($comparison_model, 'hybrid'); ?>><?php _e('智慧模式（平衡速度與準確度）', 'wp-seo-vector-importer'); ?></option>
                        <option value="gpt" <?php selected($comparison_model, 'gpt'); ?>><?php _e('精準模式（最高準確度，費用較高）', 'wp-seo-vector-importer'); ?></option>
                    </select>
                    <p class="description">
                        <strong><?php _e('快速模式', 'wp-seo-vector-importer'); ?></strong>: <?php _e('只使用向量比對，速度最快，適合大量文章初步篩選', 'wp-seo-vector-importer'); ?><br>
                        <strong><?php _e('智慧模式', 'wp-seo-vector-importer'); ?></strong>: <?php _e('系統自動優化處理方式，平衡成本與準確度（推薦）', 'wp-seo-vector-importer'); ?><br>
                        <strong><?php _e('精準模式', 'wp-seo-vector-importer'); ?></strong>: <?php _e('全部使用GPT分析，最準確但API費用較高', 'wp-seo-vector-importer'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <div class="wp-seo-vi-actions">
            <button type="button" id="wp-seo-vi-start-check" class="button button-primary" <?php echo (!$vector_count) ? 'disabled' : ''; ?>>
                <?php _e('開始比對', 'wp-seo-vector-importer'); ?>
            </button>
            <button type="button" id="wp-seo-vi-cancel-check" class="button button-secondary" style="display:none; margin-left:10px;">
                <?php _e('取消比對', 'wp-seo-vector-importer'); ?>
            </button>
            <span id="wp-seo-vi-progress-status" style="margin-left: 15px;"></span>
        </div>
        
        <!-- 進度條 -->
        <div id="wp-seo-vi-progress-container" style="display: none; margin-top: 15px;">
            <div style="width: 100%; background-color: #eee; border-radius: 5px; overflow: hidden;">
                <div id="wp-seo-vi-progress-bar" style="height: 20px; width: 0%; background-color: #0073aa; text-align: center; line-height: 20px; color: white;">0%</div>
            </div>
        </div>
    </div>
    
    <!-- 比對結果區域 -->
    <div id="wp-seo-vi-results-container" style="display: none; margin-top: 20px;">
        <h2><?php _e('比對結果', 'wp-seo-vector-importer'); ?></h2>
        <div id="wp-seo-vi-duplicate-results" class="wp-seo-vi-results-wrapper" style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 15px; margin-bottom: 20px;">
            <!-- 結果將通過JS動態填充 -->
        </div>
    </div>
    
    <!-- 歷史記錄區域 -->
    <div id="wp-seo-vi-history-container" style="margin-top: 30px;">
        <h2><?php _e('最近比對記錄', 'wp-seo-vector-importer'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('比對時間', 'wp-seo-vector-importer'); ?></th>
                    <th><?php _e('相似度閾值', 'wp-seo-vector-importer'); ?></th>
                    <th><?php _e('使用模型', 'wp-seo-vector-importer'); ?></th>
                    <th><?php _e('檢查文章數', 'wp-seo-vector-importer'); ?></th>
                    <th><?php _e('找到重複組數', 'wp-seo-vector-importer'); ?></th>
                    <th><?php _e('狀態', 'wp-seo-vector-importer'); ?></th>
                    <th><?php _e('操作', 'wp-seo-vector-importer'); ?></th>
                </tr>
            </thead>
            <tbody id="wp-seo-vi-check-history">
                <?php
                if (!empty($recent_checks)) {
                    foreach ($recent_checks as $check) {
                        ?>
                        <tr data-check-id="<?php echo esc_attr($check['check_id']); ?>">
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($check['check_date']))); ?></td>
                            <td><?php echo esc_html($check['similarity_threshold']); ?></td>
                            <td><?php echo $check['model_used'] === 'vector' ? __('向量相似度', 'wp-seo-vector-importer') : __('GPT-4o-mini', 'wp-seo-vector-importer'); ?></td>
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
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 結果模板 (用於JS動態生成內容) -->
<script type="text/template" id="wp-seo-vi-group-template">
    <div class="wp-seo-vi-duplicate-group" style="margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); background: #fff;">
        <div class="wp-seo-vi-group-header" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="wp-seo-vi-group-title" style="margin: 0; padding: 0;">
                <?php _e('重複文章組', 'wp-seo-vector-importer'); ?> #{groupNumber} <span style="color: #E44D26; font-weight: bold;">({articleCount} <?php _e('篇相似文章)', 'wp-seo-vector-importer'); ?></span>
            </h3>
            <span class="wp-seo-vi-toggle-icon dashicons dashicons-arrow-down-alt2" style="font-size: 20px;"></span>
        </div>
        <div class="wp-seo-vi-group-content" style="padding: 15px; display: none;">
            <div class="wp-seo-vi-group-explanation" style="background-color: #F3F5F6; padding: 12px 15px; margin-bottom: 15px; border-radius: 4px; border-left: 4px solid #2271b1;">
                <h4 style="margin-top: 0; margin-bottom: 8px;"><?php _e('文章相似性分析', 'wp-seo-vector-importer'); ?></h4>
                <p style="margin-bottom: 8px;"><?php _e('以下列表中的所有文章內容相似度較高：', 'wp-seo-vector-importer'); ?></p>
                <ul style="margin-top: 0; margin-left: 20px; list-style-type: disc;">
                    <li><strong style="color: #2271b1;"><?php _e('藍色標記', 'wp-seo-vector-importer'); ?></strong> <?php _e('表示參考文章（第一篇文章）', 'wp-seo-vector-importer'); ?></li>
                    <li><?php _e('每篇相似文章都會顯示與參考文章的相似度百分比', 'wp-seo-vector-importer'); ?></li>
                    <li><?php _e('點擊「查看」按鈕可以開啟原始文章進行比對', 'wp-seo-vector-importer'); ?></li>
                </ul>
            </div>
            <div class="wp-seo-vi-group-articles">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 10%;"><?php _e('文章ID', 'wp-seo-vector-importer'); ?></th>
                            <th style="width: 40%;"><?php _e('標題', 'wp-seo-vector-importer'); ?></th>
                            <th style="width: 15%;"><?php _e('與參考文章相似度', 'wp-seo-vector-importer'); ?></th>
                            <th style="width: 35%;"><?php _e('操作', 'wp-seo-vector-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="wp-seo-vi-article-list">
                        <!-- JS將在此處填充文章列表 -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</script>

<script type="text/template" id="wp-seo-vi-article-template">
    <tr class="wp-seo-vi-article {mainArticleClass}">
        <td>{postId}</td>
        <td>
            <a href="{postUrl}" target="_blank">{postTitle}</a>
            {referenceMarker}
        </td>
        <td>{similarityScore}</td>
        <td>
            <a href="{postUrl}" target="_blank" class="button button-small view-article-btn"><?php _e('查看', 'wp-seo-vector-importer'); ?></a>
            <a href="{editUrl}" target="_blank" class="button button-small"><?php _e('編輯', 'wp-seo-vector-importer'); ?></a>
        </td>
    </tr>
</script>

<!-- 添加一些自定義樣式 -->
<style>
.wp-seo-vi-status-processing {
    color: #ff9900;
    font-weight: bold;
}
.wp-seo-vi-status-completed {
    color: #46b450;
    font-weight: bold;
}
.wp-seo-vi-status-unknown {
    color: #888;
}
.wp-seo-vi-article.main-article {
    background-color: #f0f7ff;
}
.wp-seo-vi-article.main-article td {
    border-left: 3px solid #2271b1;
    font-weight: 500;
}
.wp-seo-vi-reference-marker {
    background-color: #e7f4fd;
    border-radius: 3px;
    padding: 2px 6px;
    margin-left: 8px;
    font-size: 11px;
    font-weight: bold;
    color: #2271b1;
}
.wp-seo-vi-group-description {
    font-style: italic;
    background-color: #f9f9f9;
    padding: 10px;
    border-left: 4px solid #2271b1;
}
.wp-seo-vi-group-title {
    color: #2271b1;
}
</style>
