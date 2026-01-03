(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log("TAA v31.7: Analyzer Module (Validation Rules Configurable)");
        
        // ==========================================
        //  ANALYZER FRONTEND
        // ==========================================
        function updateRules() {
            var val = $('#taa-instrument-select').val();
            if (!val) return;
            var parts = val.split('|');
            var name = parts[0].toUpperCase();
            var mode = parts[2] || 'BOTH';

            $('#rule-mode').val(mode);
            $('#rule-strike-req').val(parts[3] || 'NO');

            // Reset Radio Buttons based on Mode
            $('#rad-buy').prop('disabled', false);
            $('#rad-sell').prop('disabled', false);
            $('#btn-swap').prop('disabled', false).css('opacity', '1');

            if (mode === 'BUY') {
                $('#rad-buy').prop('checked', true); 
                $('#rad-sell').prop('disabled', true).prop('checked', false);
                $('#btn-swap').prop('disabled', true).css('opacity', '0.5'); // Disable Swap
                $('#current-dir').val('BUY');
            } else if (mode === 'SELL') {
                $('#rad-sell').prop('checked', true); 
                $('#rad-buy').prop('disabled', true).prop('checked', false);
                $('#btn-swap').prop('disabled', true).css('opacity', '0.5'); // Disable Swap
                $('#current-dir').val('SELL');
            }

            if (name.includes('FUT')) {
                $('#taa-pre-strike').hide(); $('#taa-strike-select').show();
            } else {
                $('#taa-pre-strike').show(); $('#taa-strike-select').hide();
            }

            var $lbl = $('#lbl-strike');
            if (parts[3] === 'YES') {
                $lbl.css('color', '#d9534f').text("STRIKE PRICE (Mandatory):");
            } else {
                $lbl.css('color', '#666').text("STRIKE PRICE (Optional):");
            }
        }

        $('#taa-instrument-select').on('change', updateRules);

        // Buttons
        $('#taa-btn-analyze').on('click', function(e) { e.preventDefault(); $('#taa-file-input').trigger('click'); });
        $('#taa-btn-manual').on('click', function(e) { e.preventDefault(); startManualMode(); });
        $('#taa-file-input').on('change', function() { if (this.files[0]) processFile(this.files[0]); });

        // Paste Logic
        document.addEventListener('paste', function(e) {
            if ($('#taa-upload-section').is(':visible') && e.clipboardData) {
                var items = e.clipboardData.items;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        e.preventDefault(); 
                        var file = items[i].getAsFile();
                        var globalMode = $('#taag-global-mode').val(); 

                        if (!validateSetup()) return;

                        if (globalMode === 'manual') {
                            startManualMode();
                            var dt = new DataTransfer();
                            dt.items.add(file);
                            var manualInput = $('#taa-manual-file')[0];
                            if (manualInput) {
                                manualInput.files = dt.files;
                                Toast.fire({ icon: 'success', title: 'Image Pasted for Manual Entry' });
                            }
                        } else {
                            processFile(file);
                        }
                        break;
                    }
                }
            }
        });

        // Swap Button
        $('#btn-swap').on('click', function(e) {
            e.preventDefault();
            
            // 1. Check Instrument Rule
            var mode = $('#rule-mode').val();
            if (mode !== 'BOTH') {
                Swal.fire('Restricted', 'This instrument is configured for ' + mode + ' only.', 'warning');
                return;
            }

            // 2. Perform Swap
            var cur = $('#current-dir').val();
            $('#current-dir').val(cur === 'SELL' ? 'BUY' : 'SELL');
            updateUI();
            calculateLive();
        });

        $('.taa-btn-save').on('click', function(e) { e.preventDefault(); saveTradeToStaging(); });
        $('.taa-btn-reset').on('click', function(e) { e.preventDefault(); resetApp(); });

        $(document).on('click', '.taa-edit-icon', function() {
            var f = $(this).data('field');
            $('#span-' + f).hide();
            $('#val-' + f).show().focus();
        });

        $(document).on('blur', '.taa-price-input', function() {
            var f = $(this).attr('id').replace('val-', '');
            var v = parseFloat($(this).val() || 0);
            $('#span-' + f).text(v).show();
            $(this).hide();
            calculateLive();
        });

        $('#taa-pre-strike, #taa-strike-select').on('input change', function() {
            updateUI(); 
        });
        
        function validateSetup() {
            if (!$('#taa-instrument-select').val()) { Swal.fire('Info', "Select Instrument.", 'info'); return false; }
            if (!$('input[name="trade_dir"]:checked').val()) { Swal.fire('Info', "Please Select BUY or SELL.", 'info'); return false; }
            return true;
        }

        function startManualMode() {
            if (!validateSetup()) return;
            renderTrade({ chart_name: '', top: 0, mid: 0, bot: 0, direction: 'BUY' });
            $('#taa-upload-section').hide(); $('#taa-result-section').show(); $('#image-url').val(''); $('#manual-upload-box').show();
            $('#span-entry, #span-target, #span-sl').hide(); $('#val-entry, #val-target, #val-sl').show();
        }

        function processFile(file) {
            if (!validateSetup()) return;
            var dir = $('input[name="trade_dir"]:checked').val();
            var formData = new FormData();
            formData.append('action', 'taa_analyze_image');
            formData.append('image', file);
            formData.append('direction', dir);

            $('#taa-upload-section').hide(); $('#taa-loader').show(); $('#taa-btn-manual').hide(); $('#taa-btn-analyze').hide();

            $.ajax({
                url: taa_vars.ajaxurl, type: 'POST', data: formData, contentType: false, processData: false,
                success: function(res) {
                    $('#taa-loader').hide();
                    if (res.success) {
                        $('#image-url').val(res.data.image_url);
                        renderTrade(res.data);
                    } else {
                        Swal.fire('Failed', res.data, 'error');
                        resetApp();
                    }
                },
                error: function() {
                    $('#taa-loader').hide();
                    Swal.fire('Error', 'Server Error', 'error');
                    resetApp();
                }
            });
        }

        function renderTrade(data) {
            var parts = $('#taa-instrument-select').val().split('|');
            $('#chart-name').val(parts[0]);
            $('#display-lot-size').text(parts[1]);
            $('#calc-lot-size').val(parts[1]);

            $('#raw-bot').val(Math.ceil(data.bot || 0));
            $('#raw-mid').val(Math.ceil(data.mid || 0));
            $('#raw-top').val(Math.ceil(data.top || 0));

            var manDir = $('input[name="trade_dir"]:checked').val();
            $('#current-dir').val((manDir === 'sell') ? 'SELL' : 'BUY');
            if ($('#rule-mode').val() === 'BUY') $('#current-dir').val('BUY');
            if ($('#rule-mode').val() === 'SELL') $('#current-dir').val('SELL');

            updateUI();
            calculateLive();
            $('#taa-result-section').show();
        }

        function getStrikeValue() {
            var instVal = $('#taa-instrument-select').val();
            var parts = instVal ? instVal.split('|') : [];
            var name = (parts[0] || '').toUpperCase();
            if (name.includes('FUT')) {
                return $('#taa-strike-select').val();
            } else {
                return $('#taa-pre-strike').val();
            }
        }

        function updateUI() {
            var isSell = ($('#current-dir').val() === 'SELL');
            var name = $('#chart-name').val();
            var strike = getStrikeValue(); 
            
            var bot = parseFloat($('#raw-bot').val()), mid = parseFloat($('#raw-mid').val()), top = parseFloat($('#raw-top').val());

            var entry = mid;
            var target = isSell ? bot : top;
            var sl = isSell ? top : bot;

            $('#taa-header-bg').css('background-color', isSell ? '#c62828' : '#2e7d32');
            
            var titleText = name;
            if (strike && strike.trim() !== "") {
                titleText += " " + strike;
            }
            titleText += " - " + (isSell ? 'SHORT' : 'LONG');
            $('#taa-header-text').text(titleText);

            if(parseFloat($('#val-entry').val()) === 0) {
                 $('#span-entry').text(entry); $('#val-entry').val(entry);
                 $('#span-target').text(target); $('#val-target').val(target);
                 $('#span-sl').text(sl); $('#val-sl').val(sl);
            }
        }

        function calculateLive() {
            var entry = Math.ceil(parseFloat($('#val-entry').val()) || 0);
            var target = Math.ceil(parseFloat($('#val-target').val()) || 0);
            var sl = Math.ceil(parseFloat($('#val-sl').val()) || 0);
            
            var lots = parseInt($('#calc-lot-size').val());
            var total = parseInt($('#display-total-lots').text());

            var pts = Math.abs(target - entry);
            var riskPts = Math.abs(entry - sl);

            $('#val-point-gain').text(pts.toFixed(0));
            var qty = lots * total;
            $('#val-total-qty').text(qty.toLocaleString());

            var prof = qty * pts;
            var risk = qty * riskPts;
            
            var rrVal = (riskPts > 0) ? Math.ceil(pts / riskPts) : 0; 

            var profFmt = Math.round(prof).toLocaleString('en-IN', { 
                style: 'currency', currency: 'INR', minimumFractionDigits: 0, maximumFractionDigits: 0 
            });
            var riskFmt = Math.round(risk).toLocaleString('en-IN', { 
                style: 'currency', currency: 'INR', minimumFractionDigits: 0, maximumFractionDigits: 0 
            });

            $('#res-profit').text(profFmt);
            $('#res-risk').text(riskFmt);
            $('#res-rr').text("1 : " + rrVal);
        }

        // [UPDATED] Validation Logic with Admin Rules
        function validatePrices() {
            var dir = $('#current-dir').val(); 
            var entry = parseFloat($('#val-entry').val()) || 0;
            var sl = parseFloat($('#val-sl').val()) || 0;
            var target = parseFloat($('#val-target').val()) || 0;

            // 1. Zero Check
            if (entry <= 0 || sl <= 0 || target <= 0) {
                Swal.fire('Invalid Prices', 'Entry, Stop Loss, and Target must be greater than 0.', 'error');
                return false;
            }

            // 2. Buy Logic
            if (dir === 'BUY') {
                if (sl >= entry) {
                    Swal.fire('Logic Error (BUY)', 'Stop Loss must be LESS than Entry.', 'error');
                    return false;
                }
                if (target <= entry) {
                    Swal.fire('Logic Error (BUY)', 'Target must be GREATER than Entry.', 'error');
                    return false;
                }
            }

            // 3. Sell Logic
            if (dir === 'SELL') {
                if (sl <= entry) {
                    Swal.fire('Logic Error (SELL)', 'Stop Loss must be GREATER than Entry.', 'error');
                    return false;
                }
                if (target >= entry) {
                    Swal.fire('Logic Error (SELL)', 'Target must be LESS than Entry.', 'error');
                    return false;
                }
            }

            // [NEW] 4. Admin Validations
            
            // Calculate Risk/Profit/RR Raw values
            var riskPoints = Math.abs(entry - sl);
            var profitPoints = Math.abs(target - entry);
            
            var lots = parseInt($('#calc-lot-size').val()) || 1;
            var totalLots = parseInt($('#display-total-lots').text()) || 1;
            var totalQty = lots * totalLots;
            
            var profitAmt = profitPoints * totalQty;
            var rrRatio = (riskPoints > 0) ? (profitPoints / riskPoints) : 0;

            // Get Settings
            var minProfitReq = parseFloat($('#val-rule-min-profit').val()) || 0;
            var minRrReq = parseFloat($('#val-rule-min-rr').val()) || 1; // Default 1:1 if invalid

            // Check Profit
            if (profitAmt < minProfitReq) {
                Swal.fire('Profit Too Low', 'Minimum Profit required is ₹' + minProfitReq.toLocaleString(), 'error');
                return false;
            }

            // Check RR Ratio
            if (rrRatio < minRrReq) {
                Swal.fire('Low Risk:Reward', 'RR Ratio is 1:' + rrRatio.toFixed(1) + '. Minimum required is 1:' + minRrReq, 'error');
                return false;
            }

            return true;
        }

        function saveTradeToStaging() {
            var existingUrl = $('#image-url').val();
            var manualFile = $('#taa-manual-file').length ? $('#taa-manual-file')[0].files[0] : null;

            if (!existingUrl && !manualFile) { Swal.fire('Required', "Image is mandatory.", 'warning'); return; }

            // Strike Check
            var strikeVal = getStrikeValue();
            var isStrikeReq = $('#rule-strike-req').val();
            if (isStrikeReq === 'YES' && (!strikeVal || strikeVal.trim() === '')) {
                Swal.fire('Required', "Strike Price is Mandatory for this Instrument.", 'warning');
                return;
            }

            // Validate Logic Before Confirm
            if (!validatePrices()) return;

            Swal.fire({
                title: 'Submit?', text: "Confirm details?", icon: 'info', showCancelButton: true, confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    
                    var data = {
                        action: 'taa_save_to_staging',
                        dir: $('#current-dir').val(),
                        name: $('#chart-name').val(),
                        strike: strikeVal,
                        entry: $('#val-entry').val(),
                        target: $('#val-target').val(),
                        sl: $('#val-sl').val(),
                        points: $('#val-point-gain').text(),
                        profit: $('#res-profit').text().replace(/[^0-9.-]+/g,""),
                        risk: $('#res-risk').text().replace(/[^0-9.-]+/g,""),
                        lot_size: $('#calc-lot-size').val(),
                        total_lots: $('#display-total-lots').text(),
                        rr_ratio: $('#res-rr').text().split(':')[1].trim(),
                        image_url: existingUrl
                    };

                    var formData = new FormData();
                    for (var key in data) formData.append(key, data[key]);
                    if (manualFile) formData.append('manual_image', manualFile);

                    $('#save-status').html('<span style="color:blue">Uploading...</span>');

                    $.ajax({
                        url: taa_vars.ajaxurl, type: 'POST', data: formData, contentType: false, processData: false,
                        success: function(res) {
                            if (res.success) {
                                $('#save-status').html('<span style="color:green;">✔ Sent!</span>');
                                Toast.fire({ icon: 'success', title: 'Submitted' });
                                setTimeout(resetApp, 2000);
                                notifyGlobalChange();
                            } else {
                                $('#save-status').html('<span style="color:red">Error</span>');
                                Swal.fire('Error', res.data, 'error');
                            }
                        }
                    });
                }
            });
        }

        function resetApp() {
            $('#taa-result-section').hide();
            $('#taa-upload-section').show();
            $('#taa-file-input').val('');
            $('#image-url').val('');
            $('#manual-upload-box').hide();
            $('#taa-manual-file').val('');
            $('#save-status').html('');
            $('#taa-pre-strike').val('');
            
            var mode = $('#taag-global-mode').val() || 'both';
            $('#taa-btn-analyze').toggle(mode !== 'manual');
            $('#taa-btn-manual').toggle(mode !== 'ai');
        }
    });
})(jQuery);