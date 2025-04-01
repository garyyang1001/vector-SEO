/**
 * 重複文章比對頁面的 JavaScript 功能
 */
(function($) {
    'use strict';
    
    // 全局變量，用於取消和重新比對功能
    let isCancelled = false;
    let isProcessing = false;
    let currentCheckId = null;

    // 當 DOM 準備好後運行
    $(document).ready(function() {
        
        // 初始化頁面組件
        initializePage();
        
        // 綁定事件處理器
        bindEventHandlers();
    });

    /**
     * 初始化頁面組件
     */
    function initializePage() {
        // 初始化相似度滑塊的顯示
        const threshold = $('#wp-seo-vi-similarity-threshold').val();
        $('#threshold-value').text(threshold);
        
        // 檢查歷史記錄中是否有處理中的任務
        checkForRunningTasks();
    }
    
    /**
     * 檢查是否有正在處理中的任務
     */
    function checkForRunningTasks() {
        // 檢查所有歷史記錄中的處理中任務
        const processingTasks = $('#wp-seo-vi-check-history').find('.wp-seo-vi-status-processing');
        
        if (processingTasks.length > 0) {
            // 找到最近的處理中任務
            const row = processingTasks.first().closest('tr');
            const checkId = row.data('check-id');
            
            // 顯示進度條
            $('#wp-seo-vi-progress-container').show();
            
            // 定期檢查任務狀態
            pollTaskStatus(checkId);
        }
    }
    
    /**
     * 綁定頁面上的事件處理器
     */
    function bindEventHandlers() {
        // 開始比對按鈕點擊事件
        $('#wp-seo-vi-start-check').on('click', startDuplicateCheck);
        
        // 取消比對按鈕點擊事件
        $('#wp-seo-vi-cancel-check').on('click', cancelDuplicateCheck);
        
        // 查看結果按鈕點擊事件
        $(document).on('click', '.wp-seo-vi-view-result-btn', function() {
            const checkId = $(this).data('check-id');
            loadCheckResult(checkId);
            // 保存當前檢查ID，用於可能的二次比對
            currentCheckId = checkId;
        });
        
        // 刪除記錄按鈕點擊事件
        $(document).on('click', '.wp-seo-vi-delete-record-btn', function() {
            if (confirm('確定要刪除這筆比對記錄嗎？此操作無法撤銷。')) {
                const checkId = $(this).data('check-id');
                deleteCheckRecord(checkId);
            }
        });
        
        // 二次比對按鈕點擊事件（會動態添加到結果頁面）
        $(document).on('click', '.wp-seo-vi-recheck-btn', function() {
            const postIds = $(this).data('post-ids').toString().split(',');
            const model = $('#wp-seo-vi-comparison-model').val();
            startSecondaryCheck(postIds, model);
        });
    }
    
    /**
     * 開始重複文章比對
     */
    function startDuplicateCheck() {
        // 獲取設置
        const threshold = $('#wp-seo-vi-similarity-threshold').val();
        const model = $('#wp-seo-vi-comparison-model').val();
        
        // 重置取消狀態
        isCancelled = false;
        isProcessing = true;
        
        // 禁用按鈕，避免重複點擊
        $('#wp-seo-vi-start-check').prop('disabled', true);
        
        // 顯示取消按鈕
        $('#wp-seo-vi-cancel-check').show();
        
        // 顯示進度狀態
        $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.processing_text);
        
        // 顯示進度條並重置
        $('#wp-seo-vi-progress-container').show();
        $('#wp-seo-vi-progress-bar').css('width', '0%').text('0%');
        
        // 隱藏結果容器（如果之前有顯示）
        $('#wp-seo-vi-results-container').hide();
        
        // 發送AJAX請求開始比對
        $.ajax({
            url: wpSeoViDuplicate.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_start_duplicate_check',
                nonce: wpSeoViDuplicate.duplicate_check_nonce,
                threshold: threshold,
                model: model
            },
            success: function(response) {
                if (response.success) {
                    // 開始批次處理
                    processBatch(response.data);
                } else {
                    // 顯示錯誤
                    $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_failed_text + ': ' + response.data);
                    $('#wp-seo-vi-start-check').prop('disabled', false);
                }
            },
            error: function() {
                // AJAX錯誤
                $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_failed_text);
                $('#wp-seo-vi-start-check').prop('disabled', false);
            }
        });
    }
    
    /**
     * 處理批次比對
     */
    function processBatch(data) {
        // 顯示初始進度消息
        $('#wp-seo-vi-progress-status').text(data.message);
        
        // 準備批次處理數據
        const batchData = {
            check_id: data.check_id,
            batch_id: data.batch_id,
            post_ids: JSON.stringify(data.post_ids),
            current_index: 0,
            threshold: data.threshold,
            model: data.model,
            total_posts: data.total_posts
        };
        
        // 開始處理第一批
        processBatchStep(batchData);
    }
    
    /**
     * 處理批次的單個步驟
     */
    /**
     * 取消比對功能
     */
    function cancelDuplicateCheck() {
        if (confirm(wpSeoViDuplicate.cancel_confirm_text)) {
            // 設置取消標記
            isCancelled = true;
            isProcessing = false;
            
            // 更新界面狀態
            $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.cancelling_text);
            $('#wp-seo-vi-cancel-check').prop('disabled', true);
            
            // 發送AJAX請求取消任務
            $.ajax({
                url: wpSeoViDuplicate.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_seo_vi_cancel_check',
                    nonce: wpSeoViDuplicate.duplicate_check_nonce
                },
                success: function(response) {
                    // 更新進度條
                    $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.cancelled_text);
                    $('#wp-seo-vi-start-check').prop('disabled', false);
                    $('#wp-seo-vi-cancel-check').hide();
                    
                    // 刷新歷史記錄
                    refreshCheckHistory();
                }
            });
        }
    }
    
    /**
     * 開始二次比對
     * @param {Array} postIds 要比對的文章ID列表
     * @param {String} model 比對模型
     */
    function startSecondaryCheck(postIds, model) {
        if (!postIds || postIds.length === 0) {
            alert(wpSeoViDuplicate.no_posts_selected_text);
            return;
        }
        
        // 獲取設置
        const threshold = $('#wp-seo-vi-similarity-threshold').val();
        
        // 重置取消狀態
        isCancelled = false;
        isProcessing = true;
        
        // 禁用按鈕，避免重複點擊
        $('#wp-seo-vi-start-check').prop('disabled', true);
        
        // 顯示取消按鈕
        $('#wp-seo-vi-cancel-check').show();
        
        // 顯示進度狀態
        $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.processing_text);
        
        // 顯示進度條並重置
        $('#wp-seo-vi-progress-container').show();
        $('#wp-seo-vi-progress-bar').css('width', '0%').text('0%');
        
        // 發送AJAX請求開始二次比對
        $.ajax({
            url: wpSeoViDuplicate.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_start_secondary_check',
                nonce: wpSeoViDuplicate.duplicate_check_nonce,
                post_ids: JSON.stringify(postIds),
                threshold: threshold,
                model: model
            },
            success: function(response) {
                if (response.success) {
                    // 開始批次處理
                    processBatch(response.data);
                } else {
                    // 顯示錯誤
                    $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_failed_text + ': ' + response.data);
                    $('#wp-seo-vi-start-check').prop('disabled', false);
                    $('#wp-seo-vi-cancel-check').hide();
                }
            },
            error: function() {
                // AJAX錯誤
                $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_failed_text);
                $('#wp-seo-vi-start-check').prop('disabled', false);
                $('#wp-seo-vi-cancel-check').hide();
            }
        });
    }
    
    function processBatchStep(batchData) {
        // 檢查是否已取消
        if (isCancelled) {
            // 重置界面狀態
            $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.cancelled_text);
            $('#wp-seo-vi-start-check').prop('disabled', false);
            $('#wp-seo-vi-cancel-check').hide();
            return;
        }
        
        $.ajax({
            url: wpSeoViDuplicate.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_process_duplicate_check_batch',
                nonce: wpSeoViDuplicate.duplicate_check_nonce,
                check_id: batchData.check_id,
                batch_id: batchData.batch_id,
                post_ids: batchData.post_ids,
                current_index: batchData.current_index,
                threshold: batchData.threshold,
                model: batchData.model,
                cancel_check: isCancelled ? 1 : 0  // 發送取消標記
            },
            success: function(response) {
                if (response.success) {
                    // 更新進度條
                    const progressPercent = response.data.progress + '%';
                    $('#wp-seo-vi-progress-bar').css('width', progressPercent).text(progressPercent);
                    
                    // 更新狀態消息
                    $('#wp-seo-vi-progress-status').text(response.data.message);
                    
                    if (response.data.done) {
                        // 比對完成
                        processingComplete(batchData.check_id);
                        $('#wp-seo-vi-cancel-check').hide();
                    } else {
                        // 繼續處理下一批
                        batchData.current_index = response.data.current_index;
                        processBatchStep(batchData);
                    }
                } else {
                    // 處理錯誤
                    $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_failed_text + ': ' + response.data);
                    $('#wp-seo-vi-start-check').prop('disabled', false);
                    $('#wp-seo-vi-cancel-check').hide();
                }
            },
            error: function() {
                // AJAX錯誤
                $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_failed_text);
                $('#wp-seo-vi-start-check').prop('disabled', false);
                $('#wp-seo-vi-cancel-check').hide();
            }
        });
    }
    
    /**
     * 處理完成後的操作
     */
    function processingComplete(checkId) {
        // 更新狀態
        $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_complete_text);
        
        // 重新啟用開始按鈕
        $('#wp-seo-vi-start-check').prop('disabled', false);
        
        // 加載檢測結果
        loadCheckResult(checkId);
        
        // 刷新歷史記錄
        refreshCheckHistory();
    }
    
    /**
     * 加載檢測結果
     */
    function loadCheckResult(checkId) {
        // 顯示載入中狀態
        $('#wp-seo-vi-results-container').show();
        $('#wp-seo-vi-duplicate-results').html('<p>' + wpSeoViDuplicate.loading_text + '</p>');
        
        $.ajax({
            url: wpSeoViDuplicate.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_get_check_result',
                nonce: wpSeoViDuplicate.duplicate_check_nonce,
                check_id: checkId
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    $('#wp-seo-vi-duplicate-results').html('<p>' + wpSeoViDuplicate.load_failed_text + ': ' + response.data + '</p>');
                }
            },
            error: function() {
                $('#wp-seo-vi-duplicate-results').html('<p>' + wpSeoViDuplicate.load_failed_text + '</p>');
            }
        });
    }
    
    /**
     * 顯示重複文章比對結果
     */
    function displayResults(data) {
        const groups = data.groups;
        const resultsContainer = $('#wp-seo-vi-duplicate-results');
        const checkInfo = data.check_info || {};
        const model = checkInfo.model_used || '';
        const checkId = checkInfo.check_id || 0;
        
        // 清空結果容器
        resultsContainer.empty();
        
        // 添加檢查信息頭部
        const headerHtml = `
            <div class="wp-seo-vi-result-header" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">${wpSeoViDuplicate.check_result_title}</h3>
                <p><strong>${wpSeoViDuplicate.check_info_text}:</strong></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><strong>${wpSeoViDuplicate.time_text}:</strong> ${checkInfo.check_date || '–'}</li>
                    <li><strong>${wpSeoViDuplicate.threshold_text}:</strong> ${checkInfo.similarity_threshold || '0.7'}</li>
                    <li><strong>${wpSeoViDuplicate.model_text}:</strong> 
                        ${model === 'vector' ? wpSeoViDuplicate.vector_mode_text : 
                          model === 'hybrid' ? wpSeoViDuplicate.hybrid_mode_text :
                          model === 'gpt' ? wpSeoViDuplicate.gpt_mode_text : '–'}
                    </li>
                    <li><strong>${wpSeoViDuplicate.total_articles_text}:</strong> ${checkInfo.total_articles_checked || '0'}</li>
                    <li><strong>${wpSeoViDuplicate.duplicate_groups_text}:</strong> ${(groups && groups.length) || '0'}</li>
                </ul>
                <div class="wp-seo-vi-actions" style="margin-top: 15px;">
                    <button type="button" class="button wp-seo-vi-delete-record-btn" data-check-id="${checkId}" style="color: #a00;">
                        ${wpSeoViDuplicate.delete_record_text || '刪除比對記錄'}
                    </button>
                </div>
            </div>
        `;
        resultsContainer.append(headerHtml);
        
        if (!groups || groups.length === 0) {
            // 沒有找到重複文章
            resultsContainer.append('<p>' + wpSeoViDuplicate.no_duplicates_text + '</p>');
            return;
        }
        
        // 獲取模板
        const groupTemplate = $('#wp-seo-vi-group-template').html();
        const articleTemplate = $('#wp-seo-vi-article-template').html();
        
        // 遍歷每個重複文章組
        groups.forEach(function(group, groupIndex) {
            // 創建文章組容器
            const groupHtml = groupTemplate
                .replace('{groupNumber}', groupIndex + 1)
                .replace('{articleCount}', group.length);
                
            const groupElement = $(groupHtml);
            const articleList = groupElement.find('.wp-seo-vi-article-list');
            
            // 遍歷組內文章
            group.forEach(function(article, articleIndex) {
                const isMainArticle = articleIndex === 0;
                const referenceMarker = isMainArticle ? 
                    '<span class="wp-seo-vi-reference-marker">' + wpSeoViDuplicate.reference_article_text + '</span>' : '';
                
                const similarityText = article.similarity_score !== undefined && article.similarity_score !== null ? 
                    parseFloat(article.similarity_score * 100).toFixed(1) + '%' : '100%';
                
                // 創建文章編輯連結
                const editUrl = '/wp-admin/post.php?post=' + article.post_id + '&action=edit';
                
                // 確保文章URL正確
                let postUrl = article.post_url || '#';
                
                // 如果URL不是以http開頭，可能是相對路徑，嘗試修復URL
                if (postUrl !== '#' && !postUrl.startsWith('http')) {
                    // 嘗試使用站點URL作為基礎
                    postUrl = window.location.origin + postUrl;
                }
                
                // 創建文章列表項
                const articleHtml = articleTemplate
                    .replace(/{postId}/g, article.post_id)
                    .replace(/{postTitle}/g, article.post_title || 'Untitled')
                    .replace(/{postUrl}/g, postUrl)
                    .replace(/{editUrl}/g, editUrl)
                    .replace(/{similarityScore}/g, similarityText)
                    .replace(/{referenceMarker}/g, referenceMarker)
                    .replace(/{mainArticleClass}/g, isMainArticle ? 'main-article' : '');
                
                articleList.append(articleHtml);
            });
            
            // 綁定折疊/展開事件
            groupElement.find('.wp-seo-vi-group-header').on('click', function() {
                const content = $(this).next('.wp-seo-vi-group-content');
                const icon = $(this).find('.wp-seo-vi-toggle-icon');
                
                if (content.is(':visible')) {
                    content.slideUp(200);
                    icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    content.slideDown(200);
                    icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    
                    // 如果是第一個被打開的組，顯示其內容
                    if (groupIndex === 0 && !content.data('opened')) {
                        content.data('opened', true);
                    }
                }
            });
            
            // 將文章組添加到結果容器
            resultsContainer.append(groupElement);
            
            // 默認展開第一個組
            if (groupIndex === 0) {
                const header = groupElement.find('.wp-seo-vi-group-header');
                const content = groupElement.find('.wp-seo-vi-group-content');
                const icon = header.find('.wp-seo-vi-toggle-icon');
                
                content.show();
                icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                content.data('opened', true);
            }
        });
    }
    
    /**
     * 定期檢查任務狀態
     */
    function pollTaskStatus(checkId) {
        // 每5秒檢查一次任務狀態
        setTimeout(function() {
            $.ajax({
                url: wpSeoViDuplicate.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_seo_vi_get_check_result',
                    nonce: wpSeoViDuplicate.duplicate_check_nonce,
                    check_id: checkId
                },
                success: function(response) {
                    if (response.success) {
                        const checkInfo = response.data.check_info;
                        
                        if (checkInfo && checkInfo.status === 'completed') {
                            // 任務已完成，更新介面
                            $('#wp-seo-vi-progress-bar').css('width', '100%').text('100%');
                            $('#wp-seo-vi-progress-status').text(wpSeoViDuplicate.check_complete_text);
                            
                            // 刷新歷史記錄
                            refreshCheckHistory();
                            
                            // 重新啟用開始按鈕
                            $('#wp-seo-vi-start-check').prop('disabled', false);
                        } else if (checkInfo && checkInfo.status === 'processing') {
                            // 任務仍在進行中，計算進度
                            if (checkInfo.total_articles_checked > 0) {
                                const progress = Math.min(100, Math.round((checkInfo.total_articles_checked / checkInfo.total_articles) * 100));
                                $('#wp-seo-vi-progress-bar').css('width', progress + '%').text(progress + '%');
                            }
                            
                            // 繼續輪詢
                            pollTaskStatus(checkId);
                        }
                    } else {
                        // 獲取狀態失敗，繼續輪詢
                        pollTaskStatus(checkId);
                    }
                },
                error: function() {
                    // 請求失敗，繼續輪詢
                    pollTaskStatus(checkId);
                }
            });
        }, 5000);
    }
    
    /**
     * 刪除比對記錄
     */
    function deleteCheckRecord(checkId) {
        $.ajax({
            url: wpSeoViDuplicate.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_delete_check_record',
                nonce: wpSeoViDuplicate.duplicate_check_nonce,
                check_id: checkId
            },
            success: function(response) {
                if (response.success) {
                    // 如果刪除成功，重新載入歷史記錄
                    refreshCheckHistory();
                    
                    // 如果當前顯示的結果是被刪除的記錄，則隱藏結果
                    if (currentCheckId === checkId) {
                        $('#wp-seo-vi-results-container').hide();
                        currentCheckId = null;
                    }
                    
                    alert('比對記錄已成功刪除！');
                } else {
                    alert('刪除失敗: ' + response.data);
                }
            },
            error: function() {
                alert('刪除請求失敗，請稍後再試。');
            }
        });
    }
    
    /**
     * 刷新檢查歷史記錄
     */
    function refreshCheckHistory() {
        $.ajax({
            url: wpSeoViDuplicate.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_get_recent_checks',
                nonce: wpSeoViDuplicate.duplicate_check_nonce,
                limit: 5
            },
            success: function(response) {
                if (response.success) {
                    $('#wp-seo-vi-check-history').html(response.data.html);
                }
            }
        });
    }

})(jQuery);
