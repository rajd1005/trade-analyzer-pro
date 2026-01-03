(function($) {
    'use strict';

    $(document).ready(function() {
        console.log("TAA v88: Marketing (Styled Text + Selection Mark)");

        if (typeof taa_mkt_vars === 'undefined') {
            console.error("TAA ERROR: taa_mkt_vars missing.");
            return;
        }

        var activeTradeData = null;
        var mainChartImg = new Image();
        mainChartImg.crossOrigin = "anonymous";

        // Drag Vars
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
                .taa-mkt-modal { width: 90vw; max-width: 1400px; background: #1e1e2d; border-radius: 8px; display: flex; flex-direction: column; box-shadow: 0 0 50px rgba(0,0,0,0.5); max-height: 95vh; z-index: 100000; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); }
                .taa-mkt-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; }
                .taa-mkt-canvas-wrap { flex: 1; display: flex; justify-content: center; align-items: center; padding: 20px; background: #000; overflow: auto; position: relative; }
                #taa-mkt-canvas { max-width: 100%; max-height: 80vh; object-fit: contain; box-shadow: 0 0 20px rgba(0,0,0,0.5); cursor: crosshair; }
                .taa-mkt-toolbar { padding: 15px; background: #2b2b40; display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
                .taa-mkt-title { color: #fff; font-weight: bold; font-size: 16px; }
                .taa-mkt-btn { padding: 10px 20px; margin-left: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: white; font-size: 14px; }
                .taa-btn-download { background: #007bff; }
                .taa-btn-download:hover { background: #0056b3; }
                .taa-btn-close { background: #dc3545; }
                .taa-btn-close:hover { background: #a71d2a; }

                @media only screen and (max-width: 768px) {
                    .taa-mkt-modal { width: 95vw; height: 90vh; max-height: none; }
                    .taa-mkt-canvas-wrap { padding: 10px; }
                    #taa-mkt-canvas { max-height: 60vh; width: 100%; }
                    .taa-mkt-toolbar { flex-direction: column; gap: 10px; padding: 15px 10px; }
                    .taa-mkt-actions { width: 100%; flex-direction: column; gap: 10px; }
                    .taa-mkt-btn { margin-left: 0; width: 100%; padding: 12px; font-size: 16px; }
                }
            </style>
            <div id="taa-mkt-overlay" class="taa-mkt-overlay" style="display:none;">
                <div class="taa-mkt-modal">
                    <div class="taa-mkt-canvas-wrap">
                        <canvas id="taa-mkt-canvas"></canvas>
                    </div>
                    <div class="taa-mkt-toolbar">
                        <div class="taa-mkt-title">Marketing Preview (Drag to Move)</div>
                        <div class="taa-mkt-actions">
                            <button id="taa-mkt-dl" class="taa-mkt-btn taa-btn-download">⬇ Download Image</button>
                            <button id="taa-mkt-close" class="taa-mkt-btn taa-btn-close">Close</button>
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
            
            if (!data) { alert('Trade Data Missing. Refresh page.'); return; }

            activeTradeData = data;
            draggableItems = []; 
            activeDragIndex = -1;
            
            $('#taa-mkt-overlay').fadeIn();
            mainChartImg.src = activeTradeData.img;
            mainChartImg.onload = function() { drawCanvas(); };

            var isBuy = (activeTradeData.dir.toUpperCase() === 'BUY');
            addDraggableImage(isBuy ? buySignalUrl : sellSignalUrl, 563, 580, 165, true); 
            addDraggableImage(tagUrl, 50, 150, 300, false);
            addDraggableImage(contactTagUrl, 900, 600, 150, false);
            
            // Text items
            addDraggableText("ENTRY", activeTradeData.entry, "#FFFFFF", 900, 480);    
            addDraggableText("TARGET", activeTradeData.target, "#FFFFFF", 900, 520); 
            addDraggableText("SL", activeTradeData.sl, "#FFFFFF", 750, 270);     
        });

        $('#taa-mkt-close').on('click', function() { $('#taa-mkt-overlay').fadeOut(); });

        // --- HELPERS ---
        function addDraggableImage(url, x, y, w, centered) {
            var item = { type: 'image', x: x, y: y, w: w, h: 0, img: new Image(), loaded: false };
            item.img.crossOrigin = "anonymous";
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
            // Initial dummy W/H, calculated in drawCanvas
            draggableItems.push({ type: 'text', label: label, value: value, color: color, x: x, y: y, w: 200, h: 30 });
        }
        function getMousePos(evt) {
            var cvs = document.getElementById('taa-mkt-canvas');
            var rect = cvs.getBoundingClientRect();
            var scaleX = 1126 / rect.width; var scaleY = 844 / rect.height; 
            var cx = evt.clientX; var cy = evt.clientY;
            if(evt.touches && evt.touches.length > 0) { cx = evt.touches[0].clientX; cy = evt.touches[0].clientY; }
            return { x: (cx - rect.left) * scaleX, y: (cy - rect.top) * scaleY };
        }

        // --- DRAG EVENTS ---
        $(document).on('mousedown touchstart', '#taa-mkt-canvas', function(e) {
            var evt = e.originalEvent || e; var pos = getMousePos(evt);
            // Check top-most item first
            for (var i = draggableItems.length - 1; i >= 0; i--) {
                var it = draggableItems[i];
                if (it.type === 'image' && !it.loaded) continue;
                if (pos.x >= it.x && pos.x <= it.x + it.w && pos.y >= it.y && pos.y <= it.y + it.h) {
                    isDragging = true; activeDragIndex = i; dragOffset.x = pos.x - it.x; dragOffset.y = pos.y - it.y; 
                    drawCanvas(); // Redraw to show selection
                    return; 
                }
            }
            // If clicked empty space, deselect
            if(activeDragIndex !== -1) {
                activeDragIndex = -1;
                drawCanvas();
            }
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
        $(document).on('mouseup touchend', function() { 
            isDragging = false; 
            // We keep activeDragIndex to show selection until they click elsewhere
            // drawCanvas(); 
        });

        // --- DRAW FUNCTION ---
        function drawCanvas() {
            var cvs = document.getElementById('taa-mkt-canvas');
            if(!cvs || !activeTradeData) return;
            var ctx = cvs.getContext('2d');
            var W = 1126; var H = 844;
            if(cvs.width !== W) { cvs.width = W; cvs.height = H; }
            
            ctx.setTransform(1, 0, 0, 1, 0, 0); 
            ctx.textBaseline = "alphabetic"; ctx.textAlign = "start";

            // 1. Background
            var isBuy = (activeTradeData.dir.toUpperCase() === 'BUY');
            var mainColor = isBuy ? '#28a745' : '#dc3545'; 
            ctx.fillStyle = '#0b0e11'; ctx.fillRect(0, 0, W, H);
            ctx.fillStyle = "#0b0e11"; ctx.fillRect(0, 0, W, 120);

            // 2. Header
            ctx.fillStyle = mainColor; ctx.fillRect(30, 25, 200, 70);
            ctx.fillStyle = "#FFFFFF"; ctx.font = "bold 40px Arial"; ctx.textAlign = "center";
            ctx.fillText((isBuy ? "▲" : "▼") + " " + activeTradeData.dir, 130, 75);

            var name = activeTradeData.inst + (activeTradeData.strike && activeTradeData.strike !== '-' ? " " + activeTradeData.strike : "");
            ctx.fillStyle = "#FFC107"; ctx.font = "bold 55px Arial";
            ctx.fillText(name, W/2, 80);

            // 3. Chart
            var imgY = 130; var imgH = 550; var imgW = W - 40; var imgX = 20;
            ctx.strokeStyle = "#ffffff"; ctx.lineWidth = 1; ctx.strokeRect(imgX, imgY, imgW, imgH);
            if (mainChartImg.complete) ctx.drawImage(mainChartImg, imgX, imgY, imgW, imgH);
            else { ctx.fillStyle = "#222"; ctx.fillRect(imgX, imgY, imgW, imgH); }

            // 4. Watermark
            ctx.save(); ctx.translate(W/2, imgY + imgH/2); ctx.rotate(-Math.PI / 6); 
            ctx.textAlign = "center"; ctx.fillStyle = "rgba(255, 255, 255, 0.1)"; 
            ctx.font = "900 130px Arial"; ctx.fillText("RDALGO.IN", 0, 0); ctx.restore();
            
            // 5. Draggable Items
            draggableItems.forEach(function(it, idx) {
                if (it.type === 'image' && it.loaded) {
                    ctx.drawImage(it.img, it.x, it.y, it.w, it.h);
                } else if (it.type === 'text') {
                    ctx.save();
                    // [UPDATED] Font Style: Smaller (20px) and Not Bold
                    ctx.font = "20px Arial"; 
                    ctx.textAlign = "left"; ctx.textBaseline = "top";
                    var txt = it.label + " @ " + it.value;
                    
                    // Stroke for visibility
                    ctx.lineWidth = 4; ctx.strokeStyle = "rgba(0,0,0,0.8)"; ctx.strokeText(txt, it.x, it.y);
                    
                    // Fill
                    ctx.fillStyle = it.color; ctx.fillText(txt, it.x, it.y);
                    
                    // Update dimensions for hit testing
                    it.w = ctx.measureText(txt).width; 
                    it.h = 24; // Approx height for 20px font
                    ctx.restore();
                }

                // [NEW] Draw Selection Box if Active
                if (idx === activeDragIndex) {
                    ctx.save();
                    ctx.strokeStyle = "#00FFFF"; // Cyan Color
                    ctx.lineWidth = 2;
                    ctx.setLineDash([6, 4]); // Dashed Line
                    ctx.strokeRect(it.x - 5, it.y - 5, it.w + 10, it.h + 10); // Padding
                    ctx.restore();
                }
            });

            // 6. Footer
            ctx.textBaseline = "alphabetic"; ctx.textAlign = "center";
            var startY = imgY + imgH + 15; 
            ctx.fillStyle = "#FFC107"; ctx.fillRect(0, startY, W, H - startY);
            var metrics = [ {l:"Profit",v:activeTradeData.profit},{l:"Risk",v:activeTradeData.risk},{l:"Risk & Reward",v:activeTradeData.rr.replace('1:','1:')},{l:"Lot Size",v:activeTradeData.lots} ];
            var colW = W/4; var cy = startY + ((H-startY)/2);
            metrics.forEach(function(m, i) {
                var x = (i*colW) + colW/2;
                ctx.fillStyle="#000"; ctx.font="bold 22px Arial"; ctx.fillText(m.l, x, cy - 22);
                ctx.font="900 42px Arial"; ctx.fillText(m.v, x, cy + 28);
                if(i<3) { ctx.fillStyle="#000"; ctx.fillRect((i+1)*colW, cy-35, 2, 70); }
            });
        }

        // --- DOWNLOAD ACTION ---
        $('#taa-mkt-dl').on('click', function() {
            // Deselect item before printing
            activeDragIndex = -1;
            drawCanvas(); 

            var canvas = document.getElementById('taa-mkt-canvas');
            var link = document.createElement('a');
            
            var fn = (activeTradeData.inst + "_" + activeTradeData.strike).replace(/\s+/g,'_').replace(/[^a-zA-Z0-9_]/g, '');
            if(!fn) fn = "Trade_Signal";
            
            link.download = fn + '.jpg';
            link.href = canvas.toDataURL("image/jpeg", 1.0); // Max Quality
            link.click();
        });
    });
})(jQuery);