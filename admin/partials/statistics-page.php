<?php
/**
 * 顯示Token使用統計頁面。
 */

// 如果直接訪問此文件，中止執行
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap wp-seo-vi-statistics-page">
    <h1><?php _e('OpenAI API Token使用統計', 'wp-seo-vector-importer'); ?></h1>
    
    <!-- 統計摘要 -->
    <div class="wp-seo-vi-stats-summary postbox">
        <h2 class="hndle"><span><?php _e('當前月份使用摘要', 'wp-seo-vector-importer'); ?></span></h2>
        <div class="inside">
            <div class="wp-seo-vi-stats-cards">
                <div class="wp-seo-vi-stats-card">
                    <h3><?php _e('總Token數', 'wp-seo-vector-importer'); ?></h3>
                    <div class="wp-seo-vi-stats-value"><?php echo number_format($current_month_stats['total_tokens']); ?></div>
                </div>
                <div class="wp-seo-vi-stats-card">
                    <h3><?php _e('估計成本', 'wp-seo-vector-importer'); ?></h3>
                    <div class="wp-seo-vi-stats-value">$<?php echo number_format($current_month_stats['total_cost'], 4); ?></div>
                </div>
                <?php if ($budget_limit > 0): ?>
                <div class="wp-seo-vi-stats-card">
                    <h3><?php _e('預算使用率', 'wp-seo-vector-importer'); ?></h3>
                    <?php 
                    $percentage = ($current_month_stats['total_cost'] / $budget_limit) * 100;
                    $color = $percentage < 70 ? 'green' : ($percentage < 90 ? 'orange' : 'red');
                    ?>
                    <div class="wp-seo-vi-stats-value" style="color: <?php echo $color; ?>">
                        <?php echo number_format($percentage, 1); ?>%
                    </div>
                    <div class="wp-seo-vi-budget-bar">
                        <div class="wp-seo-vi-budget-progress" style="width: <?php echo min(100, $percentage); ?>%; background-color: <?php echo $color; ?>;"></div>
                    </div>
                    <div class="wp-seo-vi-budget-text">
                        $<?php echo number_format($current_month_stats['total_cost'], 2); ?> / $<?php echo number_format($budget_limit, 2); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 統計圖表和過濾器 -->
    <div class="wp-seo-vi-stats-charts postbox">
        <h2 class="hndle"><span><?php _e('歷史使用趨勢', 'wp-seo-vector-importer'); ?></span></h2>
        <div class="inside">
            <div class="wp-seo-vi-period-filter">
                <form method="get">
                    <input type="hidden" name="page" value="wp-seo-vi-statistics">
                    <select name="period" id="wp-seo-vi-period-select">
                        <option value="day" <?php selected($period, 'day'); ?>><?php _e('按日', 'wp-seo-vector-importer'); ?></option>
                        <option value="month" <?php selected($period, 'month'); ?>><?php _e('按月', 'wp-seo-vector-importer'); ?></option>
                        <option value="year" <?php selected($period, 'year'); ?>><?php _e('按年', 'wp-seo-vector-importer'); ?></option>
                    </select>
                    <select name="limit" id="wp-seo-vi-limit-select">
                        <option value="12" <?php selected($limit, 12); ?>>12</option>
                        <option value="30" <?php selected($limit, 30); ?>>30</option>
                        <option value="60" <?php selected($limit, 60); ?>>60</option>
                        <option value="90" <?php selected($limit, 90); ?>>90</option>
                    </select>
                    <button type="submit" class="button"><?php _e('更新圖表', 'wp-seo-vector-importer'); ?></button>
                </form>
            </div>
            
            <div id="wp-seo-vi-chart-container" style="width:100%; height:400px;">
                <canvas id="wp-seo-vi-usage-chart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- 詳細數據表 -->
    <div class="wp-seo-vi-stats-details postbox">
        <h2 class="hndle"><span><?php _e('詳細使用記錄', 'wp-seo-vector-importer'); ?></span></h2>
        <div class="inside">
            <?php if (empty($statistics)): ?>
                <p><?php _e('尚無使用記錄', 'wp-seo-vector-importer'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php if ($period === 'day'): ?>
                                <th><?php _e('日期', 'wp-seo-vector-importer'); ?></th>
                            <?php elseif ($period === 'month'): ?>
                                <th><?php _e('月份', 'wp-seo-vector-importer'); ?></th>
                            <?php else: ?>
                                <th><?php _e('年份', 'wp-seo-vector-importer'); ?></th>
                            <?php endif; ?>
                            <th><?php _e('Token數', 'wp-seo-vector-importer'); ?></th>
                            <th><?php _e('成本(USD)', 'wp-seo-vector-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistics as $stat): ?>
                            <tr>
                                <?php if ($period === 'day'): ?>
                                    <td><?php echo esc_html("{$stat['year']}-{$stat['month']}-{$stat['day']}"); ?></td>
                                <?php elseif ($period === 'month'): ?>
                                    <td><?php echo esc_html("{$stat['year']}-{$stat['month']}"); ?></td>
                                <?php else: ?>
                                    <td><?php echo esc_html($stat['year']); ?></td>
                                <?php endif; ?>
                                <td><?php echo number_format($stat['total_tokens']); ?></td>
                                <td>$<?php echo number_format($stat['total_cost'], 4); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- 導出選項 -->
            <div class="wp-seo-vi-export-options" style="margin-top: 15px;">
                <button id="wp-seo-vi-export-csv" class="button" data-period="<?php echo esc_attr($period); ?>"><?php _e('導出CSV', 'wp-seo-vector-importer'); ?></button>
                <span id="wp-seo-vi-export-status"></span>
            </div>
        </div>
    </div>
</div>

<style>
/* 修正標題和內容對齊問題 */
.postbox .hndle {
    padding-left: 20px !important;
}

.wp-seo-vi-stats-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}
.wp-seo-vi-stats-card {
    flex: 1;
    min-width: 200px;
    padding: 20px 25px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}
.wp-seo-vi-stats-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 15px;
    color: #555;
    font-weight: 500;
}
.wp-seo-vi-stats-value {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
    line-height: 1.3;
}
.wp-seo-vi-budget-bar {
    margin-top: 15px;
    margin-bottom: 5px;
    height: 12px;
    background: #f0f0f0;
    border-radius: 6px;
    overflow: hidden;
}
.wp-seo-vi-budget-progress {
    height: 100%;
    border-radius: 6px;
}
.wp-seo-vi-budget-text {
    margin-top: 8px;
    font-size: 13px;
    color: #666;
}
.wp-seo-vi-period-filter {
    margin-bottom: 25px;
    padding: 5px 0;
}
.wp-seo-vi-stats-details table {
    margin-top: 15px;
}
.wp-seo-vi-stats-details th, 
.wp-seo-vi-stats-details td {
    padding: 10px 15px;
}
#wp-seo-vi-chart-container {
    padding: 10px 0 20px;
}
.wp-seo-vi-export-options {
    margin-top: 20px !important;
    padding-top: 15px;
    border-top: 1px solid #eee;
}
.postbox {
    margin-bottom: 25px;
}
.inside {
    padding: 15px 20px 20px !important;
}
</style>
