document.addEventListener("DOMContentLoaded", function() {
    let h;
    jQuery("#fastpixel-tabs").tabs({
        create: function (e, ui) {
            h = "#" + ui.panel.attr("id"); 
        },
        activate: function (e, ui) {
            h = "#" + ui.newPanel.attr("id");
            window.history.pushState(null, null, h);
            const mobile_menu = jQuery('.fastpixel-mobile-header-menu');
            if (mobile_menu.hasClass('opened')) {
                mobile_menu.removeClass('opened').addClass('closed');
                jQuery('article.fastpixel-settings menu').removeClass('opened').addClass('closed');
            }
        }
    });
    function fastpixelOnHashChange() {
        let hash = window.location.hash.replace('#', '');
        let index = jQuery('#fastpixel-tabs').find('li[data-slug="' + hash + '"').index();
        if (index > -1) {
            jQuery("#fastpixel-tabs").tabs("option", "active", index);
        }
    }
    window.addEventListener("hashchange", fastpixelOnHashChange, false);

    function fastpixelOnOptimizationChange(disable = true) {
        if (disable) {
            jQuery('[data-depends-on="fastpixel-javascript-optimization"]').attr('disabled', 'disabled');
        } else {
            jQuery('[data-depends-on="fastpixel-javascript-optimization"]').removeAttr('disabled');
        }
    }

    //adding custom event to have ability to trigger it programmatically and avoid loop triggering
    jQuery('#fastpixel_javascript_optimization').on('fastpixelChange', function () {
        const value = jQuery(this).val(); const disable = value == 3 ? true : false; fastpixelOnOptimizationChange(disable);
    });
    jQuery('#fastpixel_javascript_optimization').on('change', function () {
        jQuery(this).trigger('fastpixelChange');
    });
    jQuery('.fastpixel-horizontal-selector input').on('change', function($) {
        const value = jQuery(this).val();
        jQuery(this).parents('.fastpixel-horizontal-selector').find('.fastpixel-horizontal-selector-settings-description').addClass('fastpixel-desc-hidden'); 
        jQuery(this).parents('.fastpixel-horizontal-selector').find('.fastpixel-horizontal-selector-settings-description[data-value="'+value+'"]').removeClass('fastpixel-desc-hidden'); 
    });
    if (jQuery('.fastpixel-horizontal-selector input').length > 0) {
        jQuery('.fastpixel-horizontal-selector input:checked').trigger('change');
    }
    
    //status page
    jQuery(function($) {
        var moveLeft = -400;
        var moveDown = 0;
        $('.fastpixel-website-accelerator-wrap').on('mouseenter', 'span.have-popup', function(e) {
            if (jQuery(this).hasClass('queued') || jQuery(this).hasClass('invalidated')) {
                jQuery(this).parent().next('.pop-up').show();
            } else {
                jQuery(this).next('.pop-up').show();
            }
        });
        $('.fastpixel-website-accelerator-wrap').on('mouseleave', 'span.have-popup', function() {
            if (jQuery(this).hasClass('queued') || jQuery(this).hasClass('invalidated')) {
                jQuery(this).parent().next('.pop-up').hide();
            } else {
                jQuery(this).next('.pop-up').hide();
            }
        });
        $('.fastpixel-website-accelerator-wrap').on('mousemove', 'span.have-popup', function (e) {
            jQuery(this).next('.pop-up').css('top', e.pageY + moveDown).css('left', e.pageX + moveLeft);
        });
    });

    //global variables
    let fastpixel_cache_request_in_progress = false;
    let fastpixel_delete_cached_request_in_progress = false;

    //ajax cache reset
    jQuery('.fastpixel-website-accelerator-wrap').on('click', 'a.fastpixel-purge-single', function (e) {
        e.preventDefault();
        const id = jQuery(this).data('id');
        if (id) {
            fastpixelRequestCache(id, fastpixel_backend_status.type, fastpixel_backend_status.selected_of_type);
        }
    });
    //ajax cached files deletion
    jQuery('.fastpixel-website-accelerator-wrap').on('click', 'a.fastpixel-delete-cached-files-single', function (e) {
        e.preventDefault();
        const id = jQuery(this).data('id');
        if (id) {
            fastpixelRequestDeleteCached(id, fastpixel_backend_status.type, fastpixel_backend_status.selected_of_type);
        }
    });

    function fastpixelRequestCache(id, type, selected_of_type) {
        if (!id || !type || !selected_of_type || fastpixel_cache_request_in_progress) {
            return false;
        }
        const data = {
            action: 'fastpixel_purge_cache',
            nonce: fastpixel_backend.nonce,
            id: id,
            type: type,
            selected_of_type: selected_of_type,
            extra_params: fastpixel_backend_status.extra_params
        };
        let original;

        jQuery.ajax({
            url: fastpixel_backend.ajax_url,
            method: 'POST',
            dataType: 'JSON',
            data: data,
            beforeSend: function () {
                fastpixel_cache_request_in_progress = true;
                original = updatePostRow({id: id, display_loader: true});
            },
            success: function (response) {
                if (response.status == 'success' && !jQuery.isEmptyObject(response.item)) {
                    const row = { ...{ id: id }, ...response.item};
                    updatePostRow(row);
                } else {
                    updatePostRow({ ...{ id: id, display_loader: false }, ...original});
                }
                fastpixelDisplayMessage(response.statusText, response.status);
            },
            error: function (err) {
                updatePostRow({ ...{ id: id, display_loader: false }, ...original });
                fastpixelDisplayMessage(err.statusText);
            },
            complete: function () {
                fastpixel_cache_request_in_progress = false;
            }
        });
    }

    function fastpixelRequestDeleteCached(id, type, selected_of_type) {
        if (!id || !type || !selected_of_type || fastpixel_delete_cached_request_in_progress) {
            return false;
        }
        const data = {
            action: 'fastpixel_delete_cached_files',
            nonce: fastpixel_backend.nonce,
            id: id,
            type: type,
            selected_of_type: selected_of_type,
            extra_params: fastpixel_backend_status.extra_params
        };
        let original;

        jQuery.ajax({
            url: fastpixel_backend.ajax_url,
            method: 'POST',
            dataType: 'JSON',
            data: data,
            beforeSend: function () {
                fastpixel_delete_cached_request_in_progress = true;
                original = updatePostRow({ id: id, display_loader: true });
            },
            success: function (response) {
                if (response.status == 'success' && !jQuery.isEmptyObject(response.item)) {
                    const row = { ...{ id: id }, ...response.item };
                    updatePostRow(row);
                } else {
                    updatePostRow({ ...{ id: id, display_loader: false }, ...original });
                }
                fastpixelDisplayMessage(response.statusText, response.status);
            },
            error: function (err) {
                updatePostRow({ ...{ id: id, display_loader: false }, ...original });
                fastpixelDisplayMessage(err.statusText);
            },
            complete: function () {
                fastpixel_delete_cached_request_in_progress = false;
            }
        });
    }

    function fastpixelCacheStatuses(ids, type, selected_of_type) {
        if (!ids || !Array.isArray(ids)) {
            return false;
        }
        const data = {
            action: 'fastpixel_cache_statuses',
            nonce: fastpixel_backend.nonce,
            ids: ids,
            type: type,
            selected_of_type: selected_of_type,
            extra_params: fastpixel_backend_status.extra_params
        };

        jQuery.ajax({
            url: fastpixel_backend.ajax_url,
            method: 'POST',
            dataType: 'JSON',
            data: data,
            success: function (response) {
                if (response.status == 'success' && !jQuery.isEmptyObject(response.items)) {
                    jQuery.each(response.items, function(id, data) {
                        const row = {...{ id: id }, ...data};
                        updatePostRow(row);
                    });
                } else {
                    fastpixelDisplayMessage(response.statusText, response.status);
                }
            },
            error: function (err) {
                fastpixelDisplayMessage(err.statusText);
            }
        });
        return true;
    }
    
    //checking cache status 30 secs
    if (jQuery('.fastpixel-website-accelerator-wrap input[name="rid[]"').length > 0) {
        setInterval(function() {
            let ids = jQuery('input[name="rid[]"').map(function() { return jQuery(this).val(); }).get();
            if (ids.length > 0 && typeof(fastpixel_backend_status.type) != 'undefined') {
                let result = fastpixelCacheStatuses(ids, fastpixel_backend_status.type, fastpixel_backend_status.selected_of_type);
            }
        }, 30000);
    }

    let msgTimeout;
    function fastpixelDisplayMessage(message, type) {
        let notice_type;
        switch(type) {
            case 'warning':
                notice_type = 'notice-warning';
                break;
            case 'success':
                notice_type = 'notice-success';
                break;
            case 'error':
            default:
                notice_type = 'notice-error';
        }
        if (jQuery('#fastpixel-js-notice').length > 0) {
            jQuery('#fastpixel-js-notice').remove();
        }
        const msg = jQuery('<div id="fastpixel-js-notice" class="notice ' + notice_type + '"><p><strong>FastPixel Website Accelerator:</strong> ' + message + '</p></div>');
        jQuery('hr.wp-header-end').after(msg);
        clearTimeout(msgTimeout);
        msgTimeout = setTimeout(function () {
            if (jQuery('#fastpixel-js-notice').length > 0) {
                jQuery('#fastpixel-js-notice').fadeOut(500);
            }
        }, 3000);
    }

    function updatePostRow(data) {
        //checking input data
        if (typeof(data) !== 'object' || jQuery.isEmptyObject(data) || data == null) {
            return false;
        }
        //checking item id
        if (!data.id || (!parseInt(data.id) && data.id != 'homepage')) {
            return false;
        } else {
            data.id = data.id != 'homepage' ? parseInt(data.id) : data.id;
        }
        //default params
        let row = {
            display_loader: false,
            status_display: false,
            status: false
        };
        //merging settings
        row = {...row, ...data};

        let original = { status: '', status_display: ''};
        const id_input = jQuery('input[name="rid[]"]').filter(function () {
            return this.value == row.id;
        });
        if (id_input) {
            const tr = jQuery(id_input).parents('tr');
            original.status = id_input.data('status');
            if (tr) {
                //displaying html status
                const cache_column = jQuery(tr).children('td.cache_status');
                original.status_display = jQuery(cache_column).html();
                //displaying loader
                if (row.display_loader == true) {
                    jQuery(cache_column).fadeOut(150, function () {
                        jQuery(cache_column).html('<div class="loader"></div>').fadeIn(150);
                        id_input.data('status', 'loader');
                    });
                    return original;
                } else 
                if (row.status != original.status) {
                    //setting new status to input and displaying new html status
                    jQuery(cache_column).fadeOut(150, function () {
                        id_input.data('status', row.status);
                        jQuery(cache_column).html(row.status_display).fadeIn(150);
                    });

                    //displaying/hiding post buttons
                    const actions = jQuery(tr).children('td.url').children('.row-actions');
                    //first we need to check "cache" link
                    if (row.status == 'excluded') {
                        const prev_el = actions.find('span.purge_cache').prev();
                        actions.find('span.purge_cache').remove();
                        prev_el.html(prev_el.children('a'));
                    } else {
                        let cache_btn_text = '';
                        if (row.status == 'cached') {
                            cache_btn_text = fastpixel_backend.purge_cache_text;
                        } else {
                            cache_btn_text = fastpixel_backend.cache_now_text;
                        }
                        if (actions.find('span.purge_cache').length > 0) {
                            actions.find('span.purge_cache').children('a').text(cache_btn_text);
                        } else {
                            actions.append(jQuery('<span class="purge_cache"></span>').append(jQuery('<a class="fastpixel-purge-single" href="' + fastpixel_backend_status.purge_link + row.id + '" data-id="' + row.id + '"></a>').append(cache_btn_text)));
                            actions.find('span.purge_cache').prev().append(" | ");
                        }
                    }

                    //then we need to check "delete cached files" link
                    if (row.status == 'stale' && actions.find('span.delete_cached').length < 1) {
                        actions.find('span.purge_cache').append(" | ");
                        actions.append(jQuery('<span class="delete_cached"></span>').append(jQuery('<a class="fastpixel-delete-cached-files-single" href="' + fastpixel_backend_status.delete_cached_link + row.id + '" data-id="' + row.id + '"></a>').append(fastpixel_backend.delete_cached_files_text)));
                    } else if (row.status !== 'stale') {
                        actions.find('span.purge_cache').html(actions.find('span.purge_cache').children('a'));
                        actions.find('span.delete_cached').remove();
                    }
                }
            }
        }
    }

    //diagnostics page
    function activate_deactivate_plugin(plugin_id = 0, spinner = false, deactivate_text = false) {
        if (plugin_id == 0) {
            return false;
        }
        if (spinner) {
            jQuery('.fastpixel-website-accelerator-wrap #plugin-action-' + plugin_id + ' span.deactivate-text').after('<img class="spinner-loader" src="/wp-includes/js/tinymce/skins/lightgray/img/loader.gif" />');
        } else {
            jQuery('.fastpixel-website-accelerator-wrap #plugin-action-' + plugin_id + ' img').remove();
        }
        if (deactivate_text) {
            jQuery('.fastpixel-website-accelerator-wrap #plugin-action-' + plugin_id + ' span.deactivate-text').show();
        } else {
            jQuery('.fastpixel-website-accelerator-wrap #plugin-action-' + plugin_id + ' span.deactivate-text').hide();
        }
    }
    let dr_in_progress = false;
    jQuery('.fastpixel-website-accelerator-wrap').on('click', '.plugin-deactivation-btn', function (e) {
        e.preventDefault();
        if (dr_in_progress) {
            return;
        }
        const plugin_id = jQuery(this).data('plugin-id');
        const data = {
            action: 'fastpixel_deactivate_plugin',
            security: fastpixel_backend.deactivate_plugin_nonce,
            plugin_file: jQuery(this).data('plugin-file')
        };
        jQuery.ajax({
            type: "POST",
            url: fastpixel_backend.ajax_url,
            data: data,
            dataType: "JSON",
            beforeSend: function () {
                dr_in_progress = true;
                activate_deactivate_plugin(plugin_id, true, false);
            },
            success: function (response) {
                if (response.data.deactivated) {
                    jQuery('.fastpixel-website-accelerator-wrap #plugin-action-' + plugin_id).html('<strong class="passed">' + fastpixel_backend.deactivate_plugin_text +'</strong>');
                } else {
                    activate_deactivate_plugin(plugin_id, false, true);
                }
            },
            error: function (response) {
                activate_deactivate_plugin(plugin_id, false, true);
            },
            complete: function (xhr, status) {
                dr_in_progress = false;
            }
        });
    });


    function options_changed() {
        if (typeof (fastpixel_presets) == "undefined") {
            return false;
        }
        jQuery('.fastpixel-presets-box').removeClass('active');
        const presets = Object.keys(fastpixel_presets);
        let preset_matched = false;
        presets.forEach(function (preset_id) {
            const preset_fields = Object.keys(fastpixel_presets[preset_id]);
            let match = true;
            preset_fields.forEach(function (field_name) {
                const field = jQuery('[name=' + field_name + "]");
                let field_value;
                switch (field.get(0).type) {
                    case 'checkbox':
                        field_value = field.get(0).checked ? true : false;
                        break;
                    case 'radio':
                        if (typeof(field) == 'object') {
                            jQuery.each(field, function (i, f) {
                                const fld = jQuery(f);
                                if (fld.prop('checked') == true) {
                                    field_value = fld.val();
                                    return;
                                }
                            });
                        }
                        break;
                    default:
                        field_value = field.val();
                        break;
                }
                if (field_value != fastpixel_presets[preset_id][field_name]) {
                    match = false;
                    return;
                }
            });
            if (match) {
                preset_matched = preset_id;
                return;
            }
        });
        if (preset_matched) {
            jQuery('.fastpixel-presets-box.' + preset_matched).addClass('active');
        } 
    }

    function preset_changed(selected_preset) {
        jQuery('.fastpixel-presets-box').removeClass('active');
        jQuery('.fastpixel-presets-box.' + selected_preset).addClass('active');
        const preset_fields = Object.keys(fastpixel_presets[selected_preset]);
        preset_fields.forEach(function (field_name) {
            const field = jQuery('[name=' + field_name + ']');
            switch (field.get(0).type) {
                case 'checkbox': 
                    field.get(0).checked = fastpixel_presets[selected_preset][field_name] ? true : false;
                    break;
                case 'radio': 
                    if (typeof(field) == 'object') {
                        jQuery.each(field, function (i, f) {
                            const fld = jQuery(f);
                            if (fld.val() == fastpixel_presets[selected_preset][field_name]) {
                                fld.prop('checked', true);
                            }
                        });
                    }
                    break;
                default: 
                    field.val(fastpixel_presets[selected_preset][field_name]);
                    break;
            }
            field.trigger('fastpixelChange');
        });
        return true;
    }

    //settings presets
    if (typeof(fastpixel_preset_settings) != "undefined") {
        jQuery('.fastpixel-presets-box .apply-preset').on('click', function (e) {
            e.preventDefault();
            jQuery(this).data('preset');
            if (preset_changed(jQuery(this).data('preset'))) {
                const form = document.getElementById('fastpixel-settings-form');
                form.submit();
            }

        });

        fastpixel_preset_settings.forEach(element => {
            jQuery('#'+element).on('change', options_changed);
        });

        options_changed();
    }

    //Speculation Rules
    function fastpixelOnSpeculationRulesChange(disable = true) {
        if (disable) {
            jQuery('#fastpixel_speculation_rules-container').find('.fastpixel-fadein-options').slideUp(1000);
        } else {
            jQuery('#fastpixel_speculation_rules-container').find('.fastpixel-fadein-options').slideDown(1000);
        }
    }
    const fastpixel_speculation_rules_checkbox = jQuery('#fastpixel_speculation_rules');
    if (fastpixel_speculation_rules_checkbox.length > 0) {
        fastpixel_speculation_rules_checkbox.on('fastpixelChange', function () {
            fastpixelOnSpeculationRulesChange(jQuery(this).prop('checked') ? false : true);
        });
        fastpixel_speculation_rules_checkbox.on('change', function () {
            jQuery(this).trigger('fastpixelChange');
        });
        fastpixel_speculation_rules_checkbox.trigger('fastpixelChange');
    }

    //Params exclusions
    function fastpixelOnParamsExcludeAllChange(disable = true) {
        if (disable) {
            jQuery('[data-depends-on="fastpixel-exclude-all-params"]').attr('readonly', 'readonly');
        } else {
            jQuery('[data-depends-on="fastpixel-exclude-all-params"]').removeAttr('readonly');
        }
    }
    const fastpixel_exclude_all_params_checkbox = jQuery('#fastpixel_exclude_all_params');
    if (fastpixel_exclude_all_params_checkbox.length > 0) {
        fastpixel_exclude_all_params_checkbox.on('fastpixelChange', function () {
            fastpixelOnParamsExcludeAllChange(jQuery(this).prop('checked') ? true : false, jQuery(this).data('depends-action') == "readonly" ? false : true);
        });
        fastpixel_exclude_all_params_checkbox.on('change', function () {
            jQuery(this).trigger('fastpixelChange');
        });
        fastpixel_exclude_all_params_checkbox.trigger('fastpixelChange');
    }

    //post types exclusions
    const fastpixelPostTypesSelect = document.getElementById('fastpixel_exclude_post_types_list');
    const fastpixelExcludedPostTypesSelect = document.getElementById('fastpixel_excluded_post_types');
    const fastpixelMoveRightBtn = document.getElementById('fastpixel-exclude-post-types-move-right');
    const fastpixelMoveLeftBtn = document.getElementById('fastpixel-exclude-post-types-move-left');
    function fastpixelMoveOptions(fromSelect, toSelect) {
        const selectedOptions = Array.from(fromSelect.options).filter(opt => opt.selected);
        selectedOptions.forEach(option => {
            const exists = Array.from(toSelect.options).some(o => o.value === option.value);
            if (!exists) {
                const newOption = new Option(option.text, option.value);
                toSelect.add(newOption);
            }
            fromSelect.remove(option.index);
        });
    }
    if (fastpixelMoveRightBtn != null && fastpixelMoveLeftBtn != null) {
        fastpixelMoveRightBtn.addEventListener('click', (e) => {
            e.preventDefault();
            fastpixelMoveOptions(fastpixelPostTypesSelect, fastpixelExcludedPostTypesSelect);
        });
        fastpixelMoveLeftBtn.addEventListener('click', (e) => {
            e.preventDefault();
            fastpixelMoveOptions(fastpixelExcludedPostTypesSelect, fastpixelPostTypesSelect);
        });
    }
    const fastpixelSettingsForm = document.getElementById('fastpixel-settings-form');
    if (fastpixelSettingsForm != null) {
        fastpixelSettingsForm.addEventListener('submit', function () {
            const select = document.getElementById('fastpixel_excluded_post_types');
            const options_count = select.options.length;
            for (var i = 0; i < options_count; i++) {
                select.options[i].selected = true;
            }
        });
    }

    //mobile menu
    jQuery('.fastpixel-mobile-header-menu').on('click', function (e) {
        e.preventDefault();
        if (jQuery(this).hasClass('opened')) {
            jQuery(this).removeClass('opened').addClass('closed'); //top menu
            jQuery('article.fastpixel-settings menu').removeClass('opened').addClass('closed'); //menu
        } else {
            jQuery(this).removeClass('closed').addClass('opened'); //top menu
            jQuery('article.fastpixel-settings menu').removeClass('closed').addClass('opened'); //menu
        }
    });
});