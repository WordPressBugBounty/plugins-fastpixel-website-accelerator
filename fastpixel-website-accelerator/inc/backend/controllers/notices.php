<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Notices')) {
    class FASTPIXEL_Notices
    {
        public static $instance;
        protected $functions;

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance(); 
            // We add our display_flash_notices function to the admin_notices
            add_action('admin_notices', [$this, 'display_flash_notices'], 12);
            add_action('admin_notices', [$this, 'check_diag_tests'], 11);
            if (is_multisite()) {
                add_action('network_admin_notices', [$this, 'display_flash_notices'], 12);
                add_action('network_admin_notices', [$this, 'check_diag_tests'], 11);
            }
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_fastpixel_dismiss_notice', [$this, 'dismiss_notice']);
        }

        public static function get_instance() 
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Notices();
            }
            return self::$instance;
        }

        protected function get_dismissed_diag_notice_ids()
        {
            if (!is_user_logged_in()) {
                return [];
            }
            $dismissed = get_user_meta(get_current_user_id(), 'fastpixel_dismissed_diag_notice_ids', true);
            return is_array($dismissed) ? $dismissed : [];
        }

        protected function add_dismissed_diag_notice_id($notice_id)
        {
            if (!is_user_logged_in() || empty($notice_id)) {
                return;
            }
            $dismissed = $this->get_dismissed_diag_notice_ids();
            if (!in_array($notice_id, $dismissed, true)) {
                $dismissed[] = $notice_id;
                update_user_meta(get_current_user_id(), 'fastpixel_dismissed_diag_notice_ids', $dismissed);
            }
        }

        protected function get_flash_notices()
        {
            $notices = $this->functions->get_option("fastpixel_flash_notices", []);
            return is_array($notices) ? $notices : [];
        }

        protected function save_flash_notices($notices)
        {
            $notices = array_values(array_filter($notices, 'is_array'));
            if (!empty($notices)) {
                $this->functions->update_option("fastpixel_flash_notices", $notices);
            } else {
                $this->functions->delete_option("fastpixel_flash_notices");
            }
        }

        protected function clear_diag_flash_notices()
        {
            $notices = $this->get_flash_notices();
            if (empty($notices)) {
                return;
            }
            $filtered_notices = array_filter($notices, function ($notice) {
                $notice_id = !empty($notice['id']) ? sanitize_key($notice['id']) : '';
                return strpos($notice_id, 'diag-') !== 0;
            });
            $this->save_flash_notices($filtered_notices);
        }

        protected function sync_dismissed_diag_notice_ids($active_notice_ids)
        {
            if (!is_user_logged_in()) {
                return;
            }
            $dismissed = $this->get_dismissed_diag_notice_ids();
            if (empty($dismissed)) {
                return;
            }
            $active_notice_ids = array_values(array_filter(array_map('sanitize_key', $active_notice_ids)));
            $updated_dismissed = array_values(array_intersect($dismissed, $active_notice_ids));
            if ($updated_dismissed === $dismissed) {
                return;
            }
            if (!empty($updated_dismissed)) {
                update_user_meta(get_current_user_id(), 'fastpixel_dismissed_diag_notice_ids', $updated_dismissed);
            } else {
                delete_user_meta(get_current_user_id(), 'fastpixel_dismissed_diag_notice_ids');
            }
        }

        public function add_flash_notice($notice = "", $type = "warning", $dismissible = true, $id = null)
        {
            // Here we return the notices saved on our option, if there are not notices, then an empty array is returned
            $notices = $this->get_flash_notices();
            $notice = [
                "notice" => '<strong>FastPixel Website Accelerator:</strong> ' . wp_kses($notice, [
                    'a' => [
                        'href'             => [],
                        'class'            => [],
                        'target'           => []
                    ],
                    'b' => [],
                    'br' => [],
                    'strong' => [
                        'class' => []
                    ]
                ]),
                "type"        => $type,
                "dismissible" => $dismissible,
            ];
            if ($id != null) {
                $notice['id'] = $id;
            }
            array_push($notices, $notice);
            $serialized = array_map('serialize', $notices);
            $unique_serialized = array_unique($serialized);
            $notices = array_map('unserialize', $unique_serialized);
            $this->functions->update_option("fastpixel_flash_notices", $notices);
        }

        public function display_flash_notices()
        {
            $notices = $this->get_flash_notices();
            $dismissible = [];
            foreach ($notices as $notice) {
                $notice_obj = wpdesk_wp_notice($notice['notice'], $notice['type'], $notice['dismissible']);
                if (!empty($notice['id'])) {
                    $notice_obj->addAttribute('data-fastpixel-notice-id', $notice['id']);
                }
                if ($notice['dismissible'] && !empty($notice['id'])) { 
                    $dismissible[] = $notice;
                }
            }
            // Now we reset our options to prevent notices being displayed forever.
            $this->save_flash_notices($dismissible);
        }

        public function check_diag_tests()
        {
            // Don't show API key missing message on onboarding page
            global $pagenow;
            $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : false;
            $is_onboarding_page = ($pagenow == 'admin.php' && $page == FASTPIXEL_TEXTDOMAIN . '-settings');
            $api_key = '';
            
            if ($is_onboarding_page) {
                $functions = FASTPIXEL_Functions::get_instance();
                $api_key = $functions->get_option('fastpixel_api_key', '');
                if (empty($api_key)) {
                    // we're on onboarding page, don't show API key missing message
                    return;
                }
            }
            
            // getting notification messages and displaying them
            $diag = FASTPIXEL_Diag::get_instance();
            $notifications = $diag->get_notification_messages();
            $active_diag_notice_ids = [];
            if (is_array($notifications) && !empty($notifications)) {
                foreach ($notifications as $message) {
                    $notice_id = !empty($message['id']) ? sanitize_key($message['id']) : null;
                    if (!empty($notice_id) && strpos($notice_id, 'diag-') === 0) {
                        $active_diag_notice_ids[] = $notice_id;
                    }
                }
            }

            $this->clear_diag_flash_notices();
            $this->sync_dismissed_diag_notice_ids($active_diag_notice_ids);

            if (is_array($notifications) && !empty($notifications)) {
                $dismissed_diag_notice_ids = $this->get_dismissed_diag_notice_ids();
                foreach($notifications as $message) {
                    // skip API key missing messages if we're on onboarding page
                    if ($is_onboarding_page && empty($api_key) && strpos($message['text'], 'API Key is missing') !== false) {
                        continue;
                    }
                    $notice_id = !empty($message['id']) ? sanitize_key($message['id']) : null;
                    $dismissible = !empty($message['dismissible']);
                    if ($dismissible && !empty($notice_id) && in_array($notice_id, $dismissed_diag_notice_ids, true)) {
                        continue;
                    }
                    $this->add_flash_notice($message['text'], $message['type'], $dismissible, $notice_id);
                }
            }
        }
        public function enqueue_scripts() {
            wp_enqueue_script('fastpixel-notices', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/notices.js', ['jquery'], FASTPIXEL_VERSION, true);
        }

        public function dismiss_notice() {
            $notice_id = filter_input(INPUT_POST, 'notice_id', FILTER_SANITIZE_STRING);
            $notice_id = !empty($notice_id) ? sanitize_key($notice_id) : '';
            $notices = $this->get_flash_notices();
            $notices = array_filter($notices, function($notice) use ($notice_id) {
                return empty($notice['id']) || $notice['id'] !== $notice_id;
            });
            $this->save_flash_notices($notices);
            if (strpos($notice_id, 'diag-') === 0) {
                $this->add_dismissed_diag_notice_id($notice_id);
            }
            wp_send_json_success();
        }
    }
    new FASTPIXEL_Notices();
}
