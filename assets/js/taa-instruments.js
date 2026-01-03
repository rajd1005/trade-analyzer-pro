(function($) {
    'use strict';

    $(document).ready(function() {
        // Prevent running if table doesn't exist
        if($('#taa-inst-table').length === 0) return;

        console.log("TAA Instruments Editor Loaded (Fixed)");

        // 1. Load Data
        loadInstruments();

        function loadInstruments() {
            $('#taa-inst-body').html('<tr><td colspan="5" style="text-align:center; padding:20px;">Loading...</td></tr>');
            
            $.post(taa_vars.ajaxurl, { action: 'taa_get_instruments' }, function(res) {
                $('#taa-inst-body').empty();
                if(res.success && res.data.length > 0) {
                    res.data.forEach(item => addRow(item));
                } else {
                    addRow(); // Empty row
                }
            }).fail(function() {
                $('#taa-inst-body').html('<tr><td colspan="5" style="text-align:center; color:red;">Failed to load data.</td></tr>');
            });
        }

        // 2. Add Row Function
        function addRow(data = {name:'', lot:25, mode:'BOTH', req:'NO'}) {
            var html = `
            <tr>
                <td><input type="text" class="taa-inst-input i-name" value="${data.name}" placeholder="e.g. NIFTY"></td>
                <td><input type="number" class="taa-inst-input i-lot" value="${data.lot}"></td>
                <td>
                    <select class="taa-inst-select i-mode">
                        <option value="BOTH" ${data.mode==='BOTH'?'selected':''}>BOTH</option>
                        <option value="BUY" ${data.mode==='BUY'?'selected':''}>BUY</option>
                        <option value="SELL" ${data.mode==='SELL'?'selected':''}>SELL</option>
                    </select>
                </td>
                <td>
                    <select class="taa-inst-select i-req">
                        <option value="NO" ${data.req==='NO'?'selected':''}>NO</option>
                        <option value="YES" ${data.req==='YES'?'selected':''}>YES</option>
                    </select>
                </td>
                <td style="text-align:center;"><button class="taa-inst-del" title="Remove">&times;</button></td>
            </tr>`;
            $('#taa-inst-body').append(html);
        }

        // 3. Events
        
        // ADD ROW
        $('#taa-inst-add').on('click', function(e) { 
            e.preventDefault(); 
            e.stopPropagation();
            addRow(); 
        });

        // DELETE ROW
        $(document).on('click', '.taa-inst-del', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if(confirm('Remove this instrument?')) {
                $(this).closest('tr').remove();
            }
        });

        // SAVE CHANGES (Conflict Fixed)
        $('#taa-inst-save-unique').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // Ensures no other handlers fire

            var $btn = $(this);
            var originalText = $btn.text();
            $btn.text('Saving...').prop('disabled', true);

            var items = [];
            $('#taa-inst-body tr').each(function() {
                var name = $(this).find('.i-name').val();
                if(name && name.trim() !== '') {
                    items.push({
                        name: name.trim().toUpperCase(),
                        lot: $(this).find('.i-lot').val(),
                        mode: $(this).find('.i-mode').val(),
                        req: $(this).find('.i-req').val()
                    });
                }
            });

            $.post(taa_vars.ajaxurl, {
                action: 'taa_save_instruments',
                items: items,
                nonce: taa_vars.nonce
            }, function(res) {
                $btn.text(originalText).prop('disabled', false);
                if(res.success) {
                    Swal.fire({ 
                        title: 'Saved!', 
                        icon: 'success', 
                        toast: true, 
                        position: 'top-end', 
                        timer: 2000, 
                        showConfirmButton: false 
                    });
                } else {
                    Swal.fire('Error', res.data || 'Save failed', 'error');
                }
            }).fail(function() {
                $btn.text(originalText).prop('disabled', false);
                Swal.fire('Error', 'Network Error', 'error');
            });
        });
    });
})(jQuery);