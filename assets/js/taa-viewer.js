(function($) {
    'use strict';

    $(document).ready(function() {
        console.log("TAA v30.12: Viewer Module (Drag Hand Mode)");
        
        let viewerInstance = null;

        $(document).on('click', '.taa-js-view-feedback', function(e) {
            e.preventDefault();
            var note = $(this).data('note');
            var imgUrl = $(this).data('img');

            if (!imgUrl) return;

            // Create temporary image
            var image = new Image();
            image.src = imgUrl;
            image.alt = "Rejection Feedback";

            if (viewerInstance) {
                viewerInstance.destroy();
            }

            // Initialize ViewerJS
            viewerInstance = new Viewer(image, {
                hidden: function () {
                    viewerInstance.destroy();
                },
                
                // --- ZOOM & DRAG SETTINGS ---
                zoomable: true,       // Buttons (+/-) will still work
                zoomOnTouch: false,   // STRICTLY DISABLE Pinch-to-Zoom
                zoomOnWheel: false,   // STRICTLY DISABLE Mouse Wheel Zoom
                movable: true,        // ENABLE Dragging
                toggleOnDblclick: false, // DISABLE Double-tap zoom (prevents accidental zooming)
                
                // --- VISUALS ---
                tooltip: true,
                navbar: false, 
                title: function() {
                    return note ? 'Note: ' + note : 'Rejection Feedback';
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
                className: 'taa-native-viewer', // Custom class for CSS overrides
                
                // Force "Move" mode immediately when viewed
                viewed: function() {
                    // This ensures the internal logic knows we want to drag
                    viewerInstance.move(0, 0); 
                }
            });

            viewerInstance.show();
        });
    });
})(jQuery);