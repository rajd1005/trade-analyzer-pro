<?php
// FILE: includes/shortcode-instruments-view.php
if ( ! defined( 'ABSPATH' ) ) exit;

// NO RESTRICTIONS: Renders for all users (Logged in or out)
?>
<div class="taa-dashboard-wrapper" style="width:100%; max-width:100%; margin:0 auto;">
    
    <div class="taa-staging-header" style="background:#2b2b40; color:white; padding:15px;">
        <h2 style="color:white; margin:0;">⚙️ Instrument Editor</h2>
        <button id="taa-inst-save-unique" class="taa-inst-save-btn" style="width:auto; margin:0; padding:8px 20px; background:#28a745; border:none; color:white; border-radius:4px; cursor:pointer; font-weight:bold;">
            💾 Save Changes
        </button>
    </div>
    
    <div class="taa-table-responsive" style="max-height: 70vh; overflow: auto; position: relative;">
        <table class="taa-staging-table" id="taa-inst-table" style="width:100%; border-collapse: separate; border-spacing: 0;">
            <thead style="position: sticky; top: 0; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <tr>
                    <th style="background:#f1f1f1; color:#333; padding:12px; border-bottom:2px solid #ccc;">Instrument Name</th>
                    <th style="width:120px; min-width:120px; background:#f1f1f1; color:#333; padding:12px; border-bottom:2px solid #ccc;">Lot Size</th>
                    <th style="width:120px; background:#f1f1f1; color:#333; padding:12px; border-bottom:2px solid #ccc;">Mode</th>
                    <th style="width:120px; background:#f1f1f1; color:#333; padding:12px; border-bottom:2px solid #ccc;">Strike Req?</th>
                    <th style="width:60px; background:#f1f1f1; color:#333; padding:12px; border-bottom:2px solid #ccc; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="taa-inst-body" style="background:#fff;">
                </tbody>
            <tfoot>
                <tr style="background:#f9f9f9;">
                    <td colspan="5" style="padding:10px;">
                        <button id="taa-inst-add" style="width:100%; padding:12px; background:#0073aa; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600;">+ Add New Instrument</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
    /* Scoped Styles to prevent bleeding */
    .taa-inst-input { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing: border-box; }
    .taa-inst-select { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing: border-box; }
    .taa-inst-del { background:#dc3545; color:white; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; margin:0 auto;}
    .taa-inst-del:hover { background:#a71d2a; }
</style>