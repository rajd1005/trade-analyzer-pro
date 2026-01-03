(function($) {
    'use strict';

    $(document).ready(function() {
        console.log("TAA v65: Daily Report (Fetch-on-Demand)");

        const canvasId = 'taa-daily-canvas';
        
        // --- COLORS ---
        const C_BG = "#1e1e2d"; 
        const C_HEADER_BG = "#FFC107"; 
        const C_HEADER_TEXT = "#000000";
        const C_ROW_EVEN = "#2b2b40";
        const C_ROW_ODD = "#1e1e2d";
        const C_GREEN = "#28a745";
        const C_RED = "#dc3545";
        const C_WATERMARK = "rgba(255, 255, 255, 0.04)"; 

        const BASE_W = 1126;
        const BASE_H = 844; 
        const PORTRAIT_H = 1408; 
        
        const MKT_SUBHEADER = "AUTO BUY & SELL SIGNAL WITH STOPLOSS & TARGET";
        const MKT_FOOTER_1 = "RDALGO.IN";
        const MKT_FOOTER_2 = "74398 95689";

        // --- 1. NEW CLICK HANDLER (Fetch Data) ---
        $(document).on('click', '.taa-btn-daily-report', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $wrapper = $btn.closest('.taa-dashboard-wrapper');
            var dateVal = $wrapper.find('.taa-date-input').val(); // Get Live Date

            if (!dateVal) {
                alert("Please select a date first.");
                return;
            }

            // UI Feedback
            var originalText = $btn.text();
            $btn.text('⏳ Loading...').prop('disabled', true);

            // AJAX FETCH
            $.post(taa_vars.ajaxurl, {
                action: 'taa_fetch_daily_report',
                date: dateVal
            }, function(res) {
                $btn.text(originalText).prop('disabled', false);

                if (res.success) {
                    var data = res.data;
                    if (!data.trades || data.trades.length === 0) {
                        alert("No approved trades found for " + data.date_display);
                        return;
                    }
                    // Generate Image with Fetched Data
                    generateDailyImage(data.trades, data.date_display);
                } else {
                    alert("Error fetching data: " + res.data);
                }
            }).fail(function() {
                $btn.text(originalText).prop('disabled', false);
                alert("Server connection failed.");
            });
        });

        // --- 2. GENERATE IMAGE (Logic Unchanged) ---
        function generateDailyImage(trades, dateStr) {
            
            // --- A. DIMENSIONS ---
            const headerHeight = 160;   
            const tableHeadH = 60;
            const footerHeight = 160; 
            const padding = 20;
            
            let canvasH = BASE_H;
            let availableH = canvasH - headerHeight - tableHeadH - footerHeight - padding;
            let rowH = Math.floor(availableH / trades.length);
            
            if (rowH < 50) {
                canvasH = PORTRAIT_H;
                availableH = canvasH - headerHeight - tableHeadH - footerHeight - padding;
                rowH = Math.floor(availableH / trades.length);
            }
            if (rowH > 85) rowH = 85;

            const W = BASE_W;
            const H = canvasH;

            // --- B. CANVAS ---
            let canvas = document.getElementById(canvasId);
            if (!canvas) {
                $('body').append(`<canvas id="${canvasId}" style="display:none;"></canvas>`);
                canvas = document.getElementById(canvasId);
            }
            canvas.width = W;
            canvas.height = H;
            const ctx = canvas.getContext('2d');

            // --- C. DRAWING ---
            ctx.fillStyle = C_BG;
            ctx.fillRect(0, 0, W, H);

            // Header
            ctx.fillStyle = C_HEADER_BG;
            ctx.fillRect(0, 0, W, 100);
            ctx.fillStyle = C_HEADER_TEXT;
            ctx.font = "900 50px Arial";
            ctx.textAlign = "center"; ctx.textBaseline = "middle";
            ctx.fillText("DAILY PERFORMANCE - " + dateStr, W/2, 52);

            // Sub-Header
            ctx.fillStyle = "#2b2b40";
            ctx.fillRect(0, 100, W, 60);
            ctx.fillStyle = "#FFFFFF";
            ctx.font = "bold 30px Arial";
            ctx.fillText(MKT_SUBHEADER, W/2, 132);

            // Table Header
            let y = headerHeight + 10;
            ctx.fillStyle = "#444"; 
            ctx.fillRect(40, y, W-80, tableHeadH);
            ctx.fillStyle = "#FFF";
            ctx.font = "bold 30px Arial";
            ctx.textAlign = "left";  ctx.fillText("SIGNAL", 80, y + 32); 
            ctx.textAlign = "center"; ctx.fillText("SYMBOL", W/2, y + 32);
            ctx.textAlign = "right"; ctx.fillText("PROFIT", W-80, y + 32);

            y += tableHeadH;

            // Trades Loop
            let totalProfit = 0;
            let fontSize = 32;
            if (rowH < 60) fontSize = 28;
            if (rowH < 40) fontSize = 22;

            trades.forEach((t, i) => {
                totalProfit += parseFloat(t.profit_raw);

                ctx.fillStyle = (i % 2 === 0) ? C_ROW_EVEN : C_ROW_ODD;
                ctx.fillRect(40, y, W-80, rowH);
                ctx.fillStyle = "#333";
                ctx.fillRect(40, y + rowH - 1, W-80, 1);

                const isBuy = t.dir === 'BUY';
                const isProfit = parseFloat(t.profit_raw) >= 0;
                const textY = y + (rowH/2) + (fontSize * 0.35); 
                
                ctx.font = `bold ${fontSize}px Arial`;
                ctx.textAlign = "left";
                ctx.fillStyle = isBuy ? C_GREEN : C_RED;
                ctx.fillText(t.dir, 80, textY);

                ctx.fillStyle = "#FFF";
                ctx.textAlign = "center";
                let sym = t.symbol;
                if(t.strike && t.strike !== '-') sym += " " + t.strike;
                ctx.fillText(sym.toUpperCase(), W/2, textY);

                ctx.textAlign = "right";
                ctx.fillStyle = isProfit ? C_GREEN : C_RED;
                ctx.fillText(t.profit_fmt, W-80, textY);

                y += rowH;
            });

            // Watermark
            ctx.save();
            ctx.translate(W/2, H/2);
            ctx.rotate(-Math.PI / 6); 
            ctx.textAlign = "center"; ctx.textBaseline = "middle";
            ctx.fillStyle = C_WATERMARK;
            
            const wmSize = Math.floor(W * 0.15); 
            ctx.font = `900 ${wmSize}px Arial`;
            const metrics = ctx.measureText("RDALGO.IN");
            const stepX = metrics.width * 1.2; 
            const stepY = wmSize * 1.5;

            for (let wx = -W*1.5; wx < W*1.5; wx += stepX) {
                for (let wy = -H*1.5; wy < H*1.5; wy += stepY) {
                    ctx.fillText("RDALGO.IN", wx, wy);
                }
            }
            ctx.restore();

            // Footer
            const footerY = H - footerHeight; 
            
            const totalColor = totalProfit >= 0 ? C_GREEN : C_RED;
            const totalFmt = "₹" + totalProfit.toLocaleString('en-IN');
            
            const boxW = 600; const boxH = 90;
            const boxX = (W - boxW) / 2;
            const boxY = footerY + 10;
            
            ctx.fillStyle = totalColor;
            ctx.beginPath(); ctx.roundRect(boxX, boxY, boxW, boxH, 15); ctx.fill();
            ctx.strokeStyle = "#FFF"; ctx.lineWidth = 4; ctx.stroke();

            ctx.fillStyle = "#FFF"; ctx.textAlign = "center"; ctx.textBaseline = "middle";
            ctx.font = "bold 50px Arial";
            ctx.fillText("TOTAL: " + totalFmt, W/2, boxY + (boxH/2) + 3);

            ctx.textBaseline = "alphabetic";
            ctx.fillStyle = "#FFC107"; 
            ctx.font = "900 42px Arial";
            ctx.fillText(`${MKT_FOOTER_1}   ${MKT_FOOTER_2}`, W/2, boxY + boxH + 45);

            // Download
            const link = document.createElement('a');
            const cleanDate = dateStr.replace(/ /g, '_');
            link.download = `Daily_Report_${cleanDate}.jpg`;
            link.href = canvas.toDataURL("image/jpeg", 0.95);
            link.click();
        }
    });
})(jQuery);