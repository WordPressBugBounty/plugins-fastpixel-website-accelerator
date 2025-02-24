<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_CloudFlare_Integration')) {
    class FASTPIXEL_CloudFlare_Integration
    {

        protected $debug = false;
        protected $functions;
        protected $purge_all = false;
        protected $be_functions;
        protected $errors = [];
        protected $fastpixel_cloudflare_api_token;
        protected $fastpixel_cloudflare_zone_id;

        public function __construct()
        {
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            if (is_admin()) {
                add_filter('fastpixel/integrations_tab/enabled', function ($status) { return true; }, 10, 1);
                add_action('fastpixel/integrations_tab/save_options', [$this, 'save_options']);
                add_action('fastpixel/integrations_tab/init_settings', function () {
                    $this->register_settings();
                });
            }
            $this->fastpixel_cloudflare_api_token = $this->functions->get_option('fastpixel_cloudflare_api_token');
            $this->fastpixel_cloudflare_zone_id = $this->functions->get_option('fastpixel_cloudflare_zone_id');
            if (!empty($this->fastpixel_cloudflare_api_token) && !empty($this->fastpixel_cloudflare_zone_id)) {
                add_action('fastpixel/cachefiles/saved', [$this, 'reset_single'], 10, 1);
                add_action('fastpixel/purge_all', [$this, 'reset_all'], 10, 1); 
            }
        }

        protected function register_settings()
        {
            register_setting(FASTPIXEL_TEXTDOMAIN . '-integrations', 'fastpixel_cloudflare_api_token', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN . '-integrations', 'fastpixel_cloudflare_zone_id', ['type' => 'string']);
            
            // Register a new section in the "settings" page.
            add_settings_section(
                'fastpixel_cloudflare_settings_section',
                __('Cloudflare', 'fastpixel-website-accelerator'),
                function () {
                    echo wp_kses_post('<p class="fastpixel-settings-section-description">If you are using Cloudflare on your site, we recommend filling in the details below. This allows FastPixel to work seamlessly with Cloudflare, ensuring that pages optimized by FastPixel are automatically updated on Cloudflare as well.</p>');
                },
                FASTPIXEL_TEXTDOMAIN . '-integrations',
                [
                    'before_section' => '<div class="fastpixel-cloudflare-settings-section">',
                    'after_section'  => '</div>'
                ]
            );
            
            $field_title = esc_html__('Zone ID', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_cloudflare_zone_id',
                $field_title,
                [$this, 'fastpixel_cloudflare_zone_id_callback'],
                FASTPIXEL_TEXTDOMAIN . '-integrations',
                'fastpixel_cloudflare_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );

            $field_title = esc_html__('API Token', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_cloudflare_api_token',
                $field_title,
                [$this, 'fastpixel_cloudflare_api_token_callback'],
                FASTPIXEL_TEXTDOMAIN . '-integrations',
                'fastpixel_cloudflare_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
        }

        public function fastpixel_cloudflare_api_token_callback($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $site_token_link = '<a href="https://dash.cloudflare.com/profile/api-tokens">' . esc_html__('site token', 'fastpixel-website-accelerator') . '</a>';
            $permission_link = '<a href="https://fastpixel.io/docs/using-fastpixel-with-cloudflares-api-token/">' . esc_html__('Cache Purge permission', 'fastpixel-website-accelerator') . '</a>';
            $how_to_link     = '<a href="https://fastpixel.io/docs/using-fastpixel-with-cloudflares-api-token/">' . esc_html__('How to set it up', 'fastpixel-website-accelerator') . '</a>';
            // translators: %1$s: site token link, %2$s: permission link, %3$s: how to link
            $description = sprintf(esc_html__('Enter your %1$s for authentication. This token must have %2$s ! %3$s', 'fastpixel-website-accelerator'), $site_token_link, $permission_link, $how_to_link);
            $api_token = $this->functions->get_option('fastpixel_cloudflare_api_token');
            $this->be_functions->print_input([
                'field_name'  => 'fastpixel_cloudflare_api_token',
                'field_value' => $api_token,
                'label'       => $args['label'],
                'description' => $description
            ], true);
        }

        public function fastpixel_cloudflare_zone_id_callback($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $zone_id = $this->functions->get_option('fastpixel_cloudflare_zone_id');
            $this->be_functions->print_input([
                'field_name'  => 'fastpixel_cloudflare_zone_id',
                'field_value' => $zone_id,
                'label'       => $args['label'],
                'description' => esc_html__('You can find this in your Cloudflare account in the "Overview" section for your domain.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function save_options()
        {
            if (
                sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) ||
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings'
            ) {
                return false;
            }
            $fastpixel_cloudflare_api_token = !empty($_POST['fastpixel_cloudflare_api_token']) ? sanitize_text_field($_POST['fastpixel_cloudflare_api_token']) : '';
            $this->functions->update_option('fastpixel_cloudflare_api_token', $fastpixel_cloudflare_api_token);
            $fastpixel_cloudflare_zone_id = !empty($_POST['fastpixel_cloudflare_zone_id']) ? sanitize_text_field($_POST['fastpixel_cloudflare_zone_id']) : '';
            $this->functions->update_option('fastpixel_cloudflare_zone_id', $fastpixel_cloudflare_zone_id);
        }

        public function reset_single($url)
        {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: Reset url', $url);
            }
            $data = [
                'files' => [$url]
            ];
            add_action('fastpixel/shutdown', function () use ($data) {
                $this->request($data);
            });
        }

        // TODO: check if this is required
        public function reset_all() {
            $data = [
                'purge_everything' => true
            ];
            add_action('fastpixel/shutdown', function () use ($data) {
                $this->request($data);
            });
        }

        protected function request($data) {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: Doing reset cache request to CloudFlare, on wordpress shutdown', $data);
            }
            if (empty($this->fastpixel_cloudflare_zone_id) || empty($this->fastpixel_cloudflare_api_token)) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: zone_id or api_token is empty');
                }
                return false;
            }
            $cloudflare_api_url = "https://api.cloudflare.com/client/v4/zones/{$this->fastpixel_cloudflare_zone_id}/purge_cache";
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: api url', $cloudflare_api_url);
            }

            $args = [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->fastpixel_cloudflare_api_token,
                    'Content-Type'  => 'application/json'
                ],
                'body'    => function_exists('wp_json_encode') ? wp_json_encode($data) : json_encode($data),
                'timeout' => 15,
            ];
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: CloudFlare request data', $args);
            }
            $response = wp_remote_post($cloudflare_api_url, $args);
            if (is_wp_error($response)) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: CloudFlare request error', $response->get_error_message());
                }
                return false;
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['success']) && $data['success']) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: CloudFlare cache reset success', $data);
                }
                return true;
            } else {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_CloudFlare_Integration: CloudFlare error', $data);
                }
                return false;
            }
        }
    }

    new FASTPIXEL_CloudFlare_Integration();
}
