
jQuery(document).ready(function($) {
    var $domainStatus = $('.onboarding-domain-status');
    var domainHasAccount = false;

    /**
     * Check if current domain is already associated with a FastPixel account.
     * Uses the fastpixel_check_domain AJAX endpoint.
     */
    function checkDomainAssociation() {
        if (typeof fastpixel_onboarding === 'undefined' || !fastpixel_onboarding.ajax_url || !fastpixel_onboarding.nonce) {
            return;
        }

        $.ajax({
            url: fastpixel_onboarding.ajax_url,
            type: 'POST',
            data: {
                action: 'fastpixel_check_domain',
                nonce: fastpixel_onboarding.nonce
            },
            success: function(response) {
                if (!response || !response.success || !response.data) {
                    return;
                }

                var data = response.data;
                if (!data.has_account) {
                    // Nothing special to show when domain is not associated
                    return;
                }

                // Mark that this domain already has an account
                domainHasAccount = true;

                var domain = data.domain || '';
                var email  = data.email || '';

                var safeDomain = $('<span/>').text(domain).html();

                var message = 'Your domain ' + safeDomain + ' is already associated with an account.';
                if (email) {
                    message += ' The account email looks like ' + $('<span/>').text(email).html() + '.';
                }
                message += ' Please enter your API Key above.';

                var html = '<p class="settings-info shortpixel-settings-error" style="margin-bottom:10px;">' +
                    '<b>' + message + '</b>' +
                    '</p>';

                $domainStatus.html(html).show();
                
                // Show Skip button next to Continue
                $('#fastpixel-onboard-skip').show();

                // Disable "New user" section completely - gray it out and disable all inputs
                var $newCustomerSection = $('.new-customer');
                $newCustomerSection.addClass('domain-associated-disabled');
                
                // Disable all inputs in the new customer section
                $newCustomerSection.find('input').prop('disabled', true);
                $newCustomerSection.find('input[type="checkbox"]').prop('disabled', true);
                $newCustomerSection.find('label').css('cursor', 'not-allowed');
                $newCustomerSection.find('input').css('cursor', 'not-allowed');
                
                // Add disabled message to new customer section
                if ($newCustomerSection.find('.domain-disabled-message').length === 0) {
                    $newCustomerSection.find('h2').after('<p class="settings-info shortpixel-settings-error domain-disabled-message" style="margin:10px 0;"><b>This domain is already associated with an account. You cannot create a new account.</b></p>');
                }

                // Focus the "Existing user" card, since the domain is already associated
                $('.new-customer, .existing-customer').removeClass('now-active');
                $('.existing-customer').addClass('now-active');
            }
        });
    }

    // Run domain association check on load
    checkDomainAssociation();

    // handle "continue" button click
    $('#fastpixel-onboard-continue').on('click', function(e) {
        e.preventDefault();
        
        var $newCustomer = $('.new-customer');
        var $existingCustomer = $('.existing-customer');
        var $button = $(this);
        var $errorContainer = $('.submit-errors');
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        var originalText = $button.html();
        $button.html(originalText + ' <span class="spinner is-active" style="float:none;margin-left:5px;"></span>');
        
        // Clear previous errors
        $errorContainer.html('');
        $('#pluginemail-error').hide();
        
        // check which form is active
        if ($newCustomer.hasClass('now-active')) {
            // if domain already has an account, do not allow creating a new one
            if (domainHasAccount) {
                $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>Your domain is already associated with an existing FastPixel account. Please use your API Key in the login section on the right.</b></p>');
                // switch to existing customer card for clarity
                $('.new-customer, .existing-customer').removeClass('now-active');
                $('.existing-customer').addClass('now-active');
                $button.prop('disabled', false).html(originalText);
                return;
            }

            // new customer - validate and make AJAX request
            var email = $('#pluginemail').val();
            var tos = $('#tos').is(':checked');
            
            if (!email || !isValidEmail(email)) {
                $('#pluginemail-error').show();
                $button.prop('disabled', false).html(originalText);
                return;
            }
            
            if (!tos) {
                $('#tos').addClass('invalid');
                $('.tos-robo').fadeIn(400, function() {
                    $('.tos-hand').fadeIn();
                });
                $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>Please agree to the Terms of Service and Privacy Policy.</b></p>');
                $button.prop('disabled', false).html(originalText);
                return;
            } else {
                $('#tos').removeClass('invalid');
                $('.tos-robo').hide();
                $('.tos-hand').hide();
            }
            
            // Get nonce
            var nonce = $('#fastpixel-nonce').val();
            
            // Make AJAX request
            $.ajax({
                url: (typeof fastpixel_onboarding !== 'undefined' && fastpixel_onboarding.ajax_url) ? fastpixel_onboarding.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'fastpixel_request_new_key',
                    nonce: nonce,
                    email: email,
                    tos: tos ? '1' : '0'
                },
                success: function(response) {
                    if (response.success) {
                        // Success - redirect to settings page
                        if (response.data && response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            window.location.href = ajaxurl.replace('admin-ajax.php', 'admin.php?page=fastpixel-website-accelerator-settings');
                        }
                    } else {
                        // Error - show message
                        var errorMsg = response.data && response.data.message ? response.data.message : 'An error occurred. Please try again.';
                        $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>' + errorMsg + '</b></p>');
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>Connection error. Please try again later.</b></p>');
                    $button.prop('disabled', false).html(originalText);
                }
            });
            
        } else if ($existingCustomer.hasClass('now-active')) {
            // existing customer - validate and make AJAX request
            var apiKey = $('#new-key').val();
            
            if (!apiKey || apiKey.trim() === '') {
                $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>Please enter your API Key.</b></p>');
                $button.prop('disabled', false).html(originalText);
                return;
            }
            
            // Get nonce
            var nonce = $('#fastpixel-nonce').val();
            
            // Make AJAX request
            $.ajax({
                url: (typeof fastpixel_onboarding !== 'undefined' && fastpixel_onboarding.ajax_url) ? fastpixel_onboarding.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'fastpixel_validate_key',
                    nonce: nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        // Success - redirect to settings page
                        if (response.data && response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            var settingsPage = (typeof fastpixel_onboarding !== 'undefined' && fastpixel_onboarding.settings_page) ? fastpixel_onboarding.settings_page : 'fastpixel-website-accelerator-settings';
                            var ajaxUrl = (typeof fastpixel_onboarding !== 'undefined' && fastpixel_onboarding.ajax_url) ? fastpixel_onboarding.ajax_url : ajaxurl;
                            window.location.href = ajaxUrl.replace('admin-ajax.php', 'admin.php?page=' + settingsPage);
                        }
                    } else {
                        // Error - show message
                        var errorMsg = response.data && response.data.message ? response.data.message : 'An error occurred. Please try again.';
                        $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>' + errorMsg + '</b></p>');
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    $errorContainer.html('<p class="settings-info shortpixel-settings-error"><b>Connection error. Please try again later.</b></p>');
                    $button.prop('disabled', false).html(originalText);
                }
            });
        }
    });

    // Handle Skip button - redirect to settings page without saving API key
    $(document).on('click', '#fastpixel-onboard-skip', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        // Just redirect to settings page without saving API key
        var settingsPage = 'fastpixel-website-accelerator-settings';
        if (typeof fastpixel_onboarding !== 'undefined' && fastpixel_onboarding.settings_page) {
            settingsPage = fastpixel_onboarding.settings_page;
        }
        
        // Build clean URL - we're already on admin.php, just need to update page param
        var baseUrl = window.location.origin + window.location.pathname;
        var settingsUrl = baseUrl + '?page=' + encodeURIComponent(settingsPage) + '&skip_onboarding=1';
        
        // Use replace instead of href to avoid redirect loops
        window.location.replace(settingsUrl);
        
        return false;
    });
    
    // toggle between new and existing customer
    $('.new-customer, .existing-customer').on('click', function() {
        // Prevent switching to "New user" if domain is already associated
        if ($(this).hasClass('new-customer') && $(this).hasClass('domain-associated-disabled')) {
            return false;
        }
        
        $('.new-customer, .existing-customer').removeClass('now-active');
        $(this).addClass('now-active');
        // Clear errors when switching
        $('.submit-errors').html('');
        $('#pluginemail-error').hide();
        // Hide robot and hand when switching
        $('.tos-robo').hide();
        $('.tos-hand').hide();
        $('#tos').removeClass('invalid');
    });
    
    // Hide robot and hand when TOS checkbox is checked
    $('#tos').on('change', function() {
        if ($(this).is(':checked')) {
            $(this).removeClass('invalid');
            $('.tos-robo').hide();
            $('.tos-hand').hide();
        }
    });
    
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});

