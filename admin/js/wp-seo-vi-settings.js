/**
 * JavaScript用於AI向量助手設定頁面
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
        
        // 資料庫類型保存表單提交
        $('#wp-seo-vi-db-type-form').on('submit', function(e) {
            e.preventDefault();
            saveDbType();
        });
        
        // 遷移功能
        if ($('#wp-seo-vi-start-migration').length) {
            $('#wp-seo-vi-start-migration').on('click', function() {
                if (confirm(wpSeoViSettings.messages.confirmMigration)) {
                    startMigration();
                }
            });
        }
    });

    /**
     * 保存資料庫類型設定
     */
    function saveDbType() {
        var dbType = $('input[name="wp_seo_vi_db_type"]:checked').val();
        if (!dbType) {
            alert(wpSeoViSettings.messages.selectDbType);
            return;
        }
        
        $.ajax({
            url: wpSeoViSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_save_db_type',
                nonce: $('#wp_seo_vi_db_type_nonce').val(),
                db_type: dbType
            },
            beforeSend: function() {
                // 禁用表單按鈕
                $('#wp-seo-vi-db-type-form input, #wp-seo-vi-db-type-form button').prop('disabled', true);
                // 顯示載入中訊息
                $('#wp-seo-vi-db-type-form').append('<p id="wp-seo-vi-db-type-status">處理中...</p>');
            },
            success: function(response) {
                if (response.success) {
                    $('#wp-seo-vi-db-type-status').text(response.data).css('color', 'green');
                    
                    // 延遲後重新載入頁面，以顯示更新後的設定
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $('#wp-seo-vi-db-type-status').text(wpSeoViSettings.messages.dbTypeError + ' ' + response.data).css('color', 'red');
                    $('#wp-seo-vi-db-type-form input, #wp-seo-vi-db-type-form button').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                $('#wp-seo-vi-db-type-status').text(wpSeoViSettings.messages.dbTypeError + ' ' + error).css('color', 'red');
                $('#wp-seo-vi-db-type-form input, #wp-seo-vi-db-type-form button').prop('disabled', false);
            }
        });
    }
    
    /**
     * 開始資料遷移過程
     */
    function startMigration() {
        // 顯示進度容器
        $('#wp-seo-vi-migration-progress-container').show();
        // 隱藏操作按鈕
        $('#wp-seo-vi-migration-actions').hide();
        // 隱藏結果區域（如果先前有顯示）
        $('#wp-seo-vi-migration-result').hide();
        
        // 初始化進度條
        $('#wp-seo-vi-migration-progress-bar').css('width', '0%').text('0%');
        $('#wp-seo-vi-migration-progress-status').text(wpSeoViSettings.messages.prepareMigration);
        
        // 開始遷移過程
        migrateData();
    }
    
    /**
     * 執行資料遷移
     */
    function migrateData() {
        $.ajax({
            url: wpSeoViSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_migrate_data',
                nonce: $('#wp_seo_vi_migration_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // 更新進度條
                    var progressPercent = data.progress;
                    $('#wp-seo-vi-migration-progress-bar').css('width', progressPercent + '%').text(progressPercent + '%');
                    $('#wp-seo-vi-migration-progress-status').text(data.message);
                    
                    if (data.done) {
                        // 遷移完成
                        migrationComplete(data);
                    } else {
                        // 繼續遷移下一批
                        setTimeout(function() {
                            migrateData();
                        }, 500);
                    }
                } else {
                    // 遷移失敗
                    migrationFailed(response.data);
                }
            },
            error: function(xhr, status, error) {
                migrationFailed(error);
            }
        });
    }
    
    /**
     * 處理遷移完成
     */
    function migrationComplete(data) {
        // 更新進度條為完成狀態
        $('#wp-seo-vi-migration-progress-bar').css('width', '100%').text('100%');
        $('#wp-seo-vi-migration-progress-status').text(wpSeoViSettings.messages.migrationComplete);
        
        // 顯示結果摘要
        $('#wp-seo-vi-migration-summary').html(wpSeoViSettings.messages.migrationSummary
            .replace('%s', data.stats.total_records)
            .replace('%s', data.stats.records_migrated));
        
        // 填充詳細資訊表格
        var detailsHtml = '';
        if (data.tableStats && data.tableStats.length) {
            $.each(data.tableStats, function(i, table) {
                detailsHtml += '<tr>' +
                    '<td>' + table.name + '</td>' +
                    '<td>' + table.total + '</td>' +
                    '<td>' + table.migrated + '</td>' +
                    '<td>' + (table.errors.length ? table.errors.join('<br>') : '-') + '</td>' +
                    '</tr>';
            });
        } else {
            detailsHtml = '<tr><td colspan="4">' + wpSeoViSettings.messages.noTablesProcessed + '</td></tr>';
        }
        $('#wp-seo-vi-migration-details').html(detailsHtml);
        
        // 顯示結果區域
        $('#wp-seo-vi-migration-result').show();
        
        // 更新資料庫類型設定
        if (data.db_type_updated) {
            // 延遲後重新載入頁面，以反映最新設定
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        } else {
            // 恢復操作按鈕
            $('#wp-seo-vi-migration-actions').show();
            // 修改按鈕文字
            $('#wp-seo-vi-start-migration').text(wpSeoViSettings.messages.migrationAgain);
        }
    }
    
    /**
     * 處理遷移失敗
     */
    function migrationFailed(errorMessage) {
        $('#wp-seo-vi-migration-progress-bar').css('background-color', '#dc3232');
        $('#wp-seo-vi-migration-progress-status').html('<strong>' + wpSeoViSettings.messages.migrationFailed + ':</strong> ' + errorMessage);
        
        // 恢復操作按鈕
        $('#wp-seo-vi-migration-actions').show();
        // 修改按鈕文字
        $('#wp-seo-vi-start-migration').text(wpSeoViSettings.messages.retryMigration);
    }
    
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
