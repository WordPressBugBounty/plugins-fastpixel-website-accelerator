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
        }

        public static function get_instance() 
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Notices();
            }
            return self::$instance;
        }

        public function add_flash_notice($notice = "", $type = "warning", $dismissible = true)
        {
            // Here we return the notices saved on our option, if there are not notices, then an empty array is returned
            $notices = $this->functions->get_option("fastpixel_flash_notices", []);
            array_push($notices, [
                "notice"      => '<strong>FastPixel Website Accelerator:</strong> ' . wp_kses($notice, [
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
                "dismissible" => $dismissible
            ]);

            $this->functions->update_option("fastpixel_flash_notices", $notices);
        }

        public function display_flash_notices()
        {
            $notices = $this->functions->get_option("fastpixel_flash_notices", array());

            foreach ($notices as $notice) {
                wpdesk_wp_notice($notice['notice'], $notice['type'], $notice['dismissible']);
            }

            // Now we reset our options to prevent notices being displayed forever.
            if (!empty($notices)) {
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
    }
    new FASTPIXEL_Notices();
}
