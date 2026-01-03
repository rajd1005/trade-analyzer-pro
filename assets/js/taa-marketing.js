(function($) {
    'use strict';

    $(document).ready(function() {
        console.log("TAA v30.9: Marketing (Robust Loader Fix)");

        if (typeof taa_mkt_vars === 'undefined') {
            console.error("TAA ERROR: taa_mkt_vars missing.");
            return;
        }

        var activeTradeData = null;
        var mainChartImg = null; // Will be initialized per click

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
            <div id="taa-mkt-overlay" class="taa-mkt-overlay" style="display:none;">
                <div class="taa-mkt-modal">
                    <div class="taa-mkt-canvas-wrap">
                        <canvas id="taa-mkt-canvas"></canvas>
                    </div>
                    <div class="taa-mkt-toolbar">
                        <div class="taa-mkt-title">Marketing Preview (Drag elements to position)</div>
                        <div class="taa-mkt-actions">
                            <button id="taa-mkt-dl" class="taa-mkt-btn taa-btn-download">⬇ Download Image</button>
                            <button id="taa-mkt-close" class="taa-mkt-btn taa-btn-close">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        if ($('#taa-mkt-overlay').length === 0) $('body').append(modalHtml);

        // --- OPEN HANDLER (THE FIX) ---
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
            
            // 2. Open Modal First
            $('#taa-mkt-overlay').fadeIn();

            // 3. Initialize Items
            var isBuy = (activeTradeData.dir.toUpperCase() === 'BUY');
            addDraggableImage(isBuy ? buySignalUrl : sellSignalUrl, 563, 580, 165, true); 
            addDraggableImage(tagUrl, 50, 150, 300, false);
            addDraggableImage(contactTagUrl, 900, 600, 150, false);
            
            // Text items
            addDraggableText("ENTRY", activeTradeData.entry, "#FFFFFF", 900, 480);    
            addDraggableText("TARGET", activeTradeData.target, "#FFFFFF", 900, 520); 
            addDraggableText("SL", activeTradeData.sl, "#FFFFFF", 750, 270);     

            // 4. Force Initial Draw (Shows background + text immediately)
            drawCanvas();

            // 5. Load Chart Image (Robust Method)
            mainChartImg = new Image();
            mainChartImg.crossOrigin = "anonymous";
            
            mainChartImg.onload = function() { 
                console.log("Chart image loaded.");
                drawCanvas(); // Redraw when image arrives
            };
            
            mainChartImg.onerror = function() {
                console.error("Failed to load chart image:", activeTradeData.img);
            };

            // Set source AFTER defining onload
            mainChartImg.src = activeTradeData.img;

            // Handle cached images immediately
            if (mainChartImg.complete) {
                drawCanvas();
            }
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
            
            // Reverse loop to select top-most items first
            for (var i = draggableItems.length - 1; i >= 0; i--) {
                var it = draggableItems[i];
                if (it.type === 'image' && !it.loaded) continue;
                
                // Hit detection
                if (pos.x >= it.x && pos.x <= it.x + it.w && pos.y >= it.y && pos.y <= it.y + it.h) {
                    isDragging = true; activeDragIndex = i; dragOffset.x = pos.x - it.x; dragOffset.y = pos.y - it.y; 
                    drawCanvas(); 
                    return; 
                }
            }
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
        });

        // --- DRAW FUNCTION ---
        function drawCanvas() {
            var cvs = document.getElementById('taa-mkt-canvas');
            if(!cvs || !activeTradeData) return;
            var ctx = cvs.getContext('2d');
            var W = 1126; var H = 844;
            
            // Ensure canvas internal resolution matches logic
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

            // 3. Chart Image
            var imgY = 130; var imgH = 550; var imgW = W - 40; var imgX = 20;
            ctx.strokeStyle = "#ffffff"; ctx.lineWidth = 1; ctx.strokeRect(imgX, imgY, imgW, imgH);
            
            // Check if image is ready to draw
            if (mainChartImg && mainChartImg.complete && mainChartImg.naturalWidth !== 0) {
                ctx.drawImage(mainChartImg, imgX, imgY, imgW, imgH);
            } else {
                // Placeholder while loading
                ctx.fillStyle = "#222"; 
                ctx.fillRect(imgX, imgY, imgW, imgH);
                ctx.fillStyle = "#555"; 
                ctx.font = "20px Arial"; 
                ctx.textAlign = "center";
                ctx.fillText("Loading Chart...", W/2, imgY + imgH/2);
            }

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
                    ctx.font = "20px Arial"; 
                    ctx.textAlign = "left"; ctx.textBaseline = "top";
                    var txt = it.label + " @ " + it.value;
                    
                    ctx.lineWidth = 4; ctx.strokeStyle = "rgba(0,0,0,0.8)"; ctx.strokeText(txt, it.x, it.y);
                    ctx.fillStyle = it.color; ctx.fillText(txt, it.x, it.y);
                    
                    it.w = ctx.measureText(txt).width; it.h = 24;
                    ctx.restore();
                }

                // Selection Box
                if (idx === activeDragIndex) {
                    ctx.save();
                    ctx.strokeStyle = "#00FFFF"; ctx.lineWidth = 2;
                    ctx.setLineDash([6, 4]); 
                    ctx.strokeRect(it.x - 5, it.y - 5, it.w + 10, it.h + 10); 
                    ctx.restore();
                }
            });

            // 6. Footer
            ctx.textBaseline = "alphabetic"; ctx.textAlign = "center";
            var startY = imgY + imgH + 15; 
            ctx.fillStyle = "#FFC107"; ctx.fillRect(0, startY, W, H - startY);
            
            // Clean up numbers for display
            var cleanProfit = activeTradeData.profit.replace(/[^0-9.,₹-]/g, ''); // Ensure currency symbol is kept if needed
            var metrics = [ 
                {l:"Profit", v: activeTradeData.profit},
                {l:"Risk", v: activeTradeData.risk},
                {l:"Risk & Reward", v: "1:" + activeTradeData.rr.replace('1:','')}, // Prevent double 1:1:
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

        // --- DOWNLOAD ACTION ---
        $('#taa-mkt-dl').on('click', function() {
            activeDragIndex = -1; // Deselect before saving
            drawCanvas(); 

            var canvas = document.getElementById('taa-mkt-canvas');
            var link = document.createElement('a');
            
            var fn = (activeTradeData.inst + "_" + activeTradeData.strike).replace(/\s+/g,'_').replace(/[^a-zA-Z0-9_]/g, '');
            if(!fn) fn = "Trade_Signal";
            
            link.download = fn + '.jpg';
            link.href = canvas.toDataURL("image/jpeg", 1.0);
            link.click();
        });
    });
})(jQuery);
