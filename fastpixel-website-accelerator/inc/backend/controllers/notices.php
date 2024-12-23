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

        public function add_flash_notice($notice = "", $type = "warning", $dismissible = true, $id = null)
        {
            // Here we return the notices saved on our option, if there are not notices, then an empty array is returned
            $notices = $this->functions->get_option("fastpixel_flash_notices", []);
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
            $notices = $this->functions->get_option("fastpixel_flash_notices", array());
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
            if (!empty($dismissible)) {
                $this->functions->update_option("fastpixel_flash_notices", $dismissible);
            } else {
                $this->functions->delete_option("fastpixel_flash_notices");
            }
        }

        public function check_diag_tests()
        {
            // getting notification messages and displaying them
            $diag = FASTPIXEL_Diag::get_instance();
            $notifications = $diag->get_notification_messages();
            if (is_array($notifications) && !empty($notifications)) {
                foreach($notifications as $message) {
                    $this->add_flash_notice($message['text'], $message['type'], false);
                }
            }
        }
        public function enqueue_scripts() {
            wp_enqueue_script('fastpixel-notices', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/notices.js', ['jquery'], FASTPIXEL_VERSION, true);
        }

        public function dismiss_notice() {
            $notice_id = filter_input(INPUT_POST, 'notice_id', FILTER_SANITIZE_STRING);
            $notices = $this->functions->get_option("fastpixel_flash_notices", []);
            $notices = array_filter($notices, function($notice) use ($notice_id) {
                return $notice['id'] !== $notice_id;
            });
            $this->functions->update_option("fastpixel_flash_notices", $notices);
            wp_send_json_success();
        }
    }
    new FASTPIXEL_Notices();
}
