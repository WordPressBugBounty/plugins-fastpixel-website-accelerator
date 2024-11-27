document.addEventListener("DOMContentLoaded", function() {
    function get_popup() {
        const container = jQuery('<span class="fastpixel-deactivate-form" id="fastpixel-deactivate-form" style="display: inline;"></span>'),
        head = jQuery('<div class="fastpixel-deactivate-form-head"><strong>' + fastpixel_popup['translations']['title'] + '</strong></div>'),
        body = jQuery('<div class="fastpixel-deactivate-form-body"></div>'),
        options = jQuery('<div class="fastpixel-deactivate-options"><p><strong>' + fastpixel_popup['translations']['main_text'] + '</strong></p></div>'),
        footer = jQuery('<div class="fastpixel-deactivate-form-footer"><div class="fastpixel-column">'+
            '<div id="fastpixel-deactivation-send-anonymous-container"><input type="checkbox" name="fastpixel-deactivation-send-anonymous" checked="checked" id="fastpixel-deactivation-send-anonymous" value="1"> '+
            '<label id="fastpixel-deactivation-send-anonymous-label" for="fastpixel-deactivation-send-anonymous">' + fastpixel_popup['translations']['send_anonymous'] + '</label></div></div><div class="fastpixel-column">'+
            '<p class="fastpixel-deactivating-spinner"><span class="spinner"></span> ' + fastpixel_popup['translations']['submitting_form'] + '</p>'+
            '<a id="fastpixel-deactivate-submit-form" class="button button-primary button-large" href="javascript:void(0);">' + fastpixel_popup['translations']['btn_deactivate'] + '</a></div></div>'),
        delete_files = '<div class="fastpixel-deactivation-delete-files-container">'+
            '<input type="checkbox" name="fastpixel-deactivation-delete-files" id="fastpixel-deactivation-delete-files" value="1"> '+
            '<label for="fastpixel-deactivation-delete-files">' + fastpixel_popup['translations']['delete_cached_files'] + '</label><br></div>',
        options_p = jQuery('<p></p>');
        jQuery.each(fastpixel_popup['options'], function(index, element) {
            options_p.append(jQuery('<input type="radio" name="fastpixel-deactivate-reason" id="' + index + '" value="' + index + '" data-display-textarea="' + element['display_textarea'] + '"><label for="' + index + '">' + element['text'] + '</label></br>'));
        });
        options.append(options_p);
        options.append('<p class="fastpixel-deactivation-details-container"><label id="fastpixel-deactivate-details-label" for="fastpixel-deactivate-reasons"><strong id="fastpixel-deactivate-textarea-label"></strong></label>' +
            '<textarea name="fastpixel-deactivate-details" id="fastpixel-deactivate-details" rows="2" style="width:100%"></textarea></p>');
        body.append(options).append('<hr>').append(delete_files).append('<hr>');;
        return container.append(head).append(body).append(footer);
    }
    const deactivate_link = jQuery(fastpixel_popup['deactivate_link_id']),
    form_container = jQuery(fastpixel_popup['form_container']);

    form_container.html(get_popup());

    //click on deactivate link
    jQuery(deactivate_link).on("click", function (e) {
        e.preventDefault();
        jQuery('body').toggleClass('fastpixel-deactivate-form-active');
        form_container.fadeIn({
            complete: function () {
                var offset = form_container.offset();
                jQuery('html').animate({ scrollTop: Math.max(0, offset.top - (jQuery('#fastpixel-deactivate-form').height() + 70) )});
            }
        });
    });
    //click on radio buttons
    jQuery('body').on('click', 'input[name="fastpixel-deactivate-reason"]', function () {
        jQuery('#fastpixel-deactivation-send-anonymous-container').show();
        jQuery('#fastpixel-deactivate-submit-form').text(fastpixel_popup['translations']['btn_submit_and_deactivate']);
        if (jQuery(this).data('display-textarea')) {
            jQuery('#fastpixel-deactivate-details').show();
            jQuery('#fastpixel-deactivate-textarea-label').text(fastpixel_popup['options'][jQuery(this).val()]['textarea_text']).show(); 
        } else {
            jQuery('#fastpixel-deactivate-details').hide();
            jQuery('#fastpixel-deactivate-textarea-label').hide(); 
        }
    });
    
    //click on black background
    jQuery('body').on('click', '.fastpixel-deactivate-form-bg', function () {
        form_container.fadeOut();
        jQuery('body').toggleClass('fastpixel-deactivate-form-active');
        reset_form();
    });

    jQuery('body').on('click', '#fastpixel-deactivate-submit-form', function (e) {
        e.preventDefault();
        data = {};
        data['reason'] = form_container.find('input[name="fastpixel-deactivate-reason"]:checked').val() ? form_container.find('input[name="fastpixel-deactivate-reason"]:checked').val() : null,
        data['details'] = form_container.find('#fastpixel-deactivate-details').val() ? form_container.find('#fastpixel-deactivate-details').val() : null,
        data['anonymous'] = form_container.find('#fastpixel-deactivation-send-anonymous').prop('checked') ? 1 : 0;
        data['action'] = 'fastpixel_deactivate_plugin_feedback';
        data['security'] = fastpixel_popup['nonce'];
        const delete_files = form_container.find('#fastpixel-deactivation-delete-files').prop('checked') ? true : false;
        if (data['reason']) { //checking if reason is selected
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: data,
                dataType: 'json',
                beforeSend: function () {
                    // As soon as we click, the body of the form should disappear
                    form_container.find('#fastpixel-deactivate-submit-form').fadeOut(
                        function () {
                            // Fade in spinner
                            form_container.find(".fastpixel-deactivating-spinner").fadeIn();
                        }
                    );
                },
                success: function (response) {
                    // Fade in spinner
                    setTimeout(function () {
                        form_container.find(".fastpixel-deactivating-spinner").fadeOut(
                            function () {
                                // As soon as we click, the body of the form should disappear
                                form_container.find('#fastpixel-deactivate-submit-form').fadeIn();
                            }
                        )
                    }
                    , 1000);
                },
                complete: function (response) {
                    //Always redirect to original deactivation URL
                    if (delete_files == true) {
                        window.location.href = fastpixel_popup_deactivation_links['delete_link'];
                    } else {
                        window.location.href = fastpixel_popup_deactivation_links['deactivate_link'];
                    }
                }
            });
        } else {
            // Redirect to original deactivation URL
            if (delete_files == true) {
                window.location.href = fastpixel_popup_deactivation_links['delete_link'];
            } else {
                window.location.href = fastpixel_popup_deactivation_links['deactivate_link'];
            }
        }
    });

    function reset_form() {
        jQuery('input[name="fastpixel-deactivate-reason"]:checked').prop('checked', false);
        jQuery('#fastpixel-deactivate-details').hide();
        jQuery('#fastpixel-deactivate-textarea-label').hide(); 
        jQuery('#fastpixel-deactivation-delete-files').prop('checked', false);
        jQuery('#fastpixel-deactivation-send-anonymous').prop('checked', true);
        jQuery('#fastpixel-deactivation-send-anonymous-container').hide();
        jQuery('#fastpixel-deactivation-details-container').hide();
        jQuery('#fastpixel-deactivate-submit-form').text(fastpixel_popup['translations']['btn_deactivate']);
    }
});
