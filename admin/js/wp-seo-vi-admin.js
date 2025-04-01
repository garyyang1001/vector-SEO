jQuery(document).ready(function($) {

    // API Key Validation
    $('#wp-seo-vi-validate-key-btn').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $statusSpan = $('#wp-seo-vi-validation-status');
        var apiKey = $('input[name="wp_seo_vi_openai_api_key"]').val();

        $statusSpan.text(wpSeoVi.validating_text).css('color', 'orange');
        $button.prop('disabled', true);

        $.ajax({
            url: wpSeoVi.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_validate_api_key',
                nonce: wpSeoVi.validate_nonce,
                api_key: apiKey // Send the current value for immediate validation
            },
            success: function(response) {
                if (response.success) {
                    $statusSpan.text(response.data).css('color', 'green');
                } else {
                    $statusSpan.text(wpSeoVi.validation_error_text + ' ' + response.data).css('color', 'red');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $statusSpan.text(wpSeoVi.error_text + ' (' + textStatus + ')').css('color', 'red');
                console.error("API Key Validation AJAX Error:", textStatus, errorThrown);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // --- Placeholder for other actions ---

    // Single Post Update Vector
    $(document).on('click', '.wp-seo-vi-update-vector', function(e) {
        e.preventDefault();
        var $link = $(this);
        var postId = $link.data('post-id');
        var $row = $link.closest('tr');
        var $statusColumn = $row.find('.column-vector_status'); // Find the status column in this row

        // Add visual feedback
        $link.text(wpSeoVi.processing_text).css('pointer-events', 'none');
        $statusColumn.html('<span class="spinner is-active" style="float:none; vertical-align: middle;"></span>');

        $.ajax({
            url: wpSeoVi.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_update_vector',
                nonce: wpSeoVi.process_nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Update the status column with the HTML returned from the server
                    $statusColumn.html(response.data.new_status_html);
                    // Optional: Show a temporary success message or visual cue
                    $link.text(wpSeoVi.validation_success_text).css('color', 'green');
                    setTimeout(function() {
                         $link.text(wpSeoVi.update_vector_text).css('color', ''); // Reset link text
                    }, 2000);
                } else {
                    $statusColumn.html('<span style="color:red;">' + wpSeoVi.error_text + '</span>');
                    alert(wpSeoVi.error_text + ': ' + response.data); // Show error in alert
                    $link.text(wpSeoVi.update_vector_text); // Reset link text
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $statusColumn.html('<span style="color:red;">' + wpSeoVi.error_text + '</span>');
                alert(wpSeoVi.error_text + ' (' + textStatus + ')');
                console.error("Update Vector AJAX Error:", textStatus, errorThrown);
                $link.text(wpSeoVi.update_vector_text); // Reset link text
            },
            complete: function() {
                 $link.css('pointer-events', ''); // Re-enable link
                 // Ensure spinner is removed even on error if not replaced by status
                 if ($statusColumn.find('.spinner').length > 0) {
                     // Attempt to refetch status or show 'Error'
                     $statusColumn.html('<span style="color:red;">Error</span>');
                 }
            }
        });
    });

    // Single Post Delete Vector
    $(document).on('click', '.wp-seo-vi-delete-vector', function(e) {
        e.preventDefault();
        var $link = $(this);
        var postId = $link.data('post-id');
        var $row = $link.closest('tr');
        var $statusColumn = $row.find('.column-vector_status');

        if (confirm(wpSeoVi.confirm_delete)) {
            // Add visual feedback
            $link.text(wpSeoVi.deleting_text).css('pointer-events', 'none');
            $statusColumn.html('<span class="spinner is-active" style="float:none; vertical-align: middle;"></span>');

            $.ajax({
                url: wpSeoVi.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_seo_vi_delete_vector',
                    nonce: wpSeoVi.delete_nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Update the status column with the HTML returned from the server
                        $statusColumn.html(response.data.new_status_html);
                        // Optional: Show a temporary success message or visual cue
                        $link.text(wpSeoVi.deleted_text).css('color', 'grey');
                        // Maybe hide the delete link after successful deletion?
                        // $link.hide();
                        // Or just reset it after a delay
                         setTimeout(function() {
                             $link.text(wpSeoVi.delete_vector_text).css('color', '#a00');
                         }, 2000);
                    } else {
                        $statusColumn.html('<span style="color:red;">' + wpSeoVi.error_text + '</span>');
                        alert(wpSeoVi.error_text + ': ' + response.data); // Show error in alert
                        $link.text(wpSeoVi.delete_vector_text); // Reset link text
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $statusColumn.html('<span style="color:red;">' + wpSeoVi.error_text + '</span>');
                    alert(wpSeoVi.error_text + ' (' + textStatus + ')');
                    console.error("Delete Vector AJAX Error:", textStatus, errorThrown);
                    $link.text(wpSeoVi.delete_vector_text); // Reset link text
                },
                complete: function() {
                     $link.css('pointer-events', ''); // Re-enable link
                     // Ensure spinner is removed even on error if not replaced by status
                     if ($statusColumn.find('.spinner').length > 0) {
                         // Attempt to refetch status or show 'Error'
                         $statusColumn.html('<span style="color:red;">Error</span>');
                     }
                }
            });
        }
    });

    // Process All Posts - Click handler for the "Import/Update All Posts" button
    $('#wp-seo-vi-process-all-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $statusSpan = $('#wp-seo-vi-batch-action-status');
        var $progressBar = $('#wp-seo-vi-progress-bar');
        var $progressContainer = $('#wp-seo-vi-progress-bar-container');
        var $progressStatus = $('#wp-seo-vi-progress-status');
        
        // Check if OpenAI API key is set
        if (!$('input[name="wp_seo_vi_openai_api_key"]').val().trim()) {
            alert(wpSeoVi.api_key_required_text);
            return;
        }
        
        // Get all post IDs from the table
        var postIds = [];
        $('.wp-list-table tbody tr').each(function() {
            var postId = $(this).find('.wp-seo-vi-update-vector').data('post-id');
            if (postId) {
                postIds.push(postId);
            }
        });
        
        if (postIds.length === 0) {
            $statusSpan.text(wpSeoVi.no_posts_found_text).css('color', 'red');
            return;
        }
        
        var confirmMsg = wpSeoVi.batch_confirm_text.replace('%d', postIds.length);
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Disable button and show progress
        $button.prop('disabled', true);
        $statusSpan.text(wpSeoVi.start_batch_text).css('color', 'blue');
        $progressContainer.show();
        $progressBar.css('width', '0%').text('0%');
        $progressStatus.text(wpSeoVi.prepare_process_text);
        
        // Start the batch process
        processBatch(postIds, 0, postIds.length, 5); // Process 5 at a time
    });
    
    // Process a batch of posts
    function processBatch(postIds, position, total, batchSize) {
        $.ajax({
            url: wpSeoVi.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_process_batch',
                nonce: wpSeoVi.process_nonce,
                post_ids: JSON.stringify(postIds),
                position: position,
                total: total,
                batch_size: batchSize
            },
            success: function(response) {
                if (response.success) {
                    // Update progress
                    var progress = response.data.progress;
                    $('#wp-seo-vi-progress-bar').css('width', progress + '%').text(progress + '%');
                    $('#wp-seo-vi-progress-status').html(response.data.message);
                    
                    if (response.data.done) {
                        // All done
                        $('#wp-seo-vi-batch-action-status').text(response.data.message).css('color', 'green');
                        $('#wp-seo-vi-process-all-btn').prop('disabled', false);
                        
                        // Maybe refresh the table to show updated vector statuses
                        // Could implement a table refresh function here
                        
                        // Or just show a message asking to refresh
                        setTimeout(function() {
                            if (confirm(wpSeoVi.processing_complete_text)) {
                                window.location.reload();
                            }
                        }, 1000);
                    } else {
                        // Process next batch
                        processBatch(postIds, response.data.position, response.data.total, batchSize);
                    }
                } else {
                    // Error
                    $('#wp-seo-vi-progress-status').html(wpSeoVi.error_text + ': ' + response.data);
                    $('#wp-seo-vi-batch-action-status').text(wpSeoVi.error_text).css('color', 'red');
                    $('#wp-seo-vi-process-all-btn').prop('disabled', false);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#wp-seo-vi-progress-status').html(wpSeoVi.error_text + ' (' + textStatus + ')');
                $('#wp-seo-vi-batch-action-status').text(wpSeoVi.error_text).css('color', 'red');
                $('#wp-seo-vi-process-all-btn').prop('disabled', false);
                console.error("Batch Process AJAX Error:", textStatus, errorThrown);
            }
        });
    }

    // 處理批量刪除向量
    function processBatchDelete(postIds, position, total) {
        if (position >= total) {
            // 所有向量已刪除
            $('#wp-seo-vi-progress-bar').css('width', '100%').text('100%');
            $('#wp-seo-vi-progress-status').text('刪除完成！');
            $('#wp-seo-vi-batch-action-status').text('所有選定的向量已成功刪除').css('color', 'green');
            $('#doaction, #doaction2').prop('disabled', false);
            
            // 詢問是否刷新頁面
            setTimeout(function() {
                if (confirm('刪除完成！刷新頁面以查看更新的狀態？')) {
                    window.location.reload();
                }
            }, 1000);
            return;
        }
        
        // 處理當前文章
        var postId = postIds[position];
        var progress = Math.round((position / total) * 100);
        
        // 更新進度條
        $('#wp-seo-vi-progress-bar').css('width', progress + '%').text(progress + '%');
        $('#wp-seo-vi-progress-status').text('正在刪除 ' + (position + 1) + '/' + total + ' 篇文章的向量...');
        
        // 發送AJAX請求刪除向量
        $.ajax({
            url: wpSeoVi.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_delete_vector',
                nonce: wpSeoVi.delete_nonce,
                post_id: postId
            },
            success: function(response) {
                // 無論成功或失敗，繼續處理下一個
                processBatchDelete(postIds, position + 1, total);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("批量刪除向量錯誤:", textStatus, errorThrown, "文章 ID:", postId);
                // 即使有錯誤也繼續處理下一個
                processBatchDelete(postIds, position + 1, total);
            }
        });
    }

    // Bulk Actions - Need integration with WP_List_Table form submission
    // This might require hooking into the form submission or handling clicks on the "Apply" button.
    // For now, a simple event handler for the top bulk actions "Apply" button
    $('#doaction').on('click', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-selector-top').val();
        
        if (action === 'bulk_update_vector' || action === 'bulk_delete_vector') {
            // Get selected posts
            var selectedPosts = $('input[name="post_ids[]"]:checked').map(function() {
                return parseInt($(this).val());
            }).get();
            
            if (selectedPosts.length === 0) {
                alert(wpSeoVi.select_posts_text);
                return;
            }
            
        if (action === 'bulk_update_vector') {
            if (confirm(wpSeoVi.confirm_bulk_update)) {
                // 使用現有的 processBatch 函數處理批量更新
                var $statusSpan = $('#wp-seo-vi-batch-action-status');
                var $progressBar = $('#wp-seo-vi-progress-bar');
                var $progressContainer = $('#wp-seo-vi-progress-bar-container');
                var $progressStatus = $('#wp-seo-vi-progress-status');
                
                // 檢查 OpenAI API Key 是否設置
                if (!$('input[name="wp_seo_vi_openai_api_key"]').val().trim()) {
                    alert(wpSeoVi.api_key_required_text);
                    return;
                }
                
                // 禁用批量操作按鈕並顯示進度條
                $('#doaction, #doaction2').prop('disabled', true);
                $statusSpan.text(wpSeoVi.start_batch_text).css('color', 'blue');
                $progressContainer.show();
                $progressBar.css('width', '0%').text('0%');
                $progressStatus.text(wpSeoVi.prepare_process_text);
                
                // 開始批量處理
                processBatch(selectedPosts, 0, selectedPosts.length, wpSeoVi.batch_size || 5);
            }
        } else if (action === 'bulk_delete_vector') {
            if (confirm(wpSeoVi.confirm_bulk_delete)) {
                // 實現批量刪除功能
                var $statusSpan = $('#wp-seo-vi-batch-action-status');
                var $progressBar = $('#wp-seo-vi-progress-bar');
                var $progressContainer = $('#wp-seo-vi-progress-bar-container');
                var $progressStatus = $('#wp-seo-vi-progress-status');
                
                // 禁用批量操作按鈕並顯示進度條
                $('#doaction, #doaction2').prop('disabled', true);
                $statusSpan.text('開始批量刪除...').css('color', 'blue');
                $progressContainer.show();
                $progressBar.css('width', '0%').text('0%');
                $progressStatus.text('準備刪除向量...');
                
                // 實現批量刪除功能的邏輯
                processBatchDelete(selectedPosts, 0, selectedPosts.length);
            }
        }
        }
    });

    // Clear Error Logs
    $('#wp-seo-vi-clear-logs-btn').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $statusSpan = $('#wp-seo-vi-clear-log-status');
        var $logContainer = $('#wp-seo-vi-log-container');

        if (confirm(wpSeoVi.confirm_clear_logs)) {
            $statusSpan.text(wpSeoVi.processing_text).css('color', 'orange');
            $button.prop('disabled', true);

            $.ajax({
                url: wpSeoVi.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_seo_vi_clear_logs',
                    nonce: wpSeoVi.clear_log_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $statusSpan.text(response.data).css('color', 'green');
                        // Clear the log table display
                        $logContainer.html('<p>' + wpSeoVi.no_errors_text + '</p>');
                         setTimeout(function() {
                             $statusSpan.text(''); // Clear status message
                         }, 3000);
                    } else {
                        $statusSpan.text(wpSeoVi.error_text + ': ' + response.data).css('color', 'red');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $statusSpan.text(wpSeoVi.error_text + ' (' + textStatus + ')').css('color', 'red');
                    console.error("Clear Logs AJAX Error:", textStatus, errorThrown);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        }
    });

});
