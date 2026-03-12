<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

$functions = FASTPIXEL_Functions::get_instance();
$api_key_model = FASTPIXEL_Api_Key::get_instance();
$adminEmail = get_bloginfo('admin_email');
$current_api_key = $functions->get_option('fastpixel_api_key', '');

$is_temp = $api_key_model->is_temp_key($current_api_key);
if ($is_temp) {
    $current_api_key = '';
}

// check if API key is editable (not defined as constant)
$is_editable = !defined('FASTPIXEL_API_KEY');
$disabled = $is_editable ? '' : 'disabled';
?>

<section id="tab-onboarding" class="active setting-tab" data-part="onboarding">
    <h1><?php esc_html_e('Welcome On Board!', 'fastpixel-website-accelerator'); ?></h1>

    <div class='onboarding-logo'>
        <img src="<?php echo esc_url(FASTPIXEL_PLUGIN_URL . 'icons/FastPixel-Happy.svg'); ?>" alt="<?php esc_attr_e('FastPixel Logo', 'fastpixel-website-accelerator'); ?>" />
    </div>

    <div class='onboarding-join-wrapper'>
        <!-- New Customer Section -->
        <settinglist class='new-customer now-active' id="new-customer-section">
            <h3><?php esc_html_e('New user?', 'fastpixel-website-accelerator'); ?></h3>
            <svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="36" cy="36" r="36" fill="#1ABDCA"/>
                <path d="M19.5 25C19.5 21.4844 21.375 18.2031 24.5 16.4062C27.5469 14.6094 31.375 14.6094 34.5 16.4062C37.5469 18.2031 39.5 21.4844 39.5 25C39.5 28.5938 37.5469 31.875 34.5 33.6719C31.375 35.4688 27.5469 35.4688 24.5 33.6719C21.375 31.875 19.5 28.5938 19.5 25ZM12 52.7344C12 45 18.1719 38.75 25.9062 38.75H33.0156C40.75 38.75 47 45 47 52.7344C47 53.9844 45.9062 55 44.6562 55H14.2656C13.0156 55 12 53.9844 12 52.7344ZM51.375 39.375V34.375H46.375C45.2812 34.375 44.5 33.5938 44.5 32.5C44.5 31.4844 45.2812 30.625 46.375 30.625H51.375V25.625C51.375 24.6094 52.1562 23.75 53.25 23.75C54.2656 23.75 55.125 24.6094 55.125 25.625V30.625H60.125C61.1406 30.625 62 31.4844 62 32.5C62 33.5938 61.1406 34.375 60.125 34.375H55.125V39.375C55.125 40.4688 54.2656 41.25 53.25 41.25C52.1562 41.25 51.375 40.4688 51.375 39.375Z" fill="white"/>
            </svg>
            <h2><?php esc_html_e('Create account', 'fastpixel-website-accelerator'); ?></h2>
            <p><?php esc_html_e('If you don\'t have an API Key, you can request one for free. Just enter your email address, agree to the terms and press Continue.', 'fastpixel-website-accelerator'); ?></p>


            <form method="POST" action="<?php echo esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings&fastpixel-action=request_new_key')) ?>" id="fastpixel-form-request-key">

                <setting>
                    <content>
                        <name for="pluginemail"><?php esc_html_e('E-mail address:', 'fastpixel-website-accelerator'); ?></name>
                        <input name="pluginemail" type="email" id="pluginemail" value="<?php echo esc_attr(sanitize_email($adminEmail)); ?>" class="regular-text" <?php echo esc_attr($disabled); ?> required />
                        <span class="spinner" id="pluginemail_spinner" style="float:none;display:none;"></span>

                        <info>
                            <p class="settings-info shortpixel-settings-error" style='display:none;' id='pluginemail-error'>
                                <b><?php esc_html_e('Please provide a valid e-mail address.', 'fastpixel-website-accelerator'); ?></b>
                            </p>
                            <p class="settings-info" id='pluginemail-info'>
                                <?php
                                if ($adminEmail) {
                                    printf(
                                        esc_html__('%s %s %s is the e-mail address in your WordPress Settings. You can use it, or change it to any valid e-mail address that you own.', 'fastpixel-website-accelerator'),
                                        '<b>',
                                        esc_html(sanitize_email($adminEmail)),
                                        '</b>'
                                    );
                                } else {
                                    esc_html_e('Please input your e-mail address and press Continue.', 'fastpixel-website-accelerator');
                                }
                                ?>
                            </p>
                            <p>
                                <label for='tos'>
                                    <span style="position:relative;">
                                        <input name="tos" type="checkbox" id="tos" value="1" required>
                                        <img class="tos-robo" alt="<?php esc_attr_e('FastPixel robot', 'fastpixel-website-accelerator'); ?>"
                                             src="<?php echo esc_url(FASTPIXEL_PLUGIN_URL . 'icons/FastPixel-Happy.svg'); ?>" style="position: absolute;left: -120px;bottom: -36px;display:none;width: 80px;height: 80px;">
                                        <img class="tos-hand" alt="<?php esc_attr_e('Hand pointing', 'fastpixel-website-accelerator'); ?>"
                                             src="<?php echo esc_url(FASTPIXEL_PLUGIN_URL . 'icons/point.png'); ?>" style="position: absolute;left: -50px;bottom: -10px;display:none;">
                                    </span>
                                    <?php
                                    printf(
                                        esc_html__('I have read and I agree to the %s Terms of Service %s and the %s Privacy Policy %s (%s GDPR compliant %s).', 'fastpixel-website-accelerator'),
                                        '<a href="https://fastpixel.io/terms" target="_blank">',
                                        '</a>',
                                        '<a href="https://fastpixel.io/privacy" target="_blank">',
                                        '</a>',
                                        '<a href="https://fastpixel.io/privacy#gdpr" target="_blank">',
                                        '</a>'
                                    );
                                    ?>
                                </label>
                            </p>
                        </info>
                    </content>
                </setting>
            </form>
        </settinglist>

        <!-- Existing Customer Section -->
        <settinglist class='existing-customer'>
            <h3><?php esc_html_e('Already have an account?', 'fastpixel-website-accelerator'); ?></h3>
            <svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="36" cy="36" r="36" fill="#1ABDCA"/>
                <path d="M43.25 42.5C41.7656 42.5 40.3594 42.3438 39.0312 41.875L36.4531 44.4531C36.0625 44.8438 35.5938 45 35.125 45H32V48.125C32 49.2188 31.1406 50 30.125 50H27V53.125C27 54.2188 26.1406 55 25.125 55H18.875C17.7812 55 17 54.2188 17 53.125V46.875C17 46.4062 17.1562 45.9375 17.5469 45.5469L30.125 32.9688C29.6562 31.6406 29.5 30.2344 29.5 28.75C29.5 21.1719 35.5938 15 43.25 15C50.8281 15 57 21.1719 57 28.75C57 36.4062 50.8281 42.5 43.25 42.5ZM46.375 22.5C45.2031 22.5 44.1875 23.125 43.6406 24.0625C43.0938 25.0781 43.0938 26.25 43.6406 27.1875C44.1875 28.2031 45.2031 28.75 46.375 28.75C47.4688 28.75 48.4844 28.2031 49.0312 27.1875C49.5781 26.25 49.5781 25.0781 49.0312 24.0625C48.4844 23.125 47.4688 22.5 46.375 22.5Z" fill="white"/>
            </svg>
            <h2><?php esc_html_e('Login', 'fastpixel-website-accelerator'); ?></h2>
            <p><?php esc_html_e('Welcome back! If you already have an API Key please input it below and press Continue.', 'fastpixel-website-accelerator'); ?></p>

            <form method="POST" action="<?php echo esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings&fastpixel-action=validate_key')) ?>" id="fastpixel-form-validate-key">
            <!--            <form method="POST" action="--><?php //echo esc_url(add_query_arg(array('noheader' => 'true', 'fastpixel-action' => 'action_validate_key'))) ?><!--" id="fastpixel-form-validate-key">-->
                <setting>
                    <content>
                        <name><?php esc_html_e('API Key:', 'fastpixel-website-accelerator'); ?></name>
                        <input name="login_apiKey" type="text" id="new-key" value="<?php echo esc_attr($current_api_key); ?>" class="regular-text" <?php echo esc_attr($disabled); ?> required>
                        <input type="hidden" name="validate" id="valid" value="validate"/>
                        <span class="spinner" id="validate_spinner" style="float:none;display:none;"></span>
                    </content>
                </setting>
            </form>
            <div class="onboarding-domain-status" style="display:none; margin:5px 0 10px 0;"></div>
        </settinglist>
    </div>

    <div class='submit-errors'></div>

    <?php wp_nonce_field('fastpixel-onboarding', 'fastpixel-nonce', false); ?>

    <settinglist class='onboard-submit'>
        <button type="button" name="add-key" id="fastpixel-onboard-continue"><?php esc_html_e('Continue', 'fastpixel-website-accelerator'); ?></button>
        <button type="button" name="skip-onboarding" id="fastpixel-onboard-skip" class="fastpixel-onboard-link"><?php esc_html_e('Remind me later', 'fastpixel-website-accelerator'); ?></button>
    </settinglist>
</section>

