/**
 * JavaScript用於OpenAI API設置頁面
 */
(function($) {
    'use strict';

    // 當文檔準備就緒
    $(document).ready(function() {
        // 清理舊的token使用記錄按鈕事件
        $('#wp-seo-vi-cleanup-logs').on('click', function() {
            if (confirm(wpSeoViSettings.messages.confirmCleanup)) {
                cleanupOldLogs();
            }
        });
        
        // 啟用預算警報時，檢查預算限制是否設置
        $('#wp_seo_vi_enable_alerts').on('change', function() {
            if (this.checked && parseFloat($('#wp_seo_vi_budget_limit').val()) <= 0) {
                alert('要啟用預算警報，請先設置有效的預算上限。');
                this.checked = false;
            }
        });
        
        // 強制執行預算限制時，檢查預算限制是否設置
        $('#wp_seo_vi_enforce_budget_limit').on('change', function() {
            if (this.checked && parseFloat($('#wp_seo_vi_budget_limit').val()) <= 0) {
                alert('要強制執行預算限制，請先設置有效的預算上限。');
                this.checked = false;
            }
        });
    });

    /**
     * 清理舊的token使用記錄
     */
    function cleanupOldLogs() {
        $.ajax({
            url: wpSeoViSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_cleanup_logs',
                nonce: wpSeoViSettings.cleanup_nonce,
                days: 90 // 保留90天的數據
            },
            beforeSend: function() {
                $('#wp-seo-vi-cleanup-status').text('處理中...');
            },
            success: function(response) {
                if (response.success) {
                    $('#wp-seo-vi-cleanup-status').text(wpSeoViSettings.messages.cleanupSuccess);
                    
                    setTimeout(function() {
                        $('#wp-seo-vi-cleanup-status').text('');
                    }, 3000);
                } else {
                    $('#wp-seo-vi-cleanup-status').text(wpSeoViSettings.messages.cleanupError + ' ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#wp-seo-vi-cleanup-status').text(wpSeoViSettings.messages.cleanupError + ' ' + error);
            }
        });
    }

})(jQuery);
