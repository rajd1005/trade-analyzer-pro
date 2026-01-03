(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log("TAA v32.2: Analyzer UI (Click Fix & Mandatory Image)");
        
        // ==========================================
        //  HELPER: Row Reordering
        // ==========================================
        function applyRowOrder() {
            var dir = $('#current-dir').val(); 
            var $parent = $('#taa-table-body');
            var $rowEntry  = $('#tr-entry');
            var $rowTarget = $('#tr-target');
            var $rowSl     = $('#tr-sl');

            if (dir === 'BUY') {
                // Order: Target (Top), Entry (Mid), SL (Bot)
                $parent.prepend($rowSl);
                $parent.prepend($rowEntry);
                $parent.prepend($rowTarget);
            } else {
                // Order: SL (Top), Entry (Mid), Target (Bot)
                $parent.prepend($rowTarget);
                $parent.prepend($rowEntry);
                $parent.prepend($rowSl);
            }
        }

        // ==========================================
        //  UI & RULES
        // ==========================================
        function updateRules() {
            var val = $('#taa-instrument-select').val();
            if (!val) {
                $('#info-lot-display').hide();
                $('#taa-instr-box').hide();
                return;
            }
            
            var parts = val.split('|');
            var lotSize = parts[1] || '0';
            var mode = parts[2] || 'BOTH';

            // Show Lot Size
            $('#txt-lot-val').text(lotSize);
            $('#info-lot-display').show();

            $('#rule-mode').val(mode);
            $('#rule-strike-req').val(parts[3] || 'NO');

            // Set Radio Buttons
            $('#rad-buy').prop('disabled', false);
            $('#rad-sell').prop('disabled', false);
            $('#btn-swap').prop('disabled', false).css('opacity', '1');

            if (mode === 'BUY') {
                $('#rad-buy').prop('checked', true); 
                $('#rad-sell').prop('disabled', true).prop('checked', false);
                $('#btn-swap').prop('disabled', true).css('opacity', '0.5'); 
                $('#current-dir').val('BUY');
            } else if (mode === 'SELL') {
                $('#rad-sell').prop('checked', true); 
                $('#rad-buy').prop('disabled', true).prop('checked', false);
                $('#btn-swap').prop('disabled', true).css('opacity', '0.5'); 
                $('#current-dir').val('SELL');
            }
            
            applyRowOrder();

            if (parts[0].toUpperCase().includes('FUT')) {
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

            // DYNAMIC INSTRUCTIONS
            var instrText = "";
            var globalMode = $('#taag-global-mode').val();
            
            if (globalMode === 'ai' || (globalMode === 'both' && mode !== 'MANUAL')) {
                 instrText = "✨ <strong>AI Mode:</strong> Upload or Paste (Ctrl+V) a Chart Image to automatically extract levels.";
            } 
            if (globalMode === 'manual') {
                 instrText = "✍ <strong>Manual Mode:</strong> Upload Chart Image here (Required), then click 'Enter Manually'.";
            }
            if (globalMode === 'both') {
                 instrText = "<strong>AI or Manual:</strong> Upload/Paste Chart (Mandatory) to Auto-Analyze or Enter Manually.";
            }

            $('#taa-instr-text').html(instrText);
            $('#taa-instr-box').show();
        }

        $('#taa-instrument-select').on('change', updateRules);

        // ==========================================
        //  DROP ZONE / UPLOAD / PASTE
        // ==========================================
        
        // Click on Box -> Trigger File Input
        // FIX: Check closest to ensure we don't trigger if remove button was clicked
        $('#taa-drop-zone').on('click', function(e) {
            if ($(e.target).closest('#taa-remove-img').length > 0) {
                return; // Do nothing if remove button clicked
            }
            $('#taa-file-input').trigger('click');
        });

        // File Input Change
        $('#taa-file-input').on('change', function() {
            if (this.files && this.files[0]) {
                showPreview(this.files[0]);
            }
        });

        // Remove Image
        $('#taa-remove-img').on('click', function(e) {
            e.stopPropagation(); // Stop bubbling
            clearPreview();
        });

        // Paste Event (Global when Upload Section is visible)
        document.addEventListener('paste', function(e) {
            if ($('#taa-upload-section').is(':visible') && e.clipboardData) {
                var items = e.clipboardData.items;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        e.preventDefault(); 
                        var file = items[i].getAsFile();
                        
                        // Update File Input
                        var dt = new DataTransfer();
                        dt.items.add(file);
                        $('#taa-file-input')[0].files = dt.files;
                        
                        showPreview(file);
                        
                        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                        Toast.fire({ icon: 'success', title: 'Image Pasted' });
                        break;
                    }
                }
            }
        });

        function showPreview(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#taa-img-preview').attr('src', e.target.result);
                $('#taa-drop-content').hide();
                $('#taa-preview-container').show();
                $('#taa-drop-zone').css('border-style', 'solid');
            }
            reader.readAsDataURL(file);
        }

        function clearPreview() {
            $('#taa-file-input').val('');
            $('#taa-img-preview').attr('src', '');
            $('#taa-preview-container').hide();
            $('#taa-drop-content').show();
            $('#taa-drop-zone').css('border-style', 'dashed');
        }

        // ==========================================
        //  BUTTON ACTIONS
        // ==========================================

        // AI Analyze
        $('#taa-btn-analyze').on('click', function(e) { 
            e.preventDefault(); 
            var file = $('#taa-file-input')[0].files[0];
            
            // Mandatory Check
            if (!file) {
                Swal.fire('Required', 'Please Upload or Paste a Chart Image first.', 'warning');
                return;
            }
            processFile(file);
        });

        // Manual Mode
        $('#taa-btn-manual').on('click', function(e) { 
            e.preventDefault(); 
            startManualMode(); 
        });

        $('#btn-swap').on('click', function(e) {
            e.preventDefault();
            var mode = $('#rule-mode').val();
            if (mode !== 'BOTH') {
                Swal.fire('Restricted', 'This instrument is configured for ' + mode + ' only.', 'warning');
                return;
            }
            var cur = $('#current-dir').val();
            $('#current-dir').val(cur === 'SELL' ? 'BUY' : 'SELL');
            
            applyRowOrder();
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

        $('#taa-pre-strike, #taa-strike-select').on('input change', function() { updateUI(); });
        
        // ==========================================
        //  CORE LOGIC
        // ==========================================

        function validateSetup() {
            if (!$('#taa-instrument-select').val()) { Swal.fire('Info', "Select Instrument.", 'info'); return false; }
            if (!$('input[name="trade_dir"]:checked').val()) { Swal.fire('Info', "Please Select BUY or SELL.", 'info'); return false; }
            return true;
        }

        function startManualMode() {
            if (!validateSetup()) return;

            // CHECK: Mandatory Image in 1st Form
            var step1File = $('#taa-file-input')[0].files[0];
            if (!step1File) {
                Swal.fire('Required', 'Please Upload or Paste a Chart Image before proceeding.', 'warning');
                return;
            }

            var dir = $('input[name="trade_dir"]:checked').val().toUpperCase();
            $('#current-dir').val(dir);
            
            renderTrade({ chart_name: '', top: 0, mid: 0, bot: 0, direction: dir });
            
            // Switch Views
            $('#taa-upload-section').hide(); 
            $('#taa-result-section').show(); 
            $('#image-url').val(''); // Empty because not uploaded to cloud yet
            
            $('#span-entry, #span-target, #span-sl').hide(); 
            $('#val-entry, #val-target, #val-sl').show();
        }

        function processFile(file) {
            if (!validateSetup()) return;
            var dir = $('input[name="trade_dir"]:checked').val();
            var formData = new FormData();
            formData.append('action', 'taa_analyze_image');
            formData.append('image', file);
            formData.append('direction', dir);

            $('#taa-upload-section').hide(); 
            $('#taa-loader').show(); 
            
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
            var finalDir = (manDir === 'sell') ? 'SELL' : 'BUY';
            if ($('#rule-mode').val() === 'BUY') finalDir = 'BUY';
            if ($('#rule-mode').val() === 'SELL') finalDir = 'SELL';
            $('#current-dir').val(finalDir);

            applyRowOrder();
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

        function validatePrices() {
            var dir = $('#current-dir').val(); 
            var entry = parseFloat($('#val-entry').val()) || 0;
            var sl = parseFloat($('#val-sl').val()) || 0;
            var target = parseFloat($('#val-target').val()) || 0;

            if (entry <= 0 || sl <= 0 || target <= 0) {
                Swal.fire('Invalid Prices', 'Entry, Stop Loss, and Target must be greater than 0.', 'error');
                return false;
            }

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

            var riskPoints = Math.abs(entry - sl);
            var profitPoints = Math.abs(target - entry);
            var lots = parseInt($('#calc-lot-size').val()) || 1;
            var totalLots = parseInt($('#display-total-lots').text()) || 1;
            var totalQty = lots * totalLots;
            
            var profitAmt = profitPoints * totalQty;
            var rrRatio = (riskPoints > 0) ? (profitPoints / riskPoints) : 0;

            var minProfitReq = parseFloat($('#val-rule-min-profit').val()) || 0;
            var minRrReq = parseFloat($('#val-rule-min-rr').val()) || 1; 

            if (profitAmt < minProfitReq) {
                Swal.fire('Profit Too Low', 'Minimum Profit required is ₹' + minProfitReq.toLocaleString(), 'error');
                return false;
            }
            if (rrRatio < minRrReq) {
                Swal.fire('Low Risk:Reward', 'RR Ratio is 1:' + rrRatio.toFixed(1) + '. Minimum required is 1:' + minRrReq, 'error');
                return false;
            }

            return true;
        }

        function saveTradeToStaging() {
            var existingUrl = $('#image-url').val();
            
            // FETCH FILE FROM 1st FORM
            var manualFile = $('#taa-file-input')[0].files[0];

            // If we have neither a cloud URL (from AI) nor a local file (from Manual), Stop.
            if (!existingUrl && !manualFile) { 
                Swal.fire('Required', "Image is mandatory.", 'warning'); 
                return; 
            }

            var strikeVal = getStrikeValue();
            var isStrikeReq = $('#rule-strike-req').val();
            if (isStrikeReq === 'YES' && (!strikeVal || strikeVal.trim() === '')) {
                Swal.fire('Required', "Strike Price is Mandatory for this Instrument.", 'warning');
                return;
            }

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
                    
                    // If no cloud URL, we must send the file to be uploaded by backend
                    if (!existingUrl && manualFile) {
                        formData.append('manual_image', manualFile);
                    }

                    $('#save-status').html('<span style="color:blue">Uploading...</span>');

                    $.ajax({
                        url: taa_vars.ajaxurl, type: 'POST', data: formData, contentType: false, processData: false,
                        success: function(res) {
                            if (res.success) {
                                $('#save-status').html('<span style="color:green;">✔ Sent!</span>');
                                Toast.fire({ icon: 'success', title: 'Submitted' });
                                setTimeout(resetApp, 2000);
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
            clearPreview();
            $('#image-url').val('');
            $('#taa-manual-file').val(''); // Clear deprecated input just in case
            $('#save-status').html('');
            $('#taa-pre-strike').val('');
            
            updateRules();
            
            var mode = $('#taag-global-mode').val() || 'both';
            $('#taa-btn-analyze').toggle(mode !== 'manual');
            $('#taa-btn-manual').toggle(mode !== 'ai');
        }
    });
})(jQuery);