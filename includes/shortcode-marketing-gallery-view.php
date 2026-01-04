<?php
/**
 * Shortcode View: Published Marketing Gallery
 * Fetches data via Local AJAX Proxy to avoid CORS.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Determine if user can always delete (Privileged Role or Admin)
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

$today = current_time('Y-m-d');
?>

<div class="taa-dashboard-wrapper" style="padding: 20px;">
    <div class="taa-staging-header">
        <h2>Published Marketing Images</h2>
        <div style="display:flex; gap:10px;">
            <input type="date" id="taa-gal-date" class="taa-date-input" value="<?php echo esc_attr($today); ?>">
            <button id="taa-gal-refresh" class="taa-refresh-btn">↻ Refresh</button>
        </div>
    </div>

    <div id="taa-gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; padding: 20px 0;">
        <p style="grid-column: 1/-1; text-align:center;">Loading...</p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const isPrivileged = <?php echo $is_privileged ? 'true' : 'false'; ?>;
    const todayStr = "<?php echo $today; ?>";

    loadGallery();

    $('#taa-gal-refresh').on('click', loadGallery);
    $('#taa-gal-date').on('change', loadGallery);

    function loadGallery() {
        const date = $('#taa-gal-date').val();
        const $grid = $('#taa-gallery-grid');
        
        $grid.html('<p style="grid-column: 1/-1; text-align:center;">Fetching images...</p>');

        // Call Local Proxy to avoid CORS
        $.post(taa_vars.ajaxurl, {
            action: 'taa_load_published_gallery',
            date: date
        }, function(response) {
            $grid.empty();

            if (!response.success) {
                $grid.html('<p style="grid-column: 1/-1; text-align:center; color:red;">' + (response.data || 'Error loading gallery') + '</p>');
                return;
            }

            const data = response.data; // Expecting array of {id, name, image_url, trade_date}

            if (!data || data.length === 0) {
                $grid.html('<p style="grid-column: 1/-1; text-align:center;">No published images found for ' + date + '.</p>');
                return;
            }

            // Loop through images
            data.forEach(function(img) {
                // DELETE LOGIC: Allowed if (Date is Today) OR (User is Privileged)
                let canDelete = false;
                if (img.trade_date === todayStr) {
                    canDelete = true;
                } else if (isPrivileged) {
                    canDelete = true;
                }

                let html = `
                    <div style="border:1px solid #ddd; border-radius:8px; overflow:hidden; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                        <a href="${img.image_url}" target="_blank" style="display:block; height:150px; overflow:hidden;">
                            <img src="${img.image_url}" style="width:100%; height:100%; object-fit:cover;">
                        </a>
                        <div style="padding:10px;">
                            <strong style="display:block; font-size:13px; margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${img.name}</strong>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <a href="${img.image_url}" target="_blank" style="font-size:12px; color:#0073aa; text-decoration:none;">View</a>
                                ${ canDelete ? 
                                    `<button class="taa-gal-del" data-id="${img.id}" data-date="${img.trade_date}" style="background:#dc3545; color:#fff; border:none; border-radius:3px; padding:2px 8px; cursor:pointer; font-size:11px;">Delete</button>` 
                                    : '<span style="font-size:10px; color:#999;" title="Only Admin can delete past dates">Locked</span>' 
                                }
                            </div>
                        </div>
                    </div>
                `;
                $grid.append(html);
            });

        }).fail(function() {
            $grid.html('<p style="grid-column: 1/-1; text-align:center; color:red;">Server Connection Error</p>');
        });
    }

    // Delete Handler
    $(document).on('click', '.taa-gal-del', function(e) {
        e.preventDefault();
        if(!confirm("Are you sure? This will delete the file from the remote server.")) return;

        var $btn = $(this);
        var id = $btn.data('id');
        var date = $btn.data('date');

        $btn.text('...').prop('disabled', true);

        $.post(taa_vars.ajaxurl, {
            action: 'taa_delete_published_image',
            security: taa_vars.nonce,
            id: id,
            trade_date: date
        }, function(res) {
            if(res.success) {
                $btn.closest('div').parent().fadeOut(); // Remove card from view
            } else {
                alert('Error: ' + (res.data || 'Unknown'));
                $btn.text('Delete').prop('disabled', false);
            }
        }).fail(function() {
            alert('Server Error');
            $btn.text('Delete').prop('disabled', false);
        });
    });
});
</script>