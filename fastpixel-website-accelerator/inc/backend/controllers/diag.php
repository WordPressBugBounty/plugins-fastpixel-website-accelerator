<?php
namespace FASTPIXEL;

use Exception;
defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag')) {
    class FASTPIXEL_Diag 
    {
        protected $tests = [];
        protected $tests_results = [];
        protected $notification_messages = [];
        protected $have_failed_tests = false;
        public static $instance;

        public function __construct()
        {
            self::$instance = $this;
            $this->load_tests_models();

            //running tests on admin_init hook to have all plugins loaded
            add_action('admin_init', [$this, 'run_tests'], 1);

            add_action('wp_ajax_fastpixel_deactivate_plugin', [$this, 'ajax_deactivate_plugin'], 0);
            add_action('deactivated_plugin', [$this, 'deactivated_plugin_hook'], 10, 2);

            add_action('admin_init', function () {
                if (isset($_REQUEST['fastpixel-nonce']) && wp_verify_nonce(sanitize_key($_REQUEST['fastpixel-nonce']), 'fastpixel-autofixendpoint')) {
                    if (isset($_GET['fastpixel_diag_action']) && sanitize_key($_GET['fastpixel_diag_action']) == 'autofixendpoint') {
                        $cache = FASTPIXEL_Cache::get_instance();
                        $cache->check_endpoints();
                        \wp_redirect(esc_url(\wp_get_referer()));
                        exit;
                    }
                } elseif (isset($_GET['fastpixel_diag_action']) && sanitize_key($_GET['fastpixel_diag_action']) == 'autofixendpoint' && 
                isset($_REQUEST['fastpixel-nonce']) && !wp_verify_nonce(sanitize_key($_REQUEST['fastpixel-nonce']), 'fastpixel-autofixendpoint')) {
                    $notices = FASTPIXEL_Notices::get_instance();
                    $notices->add_flash_notice(esc_html__('Automatic endpoint update failed, wrong nonce.', 'fastpixel-website-accelerator'), 'error', true);
                }
            });
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Diag();
            }
            return self::$instance;
        }

        protected function load_tests_models()
        {
            if (file_exists(FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/diag-tests') && is_dir(FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/diag-tests')) {
                if ($handle = opendir(FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/diag-tests')) {
                    while (false !== ($entry = readdir($handle))) {
                        if (!in_array($entry, ['.', '..'])) {
                            try {
                                include_once FASTPIXEL_PLUGIN_DIR . '/inc/backend/models/diag-tests/' . $entry;
                            } catch (Exception $e) {
                                FASTPIXEL_DEBUG::log('Exception message -> ', $e->getMessage());
                            }
                        }
                    }
                    closedir($handle);
                }
            }
            usort(
                $this->tests,
                function ($a, $b) {
                    return $a->get_order_id() > $b->get_order_id() ? 1 : ($a->get_order_id() == $b->get_order_id()? 0 : -1);
                }
            );
        }

        public function add_test_model(FASTPIXEL_Diag_Test $model)
        {
            $this->tests[] = $model;
        }

        public function run_tests()
        {
            foreach ($this->tests as $test) {
                $info = $test->get_information();
                if ($test->display_on_diag_page()) {
                    $this->tests_results[] = $info;
                    if ($info['status'] == false) {
                        $this->have_failed_tests = true;
                    }
                }
                if (!empty($info['notification_messages'])) {
                    $this->notification_messages = array_merge($this->notification_messages, $info['notification_messages']);
                }
            }
        }

        public function run_activation_tests()
        {
            foreach ($this->tests as $test) {
                if (!$test->activation_test()) {
                    return false;
                }
            }
            return true;
        }

        public function get_tests_results() {
            return $this->tests_results;
        }

        public function get_notification_messages()
        {
            return $this->notification_messages;
        }

        //ajax functions that deactivate plugin from diag page
        public function ajax_deactivate_plugin() {
            check_ajax_referer('fastpixel_deactivate_plugin', 'security');
            global $fastpixel_plugin, $fastpixel_plugin_deactivated;
            $fastpixel_plugin = sanitize_text_field($_POST['plugin_file']);
            $fastpixel_plugin_deactivated = false;
            deactivate_plugins($fastpixel_plugin, false, is_multisite());
            if ($fastpixel_plugin_deactivated) {
                wp_send_json_success(['deactivated' => true]);
            } else {
                wp_send_json_success(['deactivated' => false]);
            }
        }

        public function deactivated_plugin_hook($plugin, $network_plugins) {
            global $fastpixel_plugin, $fastpixel_plugin_deactivated; 
            if (!empty($fastpixel_plugin) && $plugin == $fastpixel_plugin) {
                $fastpixel_plugin_deactivated = true;
            }
        }

        public function have_failed_tests() {
            return $this->have_failed_tests;
        }
    }

    new FASTPIXEL_Diag();
}
