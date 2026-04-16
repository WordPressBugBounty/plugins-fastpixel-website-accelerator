document.addEventListener("DOMContentLoaded", function() {
    function get_popup() {
        const container = jQuery('<span class="fastpixel-deactivate-form" id="fastpixel-deactivate-form"></span>'),
        head = jQuery(
            '<div class="fastpixel-deactivate-form-head">' +
                '<button type="button" class="fastpixel-deactivate-form-close" aria-label="Close"></button>' +
                '<div class="fastpixel-deactivate-form-title-wrap">' +
                    '<img class="fastpixel-deactivate-form-icon" src="' + fastpixel_popup['icon_url'] + '" alt="">' +
                    '<span class="fastpixel-deactivate-form-heading">' + fastpixel_popup['translations']['title'] + '</span>' +
                '</div>' +
                '<p class="fastpixel-deactivate-form-intro">' + fastpixel_popup['translations']['main_text'] + '</p>' +
            '</div>'
        ),
        body = jQuery('<div class="fastpixel-deactivate-form-body"></div>'),
        options = jQuery('<div class="fastpixel-deactivate-options"></div>'),
        footer = jQuery('<div class="fastpixel-deactivate-form-footer"><div class="fastpixel-column">'+
            '<div id="fastpixel-deactivation-send-anonymous-container" class="fastpixel-deactivation-option-row fastpixel-deactivation-send-anonymous-row"><input type="checkbox" name="fastpixel-deactivation-send-anonymous" id="fastpixel-deactivation-send-anonymous" value="1"> '+
            '<label id="fastpixel-deactivation-send-anonymous-label" for="fastpixel-deactivation-send-anonymous">' + fastpixel_popup['translations']['send_anonymous'] + '</label></div></div><div class="fastpixel-column">'+
            '<p class="fastpixel-deactivating-spinner"><span class="spinner"></span> ' + fastpixel_popup['translations']['submitting_form'] + '</p>'+
            '<a id="fastpixel-deactivate-submit-form" class="button button-primary button-large" href="javascript:void(0);">' + fastpixel_popup['translations']['btn_deactivate'] + '</a></div></div>'),
        deactivation_options = '<div class="fastpixel-deactivation-delete-files-container">'+
            '<div class="fastpixel-deactivation-option-row"><input type="checkbox" name="fastpixel-deactivation-delete-files" id="fastpixel-deactivation-delete-files" value="1"> '+
            '<label for="fastpixel-deactivation-delete-files">' + fastpixel_popup['translations']['delete_cached_files'] + '</label></div>'+
            '<div class="fastpixel-deactivation-option-row"><input type="checkbox" name="fastpixel-deactivation-delete-options" id="fastpixel-deactivation-delete-options" value="1"> '+
            '<label for="fastpixel-deactivation-delete-options">' + fastpixel_popup['translations']['delete_options'] + '</label></div></div>',
        options_list = jQuery('<div class="fastpixel-deactivate-reasons-list"></div>');
        jQuery.each(fastpixel_popup['options'], function(index, element) {
            const row = jQuery('<div class="fastpixel-deactivate-reason-row"></div>');
            row.append(jQuery('<input type="radio" name="fastpixel-deactivate-reason" id="' + index + '" value="' + index + '" data-display-textarea="' + element['display_textarea'] + '">'));
            row.append(jQuery('<label for="' + index + '">' + element['text'] + '</label>'));
            options_list.append(row);
        });
        options.append(options_list);
        const details_panel = jQuery(
            '<div class="fastpixel-deactivate-details-panel">' +
                '<div class="fastpixel-deactivation-details-container">' +
                    '<label id="fastpixel-deactivate-details-label" for="fastpixel-deactivate-details"><strong id="fastpixel-deactivate-textarea-label"></strong></label>' +
            '<textarea name="fastpixel-deactivate-details" id="fastpixel-deactivate-details" rows="3"></textarea>' +
                '</div>' +
            '</div>'
        );
        body.append(options).append(details_panel).append('<hr>').append(deactivation_options).append('<hr>');
        const scroll_hint = jQuery(
            '<button type="button" class="fastpixel-deactivate-scroll-hint" aria-label="Scroll down" title="Scroll down">' +
                '<span class="fastpixel-deactivate-scroll-mouse"></span>' +
                '<span class="fastpixel-deactivate-scroll-wheel"></span>' +
            '</button>'
        );
        return container.append(head).append(body).append(footer).append(scroll_hint);
    }

    function get_deactivate_form_el() {
        return document.getElementById('fastpixel-deactivate-form');
    }

    let deactivateScrollHintDismissed = false;

    function resetDeactivateScrollHintState() {
        deactivateScrollHintDismissed = false;
    }

    function updateDeactivateScrollHint() {
        const el = get_deactivate_form_el();
        if (!el) {
            return;
        }
        const hint = el.querySelector('.fastpixel-deactivate-scroll-hint');
        if (!hint) {
            return;
        }
        const hasOverflow = el.scrollHeight > el.clientHeight + 2;
        const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 12;
        const hide = !hasOverflow || atBottom || deactivateScrollHintDismissed;
        hint.classList.toggle('hidden', hide);
    }

    function onDeactivateFormScroll() {
        const el = get_deactivate_form_el();
        if (el && el.scrollHeight > el.clientHeight + 2 && el.scrollTop > 0) {
            deactivateScrollHintDismissed = true;
        }
        updateDeactivateScrollHint();
    }

    function get_deactivation_url(delete_files, delete_options) {
        const url = new URL(fastpixel_popup_deactivation_links['deactivate_link']);

        if (delete_files) {
            url.searchParams.set('fastpixel-delete-cached-files', '1');
        }
        if (delete_options) {
            url.searchParams.set('fastpixel-delete-options', '1');
        }

        return url.toString();
    }

    function syncReasonDetailsUi(reasonKey) {
        const opt = reasonKey && fastpixel_popup['options'][reasonKey];
        const detailsPanel = form_container.find('.fastpixel-deactivate-details-panel');
        if (opt && opt['display_textarea']) {
            detailsPanel.show();
            form_container.find('#fastpixel-deactivate-details').show();
            form_container.find('#fastpixel-deactivate-textarea-label').text(opt['textarea_text']).show();
        } else {
            detailsPanel.hide();
            form_container.find('#fastpixel-deactivate-details').hide();
            form_container.find('#fastpixel-deactivate-textarea-label').hide();
        }
        setTimeout(updateDeactivateScrollHint, 0);
    }

    const deactivate_link = jQuery(fastpixel_popup['deactivate_link_id']),
    form_container = jQuery(fastpixel_popup['form_container']);

    form_container.html(get_popup());
    /* wrapper injected next to the  link inside a <td>; fixed + overflow on tables clips the modal plus monut on body. */
    if (form_container.length && form_container.parent()[0] !== document.body) {
        form_container.appendTo(document.body);
    }
    form_container.find('#fastpixel-deactivate-form').on('scroll', onDeactivateFormScroll);
    jQuery(document).on('input', '#fastpixel-deactivate-details', updateDeactivateScrollHint);
    syncReasonDetailsUi(null);

    //click on deactivate link
    jQuery(deactivate_link).on("click", function (e) {
        e.preventDefault();
        jQuery('body').toggleClass('fastpixel-deactivate-form-active');
        form_container.fadeIn();
        resetDeactivateScrollHintState();
        const scrollEl = get_deactivate_form_el();
        if (scrollEl) {
            scrollEl.scrollTop = 0;
        }
        syncReasonDetailsUi(null);
        setTimeout(updateDeactivateScrollHint, 50);
        setTimeout(updateDeactivateScrollHint, 350);
    });
    //click on radio buttons
    jQuery('body').on('change', 'input[name="fastpixel-deactivate-reason"]', function () {
        syncReasonDetailsUi(jQuery(this).val());
    });

    jQuery(window).on('resize', updateDeactivateScrollHint);

    jQuery(document).on('click', '.fastpixel-deactivate-scroll-hint', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const el = get_deactivate_form_el();
        if (el) {
            const room = el.scrollHeight - el.clientHeight - el.scrollTop;
            el.scrollBy({ top: Math.min(140, Math.max(0, room)), behavior: 'smooth' });
        }
    });

    // click close button
    jQuery('body').on('click', '.fastpixel-deactivate-form-close', function () {
        form_container.fadeOut();
        jQuery('body').removeClass('fastpixel-deactivate-form-active');
        reset_form();
    });
    
    //click on black background
    jQuery('body').on('click', '.fastpixel-deactivate-form-bg', function () {
        form_container.fadeOut();
        jQuery('body').removeClass('fastpixel-deactivate-form-active');
        reset_form();
    });

    jQuery('body').on('click', '#fastpixel-deactivate-submit-form', function (e) {
        e.preventDefault();
        let data = {};
        const selectedReason = form_container.find('input[name="fastpixel-deactivate-reason"]:checked').val() || null;
        const detailsText = form_container.find('#fastpixel-deactivate-details').val() || '';
        const selectedOpt = selectedReason && fastpixel_popup['options'][selectedReason];

        data['reason'] = form_container.find('input[name="fastpixel-deactivate-reason"]:checked').val() ? form_container.find('input[name="fastpixel-deactivate-reason"]:checked').val() : null,
        data['details'] = (selectedOpt && selectedOpt['display_textarea']) ? (detailsText || null) : null,
        data['anonymous'] = form_container.find('#fastpixel-deactivation-send-anonymous').prop('checked') ? 1 : 0;
        data['action'] = 'fastpixel_deactivate_plugin_feedback';
        data['security'] = fastpixel_popup['nonce'];
        const delete_files = form_container.find('#fastpixel-deactivation-delete-files').prop('checked') ? true : false;
        const delete_options = form_container.find('#fastpixel-deactivation-delete-options').prop('checked') ? true : false;
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
                    window.location.href = get_deactivation_url(delete_files, delete_options);
                }
            });
        } else {
            // Redirect to original deactivation URL
            window.location.href = get_deactivation_url(delete_files, delete_options);
        }
    });

    function reset_form() {
        jQuery('input[name="fastpixel-deactivate-reason"]:checked').prop('checked', false);
        jQuery('.fastpixel-deactivate-details-panel').hide();
        jQuery('#fastpixel-deactivate-details').hide();
        jQuery('#fastpixel-deactivate-textarea-label').hide();
        jQuery('#fastpixel-deactivate-details').val('');
        jQuery('#fastpixel-deactivation-delete-files').prop('checked', false);
        jQuery('#fastpixel-deactivation-delete-options').prop('checked', false);
        jQuery('#fastpixel-deactivation-send-anonymous').prop('checked', false);
        jQuery('#fastpixel-deactivate-submit-form').text(fastpixel_popup['translations']['btn_deactivate']);
        resetDeactivateScrollHintState();
        const scrollEl = get_deactivate_form_el();
        if (scrollEl) {
            scrollEl.scrollTop = 0;
        }
        updateDeactivateScrollHint();
    }
});
