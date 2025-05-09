<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Plugin')) {
    class FASTPIXEL_Plugin
    {
        private $cache_config;
        protected $functions;

        public function __construct()
        {
            $this->cache_config = FASTPIXEL_Config_Model::get_instance();
            $this->functions = FASTPIXEL_Functions::get_instance();

            if (is_admin() || defined('WP_CLI')) {
                $this->admin_init();
            }
            add_action('wp_ajax_fastpixel_deactivate_plugin_feedback', [$this, 'feedback']);
        }

        public function admin_init()
        {
            //registering activate/deactivate plugin hooks
            register_activation_hook(FASTPIXEL_PLUGIN_FILE, [$this, 'activate']);
            register_deactivation_hook(FASTPIXEL_PLUGIN_FILE, [$this, 'deactivate']);
        }

        public function activate()
        {   
            if (is_multisite() && get_current_blog_id() > 1) {
                wp_die(esc_html__('Only network activation allowed', 'fastpixel-website-accelerator'));
            }
            $diag = FASTPIXEL_Diag::get_instance();
            if ($diag->run_activation_tests() && !defined('WP_CLI')) {
                $file_updated = $this->functions->update_ac_file();
                if ($file_updated && !$this->functions->get_wp_cache_status()) {
                    $this->functions->update_config_file(true);
                }
            }
            //creating cache dir if not exists to avoid later errors
            $cache_dir = rtrim(WP_CONTENT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
            //creating plugins cache dir
            if (!file_exists($cache_dir . FASTPIXEL_TEXTDOMAIN)) {
                wp_mkdir_p($cache_dir . FASTPIXEL_TEXTDOMAIN);
            }
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            //creating index.php for safety
            if (!file_exists($cache_dir . 'index.php')) {
                if (!$wp_filesystem->put_contents($cache_dir . 'index.php', '<?php'.chr(13).'//silence is golden')) {
                    FASTPIXEL_DEBUG::log('Error while putting empty index.php into cache directory');
                }
            }
            //checking if there was already installed plugin
            $api_key = $this->functions->get_option('fastpixel_api_key');
            if (empty($api_key)) {
                $api_key = FASTPIXEL_Api_Key::get_instance();
                $api_key->init_new_key();
                //automatically enable "javascript optimization", "exclude gdpr scripts"
                $default_options = [
                    'fastpixel_serve_stale'             => false,
                    'fastpixel_exclusions'              => implode("\r\n", ['/checkout', '/cart', '/my-account']),
                    'fastpixel_javascript_optimization' => 1, //for basic preset
                    'fastpixel_javascript_exclude_gdpr' => true, //for basic preset
                    'fastpixel_cache_lifetime'          => 1,
                    'fastpixel_images_optimization'     => 2, //for basic preset
                    'fastpixel_images_crop'             => true, //for basic preset
                    'fastpixel_fonts_soft'              => true, //for basic preset
                    'fastpixel_exclude_all_params'      => true,
                    'fastpixel_params_exclusions'       => implode("\r\n", []),
                    'fastpixel_speculation_rules'       => true,
                    'fastpixel_speculation_mode'        => 'prerender',
                    'fastpixel_speculation_eagerness'   => 'moderate',
                ];
                foreach($default_options as $option_name => $option_value) {
                    $this->functions->update_option($option_name, $option_value);
                    $this->cache_config->set_option($option_name, $option_value);
                }
            }
            //updating config always
            $this->cache_config->save_file();

            //running purge_all on init
            $be_cache = FASTPIXEL_Backend_Cache::get_instance();
            $be_cache->purge_all();
        }

        public function deactivate()
        {
            //checking if user want to delete cached files
            if (isset($_GET['fastpixel-action']) && sanitize_text_field($_GET['fastpixel-action']) == 'delete_cached_files') {
                //initializing filesystem
                global $wp_filesystem;
                //deleting cache dir recursively
                $wp_filesystem->rmdir($this->functions->get_cache_dir(), true);
            }

            if (file_exists($this->functions->get_ac_file_path())) {
                $res = wp_delete_file($this->functions->get_ac_file_path());
            }
            //disabling WP_CACHE on deactivate
            $this->functions->update_config_file(false);
        }

        public function feedback() {
            check_ajax_referer('fastpixel_deactivate_plugin', 'security');
            $reason = isset($_POST['reason']) && !empty($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
            $details = isset($_POST['details']) && !empty($_POST['details']) ? sanitize_text_field($_POST['details']) : '';
            $anonymous = isset($_POST['anonymous']) ? (bool) sanitize_text_field($_POST['anonymous']) : null;
            if (!empty($reason) && class_exists('FASTPIXEL\FASTPIXEL_Request')) {
                $request_data =[
                    'reason' => $reason,
                    'details' => $details,
                    'anonymous' => $anonymous
                ];
                $request = new FASTPIXEL_Request();
                $result = $request->feedback($request_data);
                echo json_encode(['status' => $result]);
                wp_die();
            }
            echo json_encode(['status' => true]);
            wp_die();
        }
    }
    new FASTPIXEL_Plugin();
}
