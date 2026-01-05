(function($) {
    'use strict';

    // ==========================================
    //  GLOBAL CONFIG & SYNC (v67.0 - DOM Stats)
    // ==========================================
    console.log("TAA v67.0: Core Module (DOM Stats Calculation)");

    if (typeof taa_vars === 'undefined' || !taa_vars.ajaxurl) {
        console.error("TAA CRITICAL ERROR: taa_vars not defined.");
        return;
    }

    const taaChannel = new BroadcastChannel('taa_global_sync');
    let isTabActive = true;
    
    const moneySound = new Audio('https://cdn.pixabay.com/audio/2021/08/09/audio_0dcdd21cfa.mp3'); 
    
    function playSound() {
        moneySound.currentTime = 0;
        moneySound.play().catch(e => console.warn("Sound blocked:", e));
    }

    // Tab Visibility
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            isTabActive = false;
        } else {
            isTabActive = true;
            refreshAllDashboards(true);
        }
    });

    // Cross-Tab Communication
    taaChannel.onmessage = function(event) {
        if (event.data === 'refresh_dashboards') {
            refreshAllDashboards(true);
            playSound();
        }
    };

    window.notifyGlobalChange = function() {
        refreshAllDashboards(true);
        playSound();
        taaChannel.postMessage('refresh_dashboards');
    };

    window.Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });

    // ==========================================
    //  REFRESH HANDLERS
    // ==========================================

    $(document).on('click', '.taa-refresh-btn', function(e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.taa-dashboard-wrapper');
        refreshDashboard($wrapper, false);
    });

    $(document).on('change', '.taa-date-input', function() {
        var $wrapper = $(this).closest('.taa-dashboard-wrapper');
        refreshDashboard($wrapper, false);
    });

    // Initial Load Calculation
    $(document).ready(function() {
        $('.taa-dashboard-wrapper').each(function() {
            recalculateStatsFromDOM($(this));
        });
    });

    function refreshDashboard($wrapper, silent = false) {
        if (!$wrapper.length) return;
        
        var type = $wrapper.data('type');
        var dateVal = $wrapper.find('.taa-date-input').val();
        var $tbody = $wrapper.find('.taa-table-body');
        var $btn = $wrapper.find('.taa-refresh-btn');

        if (!dateVal) return;

        if (!silent) {
            $btn.addClass('spinning').html('↻ ...');
            $wrapper.css('opacity', '0.6');
        }

        var actionMap = {
            'staging': 'taa_reload_staging',
            'approved': 'taa_reload_approved',
            'history': 'taa_reload_history',
            'rejected': 'taa_reload_rejected'
        };

        if(!actionMap[type]) return;

        $.post(taa_vars.ajaxurl, {
            action: actionMap[type],
            date: dateVal
        }, function(res) {
            if (!silent) {
                $btn.removeClass('spinning').html('↻ Refresh');
                $wrapper.css('opacity', '1');
            }
            if (res.success) {
                // 1. Update Rows
                $tbody.html(res.data);

                // 2. Update Header Button (if hidden data exists)
                var $hiddenData = $tbody.find('#taa-hidden-ajax-data');
                if ($hiddenData.length > 0) {
                    var newSummary = $hiddenData.attr('data-summary');
                    var newDate = $hiddenData.attr('data-date');
                    var $reportBtn = $wrapper.find('.taa-btn-daily-report');
                    if ($reportBtn.length > 0) {
                        $reportBtn.attr('data-summary', newSummary);
                        $reportBtn.attr('data-date', newDate);
                    }
                    $hiddenData.remove(); 
                }

                // 3. Recalculate Stats from the new Table Data
                recalculateStatsFromDOM($wrapper);

            } else {
                console.warn("TAA Refresh Failed:", res);
            }
        }).fail(function(xhr) {
            if (!silent) {
                $btn.removeClass('spinning').text('↻ Retry');
                $wrapper.css('opacity', '1');
            }
            console.error("TAA AJAX Error:", xhr.statusText);
        });
    }

    function refreshAllDashboards(silent = true) {
        if ($('.taa-design-row-wrapper').length > 0) return; 

        $('.taa-dashboard-wrapper').each(function() {
            refreshDashboard($(this), silent);
        });
    }

    // ==========================================
    //  DOM STATS CALCULATION (The Fix)
    // ==========================================
    function recalculateStatsFromDOM($wrapper) {
        var type = $wrapper.data('type');
        var $rows = $wrapper.find('.taa-table-body tr');
        
        var stats = {
            total: 0,
            buy: 0,
            sell: 0,
            approved: 0,
            rejected: 0,
            pending: 0
        };

        $rows.each(function() {
            var $row = $(this);
            // Ignore "Total Profit" summary rows (usually have colspan)
            if ($row.find('td[colspan]').length > 0) return;
            if ($row.text().trim() === "No trades for today.") return;
            if ($row.text().trim() === "No approved trades found.") return;
            if ($row.text().trim() === "No history found.") return;

            stats.total++;

            // Direction Logic
            var html = $row.html().toUpperCase();
            if (html.indexOf('BUY') !== -1 && html.indexOf('TAA-BUY') !== -1) stats.buy++;
            else if (html.indexOf('SELL') !== -1 && html.indexOf('TAA-SELL') !== -1) stats.sell++;
            
            // Status Logic
            if (type === 'approved') {
                stats.approved++;
            } else if (type === 'rejected') {
                stats.rejected++;
            } else {
                // Staging or History
                if (html.indexOf('APPROVED') !== -1 && html.indexOf('TAA-BADGE') !== -1) {
                    stats.approved++;
                } else if (html.indexOf('REJECTED') !== -1 && html.indexOf('TAA-BADGE') !== -1) {
                    stats.rejected++;
                } else {
                    stats.pending++;
                }
            }
        });

        // Update UI
        var $bar = $wrapper.find('.taa-summary-bar');
        if ($bar.length > 0) {
            $bar.find('.s-total').text(stats.total);
            $bar.find('.s-buy').text(stats.buy);
            $bar.find('.s-sell').text(stats.sell);
            $bar.find('.s-approved').text(stats.approved);
            $bar.find('.s-rejected').text(stats.rejected);
            $bar.find('.s-pending').text(stats.pending);
        }
    }
    
    // Heartbeat
    setInterval(function() {
        if (isTabActive) refreshAllDashboards(true);
    }, 3000);

})(jQuery);