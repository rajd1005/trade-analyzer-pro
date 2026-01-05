<?php
/**
 * Shortcode View: Published Marketing Gallery
 * View: TABLE (Matches 2nd Plugin Logic)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Permission Logic for "Privileged Users"
$current_user = wp_get_current_user();
$allowed_roles = get_option('taag_direct_add_roles', []);
if(!is_array($allowed_roles)) $allowed_roles = [];

$is_privileged = false;
if ( current_user_can('manage_options') ) {
    $is_privileged = true;
} else {
    foreach($current_user->roles as $role) {
        if(in_array($role, $allowed_roles)) {
            $is_privileged = true; 
            break;
        }
    }
}

// 2. Server Today (For logic sync)
$server_today = current_time('Y-m-d');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<div class="taa-dashboard-wrapper" style="max-width:1000px; margin:auto; padding:20px;">
    
    <div style="margin-bottom:20px;">
        <label><strong>Filter by Trade Date:</strong></label>
        <input type="text" id="taaGalDate" class="flatpickr" style="margin-bottom:15px; padding:6px 10px; border:1px solid #ccc; border-radius:4px;" readonly>
        <small id="taaAutoLoadStatus" style="color:green; display:none; margin-left:10px;">(Auto-updating...)</small>
    </div>

    <div id="taaGalContainer" style="overflow-x:auto;">
        </div>

</div>

<div id="taaImgModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:#000000c9;align-items:center;justify-content:center;z-index:99999;">
    <div onclick="closeTaaModal()" style="position:absolute;top:20px;right:20px;color:#fff;font-size:24px;cursor:pointer;">✖</div>
    <img id="taaModalImg" src="" style="max-width:90%;max-height:90%;">
</div>

<script>
const taaIsPrivileged = <?php echo $is_privileged ? 'true' : 'false'; ?>;
const taaAjaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
const taaNonce = "<?php echo wp_create_nonce('taa_nonce'); ?>";
const taaServerToday = "<?php echo $server_today; ?>";

function closeTaaModal() {
    document.getElementById('taaImgModal').style.display = 'none';
    document.getElementById('taaModalImg').src = '';
}

function openTaaModal(url) {
    // [FIX] Add timestamp to bust cache when viewing re-uploaded images
    var cacheBuster = "?t=" + new Date().getTime();
    document.getElementById('taaModalImg').src = url + cacheBuster;
    document.getElementById('taaImgModal').style.display = 'flex';
}

async function loadTaaGallery(isAutoLoad = false) {
    const container = document.getElementById('taaGalContainer');
    if(!isAutoLoad) container.innerHTML = "🔄 Loading...";
    else document.getElementById('taaAutoLoadStatus').style.display = 'inline';

    const date = document.getElementById('taaGalDate').value;
    
    if (!date) {
        if(!isAutoLoad) container.innerHTML = "<p style='color:red;'>❌ Please select a date</p>";
        return;
    }

    try {
        jQuery.post(taaAjaxUrl, {
            action: 'taa_load_published_gallery',
            date: date
        }, function(response) {
            
            if (!response.success) {
                if(!isAutoLoad) container.innerHTML = "<p style='color:red;'>❌ " + (response.data || 'Error loading') + "</p>";
                return;
            }

            const images = response.data; 
            
            if (!images || images.length === 0) {
                if(!isAutoLoad) container.innerHTML = "<p>No images found for " + date + ".</p>";
                return;
            }

            const table = document.createElement('table');
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            table.className = 'taa-staging-table'; 
            table.innerHTML = `
                <thead>
                    <tr style="background:#f0f0f0;">
                        <th style="padding:8px;border:1px solid #ccc;">Trade Date</th>
                        <th style="padding:8px;border:1px solid #ccc;">Name</th>
                        <th style="padding:8px;border:1px solid #ccc;">View</th>
                        <th style="padding:8px;border:1px solid #ccc;">Delete</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;

            images.forEach(img => {
                const row = document.createElement('tr');
                const canDelete = taaIsPrivileged || (img.trade_date === taaServerToday);

                // [UPDATE] Added data-url to pass to delete function
                row.innerHTML = `
                    <td style="padding:8px;border:1px solid #ccc;text-align:center;">${img.trade_date}</td>
                    <td style="padding:8px;border:1px solid #ccc;text-align:center;">${img.name}</td>
                    <td style="padding:8px;border:1px solid #ccc;text-align:center;">
                        <button onclick="openTaaModal('${img.image_url}')" style="padding:5px 10px; cursor:pointer;">View</button>
                    </td>
                    <td style="padding:8px;border:1px solid #ccc;text-align:center;">
                        ${canDelete
                            ? `<button class="taa-gal-del-btn" data-id="${img.id}" data-date="${img.trade_date}" data-url="${img.image_url}" style="padding:5px 10px;background:red;color:white;border:none;border-radius:3px;cursor:pointer;">Delete</button>`
                            : '<span style="color:#aaa;">Locked</span>'}
                    </td>
                `;
                table.querySelector('tbody').appendChild(row);
            });

            container.innerHTML = '';
            container.appendChild(table);

        }).fail(function() {
            if(!isAutoLoad) container.innerHTML = "<p style='color:red;'>❌ Server Connection Error</p>";
        }).always(function(){
            document.getElementById('taaAutoLoadStatus').style.display = 'none';
        });

    } catch (err) {
        console.error(err);
    }
}

document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('taa-gal-del-btn')) {
        e.preventDefault();
        const id = e.target.getAttribute('data-id');
        const date = e.target.getAttribute('data-date');
        const url = e.target.getAttribute('data-url'); // [UPDATE] Get URL
        deleteTaaImage(id, date, url, e.target);
    }
});

function deleteTaaImage(id, date, url, btn) {
    if (!confirm("Are you sure you want to delete this image? This will remove the View/Download buttons from dashboards as well.")) return;
    btn.textContent = '...'; btn.disabled = true;

    jQuery.post(taaAjaxUrl, {
        action: 'taa_delete_published_image',
        security: taaNonce,
        id: id,
        trade_date: date,
        image_url: url // [UPDATE] Pass URL to server
    }, function(res) {
        if (res.success) {
            // 1. Remove from Gallery Table
            const row = btn.closest('tr');
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 500);

            // 2. [UPDATE] Immediately remove View/Download buttons from other tables on the page
            if (url) {
                // Find buttons with this URL in other dashboards (Staging, Approved, History)
                const linkedButtons = document.querySelectorAll(`button[data-img="${url}"], a[href="${url}"]`);
                linkedButtons.forEach(el => {
                    el.remove();
                });
            }

        } else {
            alert("❌ Delete failed: " + (res.data || ''));
            btn.textContent = 'Delete'; btn.disabled = false;
        }
    }).fail(function() {
        alert("❌ Network Error");
        btn.textContent = 'Delete'; btn.disabled = false;
    });
}

document.addEventListener('taa_gallery_refresh', function() {
    loadTaaGallery(false);
});

document.addEventListener('DOMContentLoaded', function () {
    flatpickr("#taaGalDate", {
        dateFormat: "Y-m-d",
        defaultDate: taaServerToday,
        onChange: function() { loadTaaGallery(false); }
    });
    loadTaaGallery(false);
    setInterval(function() {
        loadTaaGallery(true);
    }, 10000);
});
</script>