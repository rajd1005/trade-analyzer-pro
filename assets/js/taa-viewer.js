(function($) {
    'use strict';

    $(document).ready(function() {
        console.log("TAA v30.13: Viewer Module (Drag Hand + Marketing)");
        
        let viewerInstance = null;

        // Shared function for opening the viewer
        function openViewer(imgUrl, titleText) {
            if (!imgUrl) return;

            // Create temporary image
            var image = new Image();
            image.src = imgUrl;
            image.alt = titleText || "Image Viewer";

            if (viewerInstance) {
                viewerInstance.destroy();
            }

            // Initialize ViewerJS
            viewerInstance = new Viewer(image, {
                hidden: function () {
                    viewerInstance.destroy();
                },
                
                // --- ZOOM & DRAG SETTINGS ---
                zoomable: true,       
                zoomOnTouch: false,   
                zoomOnWheel: false,   
                movable: true,        
                toggleOnDblclick: false, 
                
                // --- VISUALS ---
                tooltip: true,
                navbar: false, 
                title: function() {
                    return titleText || 'View Image';
                },
                toolbar: {
                    zoomIn: 1,
                    zoomOut: 1,
                    oneToOne: 1,
                    reset: 1,
                    rotateLeft: 0,
                    rotateRight: 0,
                    flipHorizontal: 0,
                    flipVertical: 0,
                },
                backdrop: true,
                className: 'taa-native-viewer', 
                
                viewed: function() {
                    viewerInstance.move(0, 0); 
                }
            });

            viewerInstance.show();
        }

        // 1. Rejection Feedback
        $(document).on('click', '.taa-js-view-feedback', function(e) {
            e.preventDefault();
            var note = $(this).data('note');
            var imgUrl = $(this).data('img');
            openViewer(imgUrl, note ? 'Note: ' + note : 'Rejection Feedback');
        });

        // 2. Marketing View (New Feature)
        $(document).on('click', '.taa-js-view-marketing', function(e) {
            e.preventDefault();
            var imgUrl = $(this).data('img');
            // Add cache buster to ensure latest image if overwritten
            if(imgUrl.indexOf('?') === -1) imgUrl += '?t=' + new Date().getTime();
            openViewer(imgUrl, 'Published Marketing Image');
        });
    });
})(jQuery);