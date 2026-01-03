(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log("TAA v40: Staging Module (Rejection Editor Fix)");

        // 1. APPROVE ACTION
        $(document).on('click', '.taa-js-approve', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            Swal.fire({
                title: 'Approve Trade?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes', confirmButtonColor: '#28a745'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(taa_vars.ajaxurl, { action: 'taa_approve_trade', id: id }, function(res) {
                        if (res.success) {
                            Toast.fire({ icon: 'success', title: 'Approved' });
                            notifyGlobalChange();
                        } else {
                            Swal.fire('Error', res.data, 'error');
                        }
                    });
                }
            });
        });

        // 2. REJECTION EDITOR INIT
        let rejectId = null;
        let ptro = null;
        let rejectNote = '';

        $(document).on('click', '.taa-js-reject-init', function(e) {
            e.preventDefault();
            var btn = $(this);
            rejectId = btn.data('id');
            var imgUrl = btn.data('img');
            rejectNote = '';

            // Populate Info
            $('#inf-dir').text(btn.data('dir') || '-');
            $('#inf-entry').text(btn.data('entry') || '-');
            $('#inf-target').text(btn.data('target') || '-');
            $('#inf-sl').text(btn.data('sl') || '-');
            $('#inf-risk').text(btn.data('risk') || '-'); 
            $('#inf-rr').text("1:" + (btn.data('rr') || '-'));
            $('#inf-profit').text(btn.data('profit') || '-');
            $('#taa-reject-note').val('');
            
            // Open Modal
            $('#taa-reject-modal').fadeIn();
            
            // Prepare Container
            var $holder = $('#taa-editor-holder');
            $holder.html('<div style="text-align:center; padding:50px; color:#777;">Loading Image Editor...<br><small>This may take a few seconds.</small></div>');
            btn.prop('disabled', true);

            // Fetch Base64 Image
            $.post(taa_vars.ajaxurl, { 
                action: 'taa_get_image_base64', 
                img_url: imgUrl 
            }, function(res) {
                btn.prop('disabled', false);
                
                if(res.success && res.data.base64) {
                    $holder.empty(); 
                    var uniqueId = 'ptro-instance-' + Date.now();
                    $holder.html('<div id="' + uniqueId + '" style="width:100%; height:100%;"></div>');

                    try {
                        ptro = Painterro({
                            id: uniqueId,
                            colorScheme: { main: '#f8f9fa', control: '#333', controlContent: '#fff', activeControl: '#0073aa' },
                            tool: 'arrow',
                            defaultTool: 'arrow',
                            hiddenTools: ['save', 'close', 'open', 'crop', 'resize', 'rotate', 'pixelize'], 
                            
                            saveHandler: function(image, done) {
                                var imgData = image.asDataURL();
                                var $submitBtn = $('#taa-submit-reject');
                                
                                $.post(taa_vars.ajaxurl, {
                                    action: 'taa_reject_trade',
                                    id: rejectId,
                                    note: rejectNote,
                                    image_data: imgData
                                }, function(res) {
                                    $submitBtn.text('Reject Trade').prop('disabled', false);
                                    if (res.success) {
                                        $('#taa-reject-modal').fadeOut();
                                        Toast.fire({ icon: 'info', title: 'Rejected & Sent' });
                                        notifyGlobalChange();
                                    } else {
                                        Swal.fire('Error', res.data, 'error');
                                    }
                                    done(true); 
                                }).fail(function() {
                                    $submitBtn.text('Reject Trade').prop('disabled', false);
                                    Swal.fire('Error', 'Server Connection Failed', 'error');
                                    done(true);
                                });
                            }
                        });
                        
                        ptro.show(res.data.base64);
                        
                    } catch(err) {
                        console.error(err);
                        $holder.html('<p style="color:red; text-align:center; padding:20px;">Editor Init Failed: ' + err.message + '</p>');
                    }

                } else {
                    $holder.html('<p style="color:red; text-align:center; padding:20px;"><b>Image Load Failed</b><br>' + (res.data || 'Unknown Error') + '</p>');
                }
            }).fail(function(xhr, status, error) {
                btn.prop('disabled', false);
                $holder.html('<p style="color:red; text-align:center; padding:20px;"><b>Server Error ('+xhr.status+')</b><br>' + error + '</p>');
            });
        });

        // 3. FINAL SUBMIT
        $('#taa-submit-reject').on('click', function(e) {
            e.preventDefault();
            
            if (!ptro) return;

            rejectNote = $('#taa-reject-note').val();
            $(this).text('Saving...').prop('disabled', true);

            try {
                ptro.save(); 
            } catch(e) {
                console.error(e);
                $(this).text('Reject Trade').prop('disabled', false);
                Swal.fire('Error', 'Editor not ready.', 'error');
            }
        });

        // 4. MODAL CONTROLS
        $('#taa-modal-close').on('click', function() {
            $('#taa-reject-modal').fadeOut();
        });

        $('#taa-btn-fullscreen').on('click', function(e) {
            e.preventDefault();
            $('#taa-reject-modal .taa-modal-content').toggleClass('fullscreen');
        });
    });
})(jQuery);