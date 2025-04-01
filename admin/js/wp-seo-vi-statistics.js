/**
 * JavaScript用於Token使用統計頁面
 */
(function($) {
    'use strict';

    // 當文檔準備就緒
    $(document).ready(function() {
        // 初始化統計圖表
        initUsageChart();
        
        // 導出CSV按鈕事件
        $('#wp-seo-vi-export-csv').on('click', function() {
            exportReportCSV($(this).data('period'));
        });
        
        // 清理舊日誌按鈕事件
        $('#wp-seo-vi-cleanup-logs').on('click', function() {
            if (confirm(wpSeoViStats.messages.confirmCleanup)) {
                cleanupOldLogs();
            }
        });
    });

    /**
     * 初始化使用量統計圖表
     */
    function initUsageChart() {
        var ctx = document.getElementById('wp-seo-vi-usage-chart').getContext('2d');
        
        // 從本地化數據中獲取圖表數據
        var labels = wpSeoViStats.chart.labels;
        var tokenData = wpSeoViStats.chart.tokenData;
        var costData = wpSeoViStats.chart.costData;
        
        // 創建圖表
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Tokens使用量',
                        data: tokenData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        borderRadius: 3,
                        yAxisID: 'y-tokens'
                    },
                    {
                        label: '成本(USD)',
                        data: costData,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        tension: 0.2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        yAxisID: 'y-cost'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                layout: {
                    padding: {
                        top: 10,
                        right: 25,
                        bottom: 10,
                        left: 10
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            padding: 15,
                            boxWidth: 15,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        padding: 10,
                        cornerRadius: 4,
                        titleFont: {
                            size: 13
                        },
                        bodyFont: {
                            size: 12
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            padding: 8,
                            font: {
                                size: 11
                            }
                        }
                    },
                    'y-tokens': {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Tokens',
                            padding: {
                                bottom: 10
                            },
                            font: {
                                size: 12,
                                weight: 'normal'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            padding: 8,
                            font: {
                                size: 11
                            }
                        }
                    },
                    'y-cost': {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '成本(USD)',
                            padding: {
                                bottom: 10
                            },
                            font: {
                                size: 12,
                                weight: 'normal'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            padding: 8,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * 導出報告為CSV
     */
    function exportReportCSV(period) {
        $.ajax({
            url: wpSeoViStats.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_export_report',
                nonce: wpSeoViStats.export_nonce,
                period: period,
                format: 'csv'
            },
            beforeSend: function() {
                $('#wp-seo-vi-export-status').text('處理中...');
            },
            success: function(response) {
                if (response.success) {
                    $('#wp-seo-vi-export-status').text(wpSeoViStats.messages.exportSuccess);
                    
                    // 創建並觸發下載
                    var blob = new Blob([response.data.data], {type: 'text/csv;charset=utf-8;'});
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', response.data.filename);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    setTimeout(function() {
                        $('#wp-seo-vi-export-status').text('');
                    }, 3000);
                } else {
                    $('#wp-seo-vi-export-status').text(wpSeoViStats.messages.exportError + ' ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#wp-seo-vi-export-status').text(wpSeoViStats.messages.exportError + ' ' + error);
            }
        });
    }

    /**
     * 清理舊的使用記錄
     */
    function cleanupOldLogs() {
        $.ajax({
            url: wpSeoViStats.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_seo_vi_cleanup_logs',
                nonce: wpSeoViStats.cleanup_nonce,
                days: 90 // 保留90天的數據
            },
            beforeSend: function() {
                $('#wp-seo-vi-cleanup-status').text('處理中...');
            },
            success: function(response) {
                if (response.success) {
                    $('#wp-seo-vi-cleanup-status').text(wpSeoViStats.messages.cleanupSuccess);
                    
                    setTimeout(function() {
                        $('#wp-seo-vi-cleanup-status').text('');
                    }, 3000);
                } else {
                    $('#wp-seo-vi-cleanup-status').text(wpSeoViStats.messages.cleanupError + ' ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#wp-seo-vi-cleanup-status').text(wpSeoViStats.messages.cleanupError + ' ' + error);
            }
        });
    }

})(jQuery);
