<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;
use Exception;

if (!class_exists('FASTPIXEL\FASTPIXEL_Functions')) {
    class FASTPIXEL_Functions 
    {
        protected $debug = false;
        protected static $instance;
        protected $ac_sample;
        protected $ac_file;
        protected $wp_cache_status;
        protected $wp_config_path;
        const FASTPIXEL_CACHE_VAR_NAME = 'WP_CACHE';
        protected $match_regexp = '/^\s*?define\(\s*?[\'|"]' . self::FASTPIXEL_CACHE_VAR_NAME . '[\'|"]\s*?,\s*?(true|false)[^\)]*?\)\s*?;/im';

        public function __construct()
        {
            self::$instance = $this;
            add_action('init', [$this, 'disable_emojis']);

            //initializing required vars
            //setting config path on init - " * WP is hardcoded to look in its own directory or one directory up for wp-config.php."
            if (file_exists(ABSPATH . 'wp-config.php')) {
                $this->wp_config_path = ABSPATH . 'wp-config.php';
            } elseif (file_exists(dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'wp-config.php')) {
                $this->wp_config_path = dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'wp-config.php';
            } else {
                throw new \Exception("Can't find config file");
            }
            $this->wp_cache_status = defined(self::FASTPIXEL_CACHE_VAR_NAME) ? constant(self::FASTPIXEL_CACHE_VAR_NAME) : false;
            $this->ac_sample       = FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php';
            $this->ac_file         = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php';
        }

        public static function get_instance() 
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Functions();
            }
            return self::$instance;
        }

        public function get_cache_dir() 
        {
            return rtrim(WP_CONTENT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . FASTPIXEL_TEXTDOMAIN;
        }

        public function get_wp_config_path() {
            return $this->wp_config_path;
        }

        public function get_match_regexp()
        {
            return $this->match_regexp;
        }

        /* 
         * this function uses default php functions(unlink, rmdir, fopen, fclose, file_get_contents, file_put_contents etc...) 
         * because this function is used early in advanced-cache.php and native WP functions(WP_Filesystem) are not available
         */
        public function delete_cached_files($path)
        {
            //checking if path is a dir and it exists, return if not
            $unlink_path = $this->get_cache_dir() . DIRECTORY_SEPARATOR . rtrim($path, DIRECTORY_SEPARATOR);
            if (!file_exists($unlink_path) || 
                !is_dir($unlink_path)) {
                return false;
            }
            if ($this->debug) {
                FASTPIXEL_Debug::log('deleting cached files for $path', $path);
            }
            $files = ['index.html', 'index_local.html', 'headers.json', 'style.css', 'meta'];
            foreach ($files as $file) {
                $unlink_file = $unlink_path . DIRECTORY_SEPARATOR . $file;
                if (file_exists($unlink_file)) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- none available before WordPress is loaded.
                    @unlink($unlink_file); //phpcs:ignore
                }
            }
            //need to check for params directories
            if ($this->debug) {
                FASTPIXEL_Debug::log('deleting cached params directoris for $path', $path);
            }
            if (is_dir($unlink_path)) {
                $objects = scandir($unlink_path);
                foreach ($objects as $object) {
                    //if param directory is found
                    if (is_dir($unlink_path . DIRECTORY_SEPARATOR . $object) && preg_match('/^_/', $object)) {
                        //deleting files from directory
                        foreach($files as $file) {
                            if(file_exists($unlink_path . DIRECTORY_SEPARATOR . $object . DIRECTORY_SEPARATOR . $file)) {
                                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- none available before WordPress is loaded.
                                @unlink($unlink_path . DIRECTORY_SEPARATOR . $object . DIRECTORY_SEPARATOR . $file); //phpcs:ignore
                            }
                        }
                        //removing directories
                        if($this->dir_is_empty($unlink_path . DIRECTORY_SEPARATOR . $object)) {
                            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- none available before WordPress is loaded.
                            @rmdir($unlink_path . DIRECTORY_SEPARATOR . $object); //phpcs:ignore
                        }
                    }
                }
            }
            //removing unnecessary directories
            if ($this->dir_is_empty($unlink_path)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- none available before WordPress is loaded.
                @rmdir($unlink_path); //phpcs:ignore
            }
            return true;
        }

        /* 
         * this function uses default php functions(unlink, rmdir, fopen, fclose, opendir, closedir, readdir etc...) 
         * because they are used early in advanced-cache.php and native WP functions are not available
         */
        public function dir_is_empty($dir)
        {
            if (!file_exists($dir)) {
                return false;
            }
            $handle = opendir($dir);
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    closedir($handle);
                    return false;
                }
            }
            closedir($handle);
            return true;
        }

        /* 
         * this function uses default php functions(unlink, rmdir, fopen, fclose, file_get_contents, file_put_contents etc...) 
         * because this function is used early in advanced-cache.php and native WP functions(WP_Filesystem) are not available
         */
        public function check_post_cache_status($post_url)
        {
            if (empty($post_url)) {
                return false;
            } else {
                $url = new FASTPIXEL_Url($post_url);
            }
            $data = [
                'have_cache'               => false,
                'need_cache'               => false,
                'html_created_time'        => false,
                'local_html_created_time'  => false,
                'global_invalidation_time' => false,
                'local_invalidation_time'  => false,
                'last_cache_request_time'  => false,
                'error'                    => false,
                'error_time'               => false
            ];
            $path = $this->get_cache_dir() . DIRECTORY_SEPARATOR . rtrim($url->get_url_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
            $meta_file = $this->get_cache_dir() . DIRECTORY_SEPARATOR . rtrim($url->get_url_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'meta';
            //checking for file and its create time
            $html_created_time = false;
            if (@file_exists($path) && @is_readable($path)) {
                $data['html_created_time'] = $html_created_time = filemtime($path);
                $data['have_cache'] = true;
            }
            $local_path = $this->get_cache_dir() . DIRECTORY_SEPARATOR . rtrim($url->get_url_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index_local.html';
            if (@file_exists($local_path) && @is_readable($local_path)) {
                $data['local_html_created_time'] = filemtime($local_path);
            }

            //getting global invalidation time
            $global_invalidation_time = false;
            $global_invalidated_path = $this->get_cache_dir() . DIRECTORY_SEPARATOR . 'invalidated';
            if (@file_exists($global_invalidated_path) && @is_readable($global_invalidated_path)) {
                $data['global_invalidation_time'] = $global_invalidation_time = filemtime($global_invalidated_path);
            }
            //initializing $meta variable to avoid notices
            $meta = [
                'invalidated_time' => 0,
                'cache_request_time' => 0
            ];
            //checking for local invalidation time and cache request time
            if (@file_exists($meta_file) && @is_readable($meta_file)) {
                //can't use native WP functions because file is included early in advanced-cache.php
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- none available before WordPress is loaded.
                $meta = json_decode(file_get_contents($meta_file), true); //phpcs:ignore
                $data['local_invalidation_time'] = $meta['invalidated_time'];
                $data['last_cache_request_time'] = $meta['cache_request_time'];
            } else {
                //require cache when file is not present
                $data['need_cache'] = true;
            }

            // compare global invalidation time with local
            if ($global_invalidation_time > $meta['invalidated_time']) {
                //compare html file created time with global invalidation
                if ($html_created_time < $global_invalidation_time) {
                    $data['need_cache'] = true;
                }
            } else {
                //compare html file created time with local invalidation
                if ($html_created_time < $meta['invalidated_time']) {
                    $data['need_cache'] = true;
                }
            }

            //checking for error file
            $error_path = $this->get_cache_dir() . DIRECTORY_SEPARATOR . rtrim($url->get_url_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'error.json';
            if (@file_exists($error_path) && @is_readable($error_path)) {
                //can't use native WP functions because file is included early in advanced-cache.php
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- none available before WordPress is loaded.
                $error = file_get_contents($error_path); //phpcs:ignore
                $decoded_error = json_decode($error, true);
                if (isset($decoded_error['error']) && !empty($decoded_error['error'])) {
                    $data['error'] = $decoded_error['error'];
                    $data['error_time'] = filemtime($error_path);
                }
            }
            return $data;
        }

        /* 
         * this function uses default php functions(unlink, rmdir, fopen, fclose, file_get_contents, file_put_contents etc...) 
         * because this function is used early in advanced-cache.php and native WP functions(WP_Filesystem) are not available
         */
        public function update_post_cache($path, $invalidated = true, $requested = false) 
        {
            if (empty($path)) {
                return false;
            }
            $path = rtrim($path, DIRECTORY_SEPARATOR); //removing trailing slash
            $cache_dir = $this->get_cache_dir();
            $meta = ['invalidated_time' => false, 'cache_request_time' => false];
            if (file_exists($cache_dir . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . 'meta')) {
                //can't use native WP functions because file is included early in advanced-cache.php
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- none available before WordPress is loaded.
                $meta = array_merge($meta, json_decode(file_get_contents($cache_dir . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . 'meta'), true)); //phpcs:ignore
            }
            if ($invalidated) {
                $meta['invalidated_time'] = time();
            } 
            if ($requested) {
                $meta['cache_request_time'] = time();
            }
            if (!file_exists($cache_dir . DIRECTORY_SEPARATOR . $path)) {
                $dirs = explode(DIRECTORY_SEPARATOR, $path);
                $urls_path = $cache_dir;
                foreach ($dirs as $dir) {
                    if (!empty($dir)) {
                        $urls_path .= DIRECTORY_SEPARATOR . $dir;

                        if (!file_exists($urls_path)) {
                            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- none available before WordPress is loaded.
                            if (!@mkdir($urls_path)) { //phpcs:ignore
                                // Can not create dir!
                                return false;
                            }
                        }
                    }
                }
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- none available before WordPress is loaded.
            file_put_contents($cache_dir . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . 'meta', wp_json_encode($meta)); //phpcs:ignore
        }

        public function disable_emojis_tinymce($plugins)
        {
            if (is_array($plugins)) {
                return array_diff($plugins, array('wpemoji'));
            } else {
                return array();
            }
        }

        public function disable_emojis()
        {

            if (function_exists('print_emoji_detection_script')) {
                remove_action('wp_head', 'print_emoji_detection_script', 7);
            }
            if (function_exists('print_emoji_detection_script')) {
                remove_action('admin_print_scripts', 'print_emoji_detection_script');
            }
            if (function_exists('print_emoji_styles')) {
                remove_action('wp_print_styles', 'print_emoji_styles');
            }
            if (function_exists('print_emoji_styles')) {
                remove_action('admin_print_styles', 'print_emoji_styles');
            }
            if (function_exists('wp_staticize_emoji')) {
                remove_filter('the_content_feed', 'wp_staticize_emoji');
            }
            if (function_exists('wp_staticize_emoji')) {
                remove_filter('comment_text_rss', 'wp_staticize_emoji');
            }
            if (function_exists('wp_staticize_emoji_for_email')) {
                remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
            }
            add_filter('tiny_mce_plugins', [$this, 'disable_emojis_tinymce']);
        }

        public function update_ac_file($rest_url = false)
        {
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if (file_exists($this->ac_sample)) {
                //check for specific case when WP_HOME is defined in wp-config.php and it is set to $_SERVER['SERVER_NAME']
                if (defined('WP_HOME') && empty(preg_replace('/https?:\/\//i', '', WP_HOME)) ) {
                    return false;
                }
                if (empty($rest_url)) {
                    //here we need to check for WPML and if present then remove folder urls
                    if (defined('ICL_SITEPRESS_VERSION')) {
                        global $wp_filter;
                        if (isset($wp_filter['rest_url']) && !empty($wp_filter['rest_url'])) {
                            foreach ($wp_filter['rest_url'] as $priority => $functions) {
                                // Loop through each function attached to the hook
                                if (!empty($functions) && is_array($functions)) {
                                    foreach ($functions as $function) {
                                        // Process function information
                                        if (isset($function['function'][0]) && is_object($function['function'][0]) && strpos(get_class($function['function'][0]), 'WPML_URL_Converter') >= 0)  {
                                            //checking wpml object for its function that remove hook and calling it
                                            if (method_exists($function['function'][0], 'remove_hooks') ) { 
                                                $function['function'][0]->remove_hooks(); 
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $rest_url = get_rest_url(get_current_blog_id(), FASTPIXEL_TEXTDOMAIN . '/v1/update');
                }
                $advanced_cache_contents = $wp_filesystem->get_contents($this->ac_sample);
                $advanced_cache_contents = str_replace("%%FASTPIXEL_PLUGIN_DIR%%", FASTPIXEL_PLUGIN_DIR, $advanced_cache_contents);
                $advanced_cache_contents = str_replace("%%FASTPIXEL_TEXTDOMAIN%%", FASTPIXEL_TEXTDOMAIN, $advanced_cache_contents);
                $advanced_cache_contents = str_replace("%%FASTPIXEL_LOGGED_IN_COOKIE%%", (defined("LOGGED_IN_COOKIE") ? LOGGED_IN_COOKIE : ""), $advanced_cache_contents);
                $advanced_cache_contents = str_replace("%%FASTPIXEL_REST_URL%%", $rest_url, $advanced_cache_contents);
                $advanced_cache_contents = str_replace("%%FASTPIXEL_API_HOST%%", FASTPIXEL_API_HOST, $advanced_cache_contents);
                $advanced_cache_contents = str_replace("'%%FASTPIXEL_DEBUG%%'", (defined("FASTPIXEL_DEBUG") && FASTPIXEL_DEBUG ? 'TRUE' : 'FALSE'), $advanced_cache_contents);
                $advanced_cache_contents = str_replace("'%%FASTPIXEL_USE_SK%%'", (defined("FASTPIXEL_USE_SK") && FASTPIXEL_USE_SK ? 'TRUE' : 'FALSE'), $advanced_cache_contents);
                return $wp_filesystem->put_contents($this->ac_file, $advanced_cache_contents);
            }
            return false;
        }

        public function get_ac_file_path() 
        {
            return $this->ac_file;
        }

        public function get_config_path()
        {
            return $this->wp_config_path;
        }

        public function get_wp_cache_status() 
        {
            return $this->wp_cache_status;
        }

        public function update_config_file(bool $var_value)
        {
            if (empty($this->wp_config_path)) {
                return false;
            }
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if (!$wp_filesystem->is_file($this->wp_config_path) || !$wp_filesystem->is_writable($this->wp_config_path)) {
                return false;
            }
            $wp_config_content = $wp_filesystem->get_contents($this->wp_config_path);
            preg_match_all($this->match_regexp, $wp_config_content, $matches);
            $need_save = false;
            $var_value_str = $var_value == true ? 'true' : 'false';
            //checking if entry already exists
            if (!empty($matches[0][0]) && !empty($matches[1][0])) {
                //entry exists, checking status
                if (filter_var($matches[1][0], FILTER_VALIDATE_BOOLEAN) != $var_value) {
                    //need to update status
                    $wp_config_content_replaced = preg_replace($this->match_regexp, "define(\"" . self::FASTPIXEL_CACHE_VAR_NAME . "\", " . $var_value_str . ");", $wp_config_content);
                    $need_save = true;
                }
            } else {
                //need to add if not exist and if value equals to true
                if ($var_value == true) {
                    $wp_config_content_replaced = preg_replace('/(require_once\s*\(?\s*ABSPATH\s*\.\s*[\'|"]wp-settings.php[\'|"]\s*\)?\s*;)/i', "define(\"" . self::FASTPIXEL_CACHE_VAR_NAME . "\", " . $var_value_str . ");\r\n$1", $wp_config_content);
                    $need_save = true;
                }
            }
            if ($need_save) {
                $wp_filesystem->put_contents($this->wp_config_path, $wp_config_content_replaced);
                return true;
            }
            return false;
        }

        public function check_path($url) {
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $cache_dir = $this->get_cache_dir();
            // Make sure we can read/write files to cache dir parent
            if (!$wp_filesystem->exists(dirname($cache_dir))) {
                if (!$wp_filesystem->mkdir(dirname($cache_dir))) {
                    // Can not cache!
                    return false;
                }
            }
            // Make sure we can read/write files to cache dir
            if (!$wp_filesystem->exists($cache_dir)) {
                if (!$wp_filesystem->mkdir($cache_dir)) {
                    // Can not cache!
                    return false;
                }
            }
            $url = new FASTPIXEL_Url($url);
            $dirs = explode(DIRECTORY_SEPARATOR, $url->get_url_path());
            $path = $cache_dir;
            foreach ($dirs as $dir) {
                if (!empty($dir)) {
                    $path .= DIRECTORY_SEPARATOR . $dir;
                    if (!$wp_filesystem->exists($path)) {
                        if (!$wp_filesystem->mkdir($path)) {
                            // Can not cache!
                            return false;
                        }
                    }
                }
            }
            return $path;
        }
        public function error_file($url, $action = 'add', $data = []) {
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $path = $this->check_path($url);
            if (!$path) {
                return false;
            }
            $error_path = $path . DIRECTORY_SEPARATOR. 'error.json';
            switch ($action) {
                case 'add':
                    $wp_filesystem->put_contents($error_path, wp_json_encode($data));
                    break;
                case 'delete':
                    if ($wp_filesystem->exists($error_path)) {
                        wp_delete_file($error_path);
                    }
                    break;
            }
            return true;
        }

        public function get_option($option_name, $default = false)
        {
            if (is_multisite()) {
                if (function_exists('get_site_option')) {
                    return get_site_option($option_name, $default);
                } else {
                    return false;
                }
            }
            if (function_exists('get_option')) {
                return get_option($option_name, $default);
            } else {
                return false;
            }
        }
        public function update_option($option_name, $option_value, $autoload = null)
        {
            if (is_multisite()) {
                if (function_exists('update_site_option')) {
                    return update_site_option($option_name, $option_value);
                } else {
                    return false;
                }
            }
            if (function_exists('update_option')) {
                return update_option($option_name, $option_value, $autoload);
            } else {
                return false;
            }
        }
        public function delete_option($option_name)
        {
            if (is_multisite()) {
                if (function_exists('delete_site_option')) {
                    return delete_site_option($option_name);
                } else {
                    return false;
                }
            }
            if (function_exists('delete_option')) {
                return delete_option($option_name);
            } else {
                return false;
            }
        }

        public function valid_html($buffer)
        {
            if (empty($buffer)) {
                return false;
            }
            if (stripos($buffer, '</html>') !== false || stripos($buffer, '</body>') !== false) {
                return true;
            }
            return false;
        }

        public function esc_url($url) {
            if (function_exists('esc_url')) {
                return esc_url($url);
            }
            return $url;
        }

        public function sanitize_text_field($field) {
            if (function_exists('sanitize_text_field')) {
                return sanitize_text_field($field);
            }
            return $field;
        }

        public function user_is_logged_in()
        {
            $login_cookies = array(defined('FASTPIXEL_LOGGED_IN_COOKIE') ? FASTPIXEL_LOGGED_IN_COOKIE : (defined('LOGGED_IN_COOKIE') ? LOGGED_IN_COOKIE : ''));
            foreach ($login_cookies as $l_cookie) {
                if (!empty($_COOKIE[$l_cookie])) {
                    return true;
                }
            }
            $logged = false;
            foreach ($_COOKIE as $key => $value) {
                if (strpos($key, "wordpress_logged_in_") !== false) {
                    $logged = true;
                }
            }
            return $logged;
        }
    }

    new FASTPIXEL_Functions();
}
