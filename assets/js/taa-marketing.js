(function($) {
    'use strict';

    $(document).ready(function() {
        console.log("TAA v34.0: Marketing (Telegram Button Added)");

        if (typeof taa_mkt_vars === 'undefined') {
            console.error("TAA ERROR: taa_mkt_vars missing.");
            return;
        }

        // --- TABLE TELEGRAM BUTTON HANDLER ---
        $(document).on('click', '.taa-tbl-telegram-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            
            $btn.prop('disabled', true).text('...');
            
            $.post(taa_vars.ajaxurl, {
                action: 'taa_send_published_telegram',
                security: taa_vars.nonce,
                id: id
            }, function(res) {
                $btn.prop('disabled', false).text('✈');
                if(res.success) {
                    if(typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Sent!', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                    else alert('Sent!');
                } else {
                    alert('Error: ' + res.data);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('✈');
                alert('Network Error');
            });
        });

        // =====================================
        // EXISTING MARKETING MODAL CODE BELOW
        // =====================================

        var activeTradeData = null;
        var mainChartImg = null; 
        var draggableItems = [];
        var activeDragIndex = -1;
        var isDragging = false;
        var dragOffset = { x: 0, y: 0 };
        
        var basePath = taa_mkt_vars.img_path;
        var buySignalUrl = basePath + 'buy-si.png'; 
        var sellSignalUrl = basePath + 'sell-si.png';
        var tagUrl = basePath + 'tag.png';
        var contactTagUrl = basePath + 'tag-contact.png';

        // --- MODAL HTML ---
        var modalHtml = `
            <style>
                @media screen and (max-width: 768px) {
                    .taa-mkt-modal {
                        width: 95% !important;
                        max-width: 100% !important;
                        margin: 10px auto !important;
                        top: 5% !important;
                        left: 0 !important; 
                        right: 0 !important;
                        transform: none !important;
                        max-height: 90vh;
                        overflow-y: auto;
                    }
                    .taa-mkt-canvas-wrap {
                        width: 100%;
                        overflow: hidden;
                        text-align: center;
                    }
                    #taa-mkt-canvas {
                        width: 100% !important;
                        height: auto !important;
                    }
                    .taa-mkt-actions {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 10px;
                        justify-content: center;
                    }
                    .taa-mkt-btn {
                        flex: 1 1 45%; 
                        margin: 0 !important;
                        font-size: 14px;
                        padding: 10px;
                    }
                    .taa-mkt-title {
                        font-size: 16px;
                        margin-bottom: 10px;
                    }
                }
            </style>
            <div id="taa-mkt-overlay" class="taa-mkt-overlay">
                <div class="taa-mkt-modal">
                    <div class="taa-mkt-canvas-wrap">
                        <canvas id="taa-mkt-canvas"></canvas>
                    </div>
                    <div class="taa-mkt-toolbar">
                        <div class="taa-mkt-title">Marketing Preview (Drag elements to position)</div>
                        <div class="taa-mkt-actions">
                            <button type="button" id="taa-mkt-publish" class="taa-mkt-btn" style="background-color:#28a745; color:#fff; margin-right:10px;">
                                ☁ Publish
                            </button>
                            <button type="button" id="taa-mkt-telegram" class="taa-mkt-btn taa-btn-telegram" style="background-color:#0088cc; color:#fff; margin-right:10px;">
                                ✈ Telegram
                            </button>
                            <button type="button" id="taa-mkt-dl" class="taa-mkt-btn taa-btn-download">⬇ Download</button>
                            <button type="button" id="taa-mkt-close" class="taa-mkt-btn taa-btn-close">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        if ($('#taa-mkt-overlay').length === 0) $('body').append(modalHtml);

        // --- OPEN HANDLER ---
        $(document).on('click', '.taa-btn-marketing', function(e) {
            e.preventDefault();
            
            var rawData = $(this).attr('data-trade');
            var data = null;
            try { data = JSON.parse(rawData); } catch(err) { console.error(err); }
            
            if (!data) { alert('Trade Data Missing. Please refresh the page.'); return; }

            // 1. Setup Data
            activeTradeData = data;
            draggableItems = []; 
            activeDragIndex = -1;
            
            // 2. Open Modal
            var $overlay = $('#taa-mkt-overlay');
            $overlay.addClass('taa-active').hide().fadeIn(300);

            // 3. Initialize Items & Positions
            var isBuy = (activeTradeData.dir.toUpperCase() === 'BUY');
            
            // A. Signal Image Position
            if (isBuy) {
                addDraggableImage(buySignalUrl, 563, 580, 165, true); 
            } else {
                addDraggableImage(sellSignalUrl, 563, 160, 165, true); 
            }

            // B. Tag Images
            addDraggableImage(tagUrl, 40, 140, 300, false);
            addDraggableImage(contactTagUrl, 30, 615, 150, false);
            
            // C. Text Positions
            var xPos = 880; 
            var yTop = 320;
            var yMid = 440;
            var yBot = 560;

            if (isBuy) {
                addDraggableText("TARGET", activeTradeData.target, "#FFFFFF", xPos, yTop); 
                addDraggableText("ENTRY", activeTradeData.entry, "#FFFFFF", xPos, yMid);    
                addDraggableText("SL", activeTradeData.sl, "#FFFFFF", xPos, yBot);     
            } else {
                addDraggableText("SL", activeTradeData.sl, "#FFFFFF", xPos, yTop);     
                addDraggableText("ENTRY", activeTradeData.entry, "#FFFFFF", xPos, yMid);    
                addDraggableText("TARGET", activeTradeData.target, "#FFFFFF", xPos, yBot); 
            }

            // 4. Force Initial Draw
            drawCanvas();

            // 5. Load Chart Image
            mainChartImg = new Image();
            mainChartImg.crossOrigin = "Anonymous"; 
            mainChartImg.onload = function() { drawCanvas(); };
            mainChartImg.onerror = function() { console.error("Failed to load chart image:", activeTradeData.img); };
            mainChartImg.src = activeTradeData.img;

            if (mainChartImg.complete) drawCanvas();
        });

        // --- CLOSE HANDLER ---
        $('#taa-mkt-close').on('click', function() { 
            $('#taa-mkt-overlay').fadeOut(300, function() {
                $(this).removeClass('taa-active');
            }); 
        });

        // --- PUBLISH HANDLER ---
        $('#taa-mkt-publish').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();
            
            activeDragIndex = -1;
            drawCanvas();

            $btn.prop('disabled', true).html('☁ Publishing...');

            setTimeout(function() {
                var canvas = document.getElementById('taa-mkt-canvas');
                
                canvas.toBlob(function(blob) {
                    if (!blob) { alert('Error generating image.'); $btn.prop('disabled', false).html(originalText); return; }

                    var formData = new FormData();
                    formData.append('action', 'taa_publish_marketing_image');
                    formData.append('security', taa_vars.nonce);
                    formData.append('id', activeTradeData.id); // [NEW] Pass ID for DB update
                    
                    // [FIX] Use Trade Date if available, otherwise Today
                    // We check common date field names: entry_date, date, trade_date
                    var tradeDate = activeTradeData.entry_date || activeTradeData.date || activeTradeData.trade_date || taa_mkt_vars.today;
                    // Ensure we only get the YYYY-MM-DD part (in case of datetime string)
                    var dateStr = tradeDate.split(' ')[0];
                    
                    // NAME: Chart name + Strike + DIR (ALL CAPS)
                    var rawName = activeTradeData.inst + ' ' + activeTradeData.strike + ' ' + activeTradeData.dir;
                    var formattedName = rawName.toUpperCase().replace(/\s+/g, ' ').trim();
                    
                    var fileName = formattedName.replace(/\s+/g, '_') + '_' + dateStr + '.jpg';
                    
                    formData.append('file', blob, fileName); 
                    formData.append('name', formattedName); 
                    formData.append('date', dateStr); 

                    $.ajax({
                        url: taa_vars.ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if(response.success) {
                                if(typeof Swal !== 'undefined') Swal.fire('Published!', 'Image uploaded to server for ' + dateStr, 'success');
                                else alert('Published Successfully for ' + dateStr);
                                
                                $(document).trigger('taa_gallery_refresh');
                                // Optional: Refresh current dashboard to show View button immediately
                                if(typeof notifyGlobalChange === 'function') notifyGlobalChange();
                            } else {
                                var msg = response.data || 'Publish failed';
                                if(typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
                                else alert('Error: ' + msg);
                            }
                        },
                        error: function(xhr, status, error) { alert('Network Error: ' + error); },
                        complete: function() { $btn.prop('disabled', false).html(originalText); }
                    });
                }, 'image/jpeg', 0.95);
            }, 100);
        });

        // --- TELEGRAM HANDLER (In Modal) ---
        $('#taa-mkt-telegram').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();
            
            activeDragIndex = -1;
            drawCanvas();

            $btn.prop('disabled', true).html('⌛ Sending...');

            setTimeout(function() {
                var canvas = document.getElementById('taa-mkt-canvas');
                
                canvas.toBlob(function(blob) {
                    if (!blob) { alert('Error generating image.'); $btn.prop('disabled', false).html(originalText); return; }

                    var formData = new FormData();
                    formData.append('action', 'taa_send_marketing_telegram');
                    formData.append('security', taa_vars.nonce); 
                    formData.append('image', blob, 'trade_signal.jpg');
                    
                    var caption = "🔔 *" + activeTradeData.inst + "* (" + activeTradeData.dir + ")\n" +
                                  "Entry: " + activeTradeData.entry + "\n" +
                                  "Target: " + activeTradeData.target + "\n" + 
                                  "SL: " + activeTradeData.sl;
                    formData.append('caption', caption);

                    $.ajax({
                        url: taa_vars.ajaxurl, type: 'POST', data: formData, processData: false, contentType: false,
                        success: function(response) {
                            if(response.success) {
                                if(typeof Swal !== 'undefined') Swal.fire('Sent!', 'Trade setup sent to Telegram.', 'success');
                                else alert('Sent!');
                            } else {
                                if(typeof Swal !== 'undefined') Swal.fire('Error', response.data || 'Unknown error', 'error');
                                else alert('Error: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) { alert('AJAX Error: ' + error); },
                        complete: function() { $btn.prop('disabled', false).html(originalText); }
                    });
                }, 'image/jpeg', 0.95); 
            }, 100);
        });

        // --- DOWNLOAD HANDLER ---
        $('#taa-mkt-dl').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();

            activeDragIndex = -1; 
            drawCanvas(); 
            
            $btn.prop('disabled', true).html('⬇ Saving...');

            setTimeout(function() {
                var canvas = document.getElementById('taa-mkt-canvas');
                var fn = (activeTradeData.inst + "_" + activeTradeData.strike).replace(/\s+/g,'_').replace(/[^a-zA-Z0-9_]/g, '');
                
                var link = document.createElement('a');
                link.download = fn + '.jpg';
                link.href = canvas.toDataURL("image/jpeg", 1.0);
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                $btn.prop('disabled', false).html(originalText);
            }, 100);
        });

        // --- HELPERS ---
        function addDraggableImage(url, x, y, w, centered) {
            var item = { type: 'image', x: x, y: y, w: w, h: 0, img: new Image(), loaded: false };
            item.img.crossOrigin = "Anonymous";
            item.img.onload = function() {
                item.loaded = true;
                item.h = w / (item.img.width / item.img.height);
                if(centered) item.x = x - (item.w / 2);
                drawCanvas();
            };
            item.img.src = url;
            draggableItems.push(item);
        }

        function addDraggableText(label, value, color, x, y) {
            draggableItems.push({ type: 'text', label: label, value: value, color: color, x: x, y: y, w: 200, h: 30 });
        }

        function getMousePos(evt) {
            var cvs = document.getElementById('taa-mkt-canvas');
            var rect = cvs.getBoundingClientRect();
            var scaleX = 1126 / rect.width; 
            var scaleY = 844 / rect.height; 
            
            var cx = evt.clientX; var cy = evt.clientY;
            if(evt.touches && evt.touches.length > 0) { cx = evt.touches[0].clientX; cy = evt.touches[0].clientY; }
            return { x: (cx - rect.left) * scaleX, y: (cy - rect.top) * scaleY };
        }

        // --- DRAG EVENTS ---
        $(document).on('mousedown touchstart', '#taa-mkt-canvas', function(e) {
            var evt = e.originalEvent || e; var pos = getMousePos(evt);
            for (var i = draggableItems.length - 1; i >= 0; i--) {
                var it = draggableItems[i];
                if (it.type === 'image' && !it.loaded) continue;
                if (pos.x >= it.x && pos.x <= it.x + it.w && pos.y >= it.y && pos.y <= it.y + it.h) {
                    isDragging = true; activeDragIndex = i; dragOffset.x = pos.x - it.x; dragOffset.y = pos.y - it.y; 
                    drawCanvas(); return; 
                }
            }
            if(activeDragIndex !== -1) { activeDragIndex = -1; drawCanvas(); }
        });

        $(document).on('mousemove touchmove', function(e) {
            if (isDragging && activeDragIndex > -1) {
                var evt = e.originalEvent || e;
                if(e.target.id === 'taa-mkt-canvas') e.preventDefault(); 
                var pos = getMousePos(evt); var it = draggableItems[activeDragIndex];
                it.x = pos.x - dragOffset.x; it.y = pos.y - dragOffset.y;
                drawCanvas();
            }
        });

        $(document).on('mouseup touchend', function() { isDragging = false; });

        // --- DRAW FUNCTION ---
        function drawCanvas() {
            var cvs = document.getElementById('taa-mkt-canvas');
            if(!cvs || !activeTradeData) return;
            var ctx = cvs.getContext('2d');
            var W = 1126; var H = 844;
            
            if(cvs.width !== W) { cvs.width = W; cvs.height = H; }
            
            ctx.setTransform(1, 0, 0, 1, 0, 0); 
            ctx.textBaseline = "alphabetic"; ctx.textAlign = "start";

            // Background
            var isBuy = (activeTradeData.dir.toUpperCase() === 'BUY');
            var mainColor = isBuy ? '#28a745' : '#dc3545'; 
            ctx.fillStyle = '#0b0e11'; ctx.fillRect(0, 0, W, H);
            ctx.fillStyle = "#0b0e11"; ctx.fillRect(0, 0, W, 120);

            // Header
            ctx.fillStyle = mainColor; ctx.fillRect(30, 25, 200, 70);
            ctx.fillStyle = "#FFFFFF"; ctx.font = "bold 40px Arial"; ctx.textAlign = "center";
            ctx.fillText((isBuy ? "▲" : "▼") + " " + activeTradeData.dir, 130, 75);

            // NAME: All Caps Logic
            var name = (activeTradeData.inst + (activeTradeData.strike && activeTradeData.strike !== '-' ? " " + activeTradeData.strike : "")).toUpperCase();
            
            ctx.fillStyle = "#FFC107"; ctx.font = "bold 55px Arial";
            ctx.fillText(name, W/2, 80);

            // Chart
            var imgY = 130; var imgH = 550; var imgW = W - 40; var imgX = 20;
            ctx.strokeStyle = "#ffffff"; ctx.lineWidth = 1; ctx.strokeRect(imgX, imgY, imgW, imgH);
            
            if (mainChartImg && mainChartImg.complete && mainChartImg.naturalWidth !== 0) {
                try { ctx.drawImage(mainChartImg, imgX, imgY, imgW, imgH); } catch(e) { }
            } else {
                ctx.fillStyle = "#222"; ctx.fillRect(imgX, imgY, imgW, imgH);
                ctx.fillStyle = "#555"; ctx.font = "20px Arial"; ctx.textAlign = "center";
                ctx.fillText("Loading Chart...", W/2, imgY + imgH/2);
            }

            // Watermark
            ctx.save(); ctx.translate(W/2, imgY + imgH/2); ctx.rotate(-Math.PI / 6); 
            ctx.textAlign = "center"; ctx.fillStyle = "rgba(255, 255, 255, 0.1)"; 
            ctx.font = "900 130px Arial"; ctx.fillText("RDALGO.IN", 0, 0); ctx.restore();
            
            // Items
            draggableItems.forEach(function(it, idx) {
                if (it.type === 'image' && it.loaded) {
                    ctx.drawImage(it.img, it.x, it.y, it.w, it.h);
                } else if (it.type === 'text') {
                    ctx.save();
                    ctx.font = "20px Arial"; ctx.textAlign = "left"; ctx.textBaseline = "top";
                    var txt = it.label + " @ " + it.value;
                    ctx.lineWidth = 4; ctx.strokeStyle = "rgba(0,0,0,0.8)"; ctx.strokeText(txt, it.x, it.y);
                    ctx.fillStyle = it.color; ctx.fillText(txt, it.x, it.y);
                    it.w = ctx.measureText(txt).width; it.h = 24;
                    ctx.restore();
                }
                if (idx === activeDragIndex) {
                    ctx.save(); ctx.strokeStyle = "#00FFFF"; ctx.lineWidth = 2; ctx.setLineDash([6, 4]); 
                    ctx.strokeRect(it.x - 5, it.y - 5, it.w + 10, it.h + 10); ctx.restore();
                }
            });

            // Footer
            ctx.textBaseline = "alphabetic"; ctx.textAlign = "center";
            var startY = imgY + imgH + 15; 
            ctx.fillStyle = "#FFC107"; ctx.fillRect(0, startY, W, H - startY);
            
            var metrics = [ 
                {l:"Profit", v: activeTradeData.profit},
                {l:"Risk", v: activeTradeData.risk},
                {l:"Risk & Reward", v: "1:" + activeTradeData.rr.replace('1:','')}, 
                {l:"Lot Size", v: activeTradeData.lots} 
            ];
            var colW = W/4; var cy = startY + ((H-startY)/2);
            metrics.forEach(function(m, i) {
                var x = (i*colW) + colW/2;
                ctx.fillStyle="#000"; ctx.font="bold 22px Arial"; ctx.fillText(m.l, x, cy - 22);
                ctx.font="900 42px Arial"; ctx.fillText(m.v, x, cy + 28);
                if(i<3) { ctx.fillStyle="#000"; ctx.fillRect((i+1)*colW, cy-35, 2, 70); }
            });
        }
    });
})(jQuery);