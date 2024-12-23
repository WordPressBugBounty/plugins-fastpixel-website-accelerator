document.addEventListener("DOMContentLoaded", function() {
    function fastpixel_dismiss_notice(notice) {
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'fastpixel_dismiss_notice',
                nonce: fastpixel_backend.nonce,
                notice_id: jQuery(notice).data('fastpixel-notice-id')
            },
            success: function (response) {
                //do nothing because can't prevent default action
            }
        });
    }
    jQuery('body').on('click', '[data-fastpixel-notice-id] .notice-dismiss', function (e) {
        const notice = jQuery(this).parent();
        fastpixel_dismiss_notice(notice); 
    });
});