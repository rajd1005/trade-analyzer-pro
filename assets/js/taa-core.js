(function($) {
    'use strict';

    // ==========================================
    //  GLOBAL CONFIG & SYNC (v61.0)
    // ==========================================
    console.log("TAA v61.0: Core Module (Date Filter Fix)");

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
            'history': 'taa_reload_history'
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

                // 2. [FIX] Update Header "Daily Report" Button
                // We look for the hidden data passed in the response
                var $hiddenData = $tbody.find('#taa-hidden-ajax-data');
                if ($hiddenData.length > 0) {
                    var newSummary = $hiddenData.attr('data-summary');
                    var newDate = $hiddenData.attr('data-date');
                    
                    var $reportBtn = $wrapper.find('.taa-btn-daily-report');
                    if ($reportBtn.length > 0) {
                        $reportBtn.attr('data-summary', newSummary);
                        $reportBtn.attr('data-date', newDate);
                        console.log("TAA: Updated Daily Report Button for " + newDate);
                    }
                    $hiddenData.remove(); // Cleanup
                }

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
    
    // Heartbeat
    setInterval(function() {
        if (isTabActive) refreshAllDashboards(true);
    }, 3000);

})(jQuery);