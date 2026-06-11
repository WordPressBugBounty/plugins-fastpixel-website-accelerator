<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Local_Cache')) {
    class FASTPIXEL_Local_Cache
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $page_content = '';
        protected $is_html_content = true;
        protected $response_code = 200;
        /** Snapshot of $wp_styles->done captured at the end of wp_head, used to detect late-enqueued styles. */
        protected $head_styles_done = [];
        /** Output buffer level recorded immediately after FastPixel calls ob_start(). Used to detect nested
         *  WP buffers (e.g. wp_start_template_enhancement_output_buffer in WP 6.9+) that must be flushed
         *  before we read our own buffer, so their callbacks run first and replace placeholder styles. */
        protected $ob_level = 0;

        public function __construct() {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config    = FASTPIXEL_Config_Model::get_instance();
            if (!defined('FASTPIXEL_ADVANCED_CACHE') || !FASTPIXEL_ADVANCED_CACHE) {
                return;
            }
            //starting output beffering on fastpixel/init, also checking if user is not logged in
            if (!$this->functions->user_is_logged_in()) {
                add_action('fastpixel/cachefiles/exists', function($args = []) {
                    //no need to buffer output if "cache" or "local_cache" file exists
                    if ((!empty($args['cache_exists']) && $args['cache_exists'] == true) ||
                        (!empty($args['local_cache_exists']) && $args['local_cache_exists'] == true) ) {
                        return false;
                    }
                    ob_start(); //starting buffering output
                    $this->ob_level = ob_get_level(); // record our level so we can detect nested WP buffers
                    add_action('fastpixel/shutdown', [$this, 'get_buffer'], 10); //getting buffer into variable
                    add_action('fastpixel/shutdown/request/before', [$this, 'save'], 10, 1); //saving buffer to file, if page passed validation
                    // Snapshot which styles are already done at the end of wp_head so we can detect
                    // styles that were enqueued late (from block render_callbacks) and are absent from the HTML.
                    //add_action('wp_head', [$this, 'snapshot_head_styles'], 9999);
                });
            }
            add_action('fastpixel/cachefiles/saved', [$this, 'delete_file_on_api_request'], 10, 1);
            add_action('fastpixel/post/trashed', [$this, 'delete_file_on_trashed'], 10, 1);
            add_action('fastpixel/admin/purge_cache_by_url', [$this, 'delete_file_on_purge'], 10, 1);
            add_action('fastpixel/admin/purge_cache_by_id', [$this, 'delete_file_on_purge'], 10, 1);
            //getting response code for extra check
            add_action('fastpixel/shutdown', function () {
                $this->response_code = http_response_code();
            });
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Local_Cache();
            }
            return self::$instance;
        }

        // Not used. Keeping it as an example of how to capture late-enqueued styles that are missing from the HTML due to WP quirks or filters.
        /**
         * Captures $wp_styles->done at the very end of wp_head so we can later identify
         * styles that were enqueued after wp_head (from block render_callbacks, etc.).
         */
        public function snapshot_head_styles() {
            global $wp_styles;
            if ($wp_styles instanceof \WP_Styles) {
                $this->head_styles_done = $wp_styles->done;
            }
        }

        public function get_buffer() {
            if (ob_get_level() == 0) {
                return false;
            }
            // WordPress 6.9+ opens a template-enhancement output buffer (wp_start_template_enhancement_output_buffer)
            // ABOVE FastPixel's own buffer. Its ob_end callback replaces placeholder <style> tags with actual
            // block/global styles. If FastPixel reads ob_get_contents() before that callback fires, the saved
            // HTML contains only the placeholder stubs and is missing all block/global styles.
            // Flushing every ob level above our own triggers each pending callback in turn (including WP's
            // wp_finalize_template_enhancement_output_buffer), so their processed output flows into our buffer.
            
            // After each ob_end_flush(), ob_get_level() decrements by 1. That's why successive calls return different values — the stack is shrinking in real time. 
            // A single ob_end_flush() would only collapse one level and could miss deeper nested buffers.
            
            if ($this->ob_level > 0) {
                while (ob_get_level() > $this->ob_level) {
                    ob_end_flush();
                }
            }
            if (ob_get_level() == 0) {
                return false;
            }
            //getting buffered output
            $this->page_content = ob_get_contents();
            if (!preg_match('/<html/i', $this->page_content) || preg_match('/var _wpmeteor\s?=/i', $this->page_content)) {
                $this->is_html_content = false;
                //preventing request if there is no html tag in output
                add_filter('fastpixel/is_cache_request_allowed/excluded', function ($excluded) {
                    return true;
                }, 20, 1);
            }
        }      

        public function save($url)
        {
            if (empty($url) || !is_a($url, 'FASTPIXEL\FASTPIXEL_Url')) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Url is empty or not instance of FASTPIXEL\FASTPIXEL_Url');
                }
                return false;
            }
            //checking response code, no need to save buffer if there was redirect
            if (in_array($this->response_code, [300, 301, 302, 303, 307, 308])) {
                return false;
            }
            //if content is not empty, save it to file
            if (strlen($this->page_content) > 0 && $this->is_html_content) {
                //$this->file($url, 'add', $this->move_late_styles_to_head($this->page_content));
                $this->file($url, 'add', $this->page_content);
                ob_end_flush();
            } else {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Buffer is empty');
                }
            }
        }

        // Only keep this here as an example on how to enumerate late styles and inject them if needed.     
        /**
         * Ensures late-enqueued styles (enqueued after wp_head, e.g. from block render_callbacks)
         * appear as <link> tags in <head> in the local cache HTML.
         *
         * Two strategies are combined:
         * 1. Move any <link rel="stylesheet"> tags already present in <body> to <head>.
         * 2. Inject <link> tags for styles that entered $wp_styles->done after wp_head
         *    but whose src URL is absent from the HTML (e.g. when WordPress marks a style
         *    as done without producing output due to a filter or internal quirk).
         */
        protected function move_late_styles_to_head($html)
        {
            $head_close_pos = stripos($html, '</head>');
            if ($head_close_pos === false) {
                return $html;
            }

            // --- Strategy 1: move <link rel="stylesheet"> tags from <body> to <head> ---
            // This is just hipotethical.
            $body_part = substr($html, $head_close_pos);
            $late_links = [];
            $body_part_cleaned = preg_replace_callback(
                '/<link\b[^>]+rel=["\']stylesheet["\'][^>]*>|<link\b[^>]+href=[^>]+rel=["\']stylesheet["\'][^>]*>/i',
                function ($matches) use (&$late_links) {
                    $late_links[] = $matches[0];
                    return '';
                },
                $body_part
            );

            // --- Strategy 2: inject styles that WP marked done after wp_head but never output ---
            $inject_links = [];
            if (!empty($this->head_styles_done)) {
                global $wp_styles;
                if ($wp_styles instanceof \WP_Styles) {
                    $late_done = array_diff($wp_styles->done, $this->head_styles_done);
                    foreach ($late_done as $handle) {
                        if (!isset($wp_styles->registered[$handle])) {
                            continue;
                        }
                        $dep = $wp_styles->registered[$handle];
                        if (empty($dep->src)) {
                            continue;
                        }
                        $src = $dep->src;
                        // Make absolute if protocol-relative or path-relative
                        if (strncmp($src, '//', 2) === 0) {
                            $src = 'https:' . $src;
                        } elseif ($src[0] === '/') {
                            $src = rtrim(site_url(), '/') . $src;
                        }
                        // Skip core WordPress styles (wp-includes)
                        if (strpos($src, '/wp-includes/') !== false) {
                            continue;
                        }
                        $filename = basename(parse_url($src, PHP_URL_PATH));
                        // Only inject if neither the filename nor the CSS id attribute is already in the HTML
                        if (!empty($filename)
                            && strpos($html, $filename) === false
                            && strpos($html, $handle . '-css') === false
                        ) {
                            $ver   = !empty($dep->ver) ? $dep->ver : false;
                            $media = !empty($dep->args) ? $dep->args : 'all';
                            $href  = $ver ? add_query_arg('ver', $ver, $src) : $src;
                            $inject_links[] = '<link rel="stylesheet" id="' . esc_attr($handle) . '-css" href="' . esc_url($href) . '" media="' . esc_attr($media) . '" />';
                        }
                    }
                }
            }

            if (empty($late_links) && empty($inject_links)) {
                return $html;
            }

            $head_part = substr($html, 0, $head_close_pos);
            $all_links = array_merge($late_links, $inject_links);
            return $head_part . implode("\n", $all_links) . "\n" . '</head>' . $body_part_cleaned;
        }

        protected function file($url, $action = 'add', $data = '')
        {
            $path = $this->functions->check_path($url);
            if (!$path) {
                return false;
            }
            $modified_time = time();

            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem) && file_exists(ABSPATH . '/wp-admin/includes/file.php')) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $local_path = $path . DIRECTORY_SEPARATOR . 'index_local.html';
            switch ($action) {
                case 'add':
                    if (empty($data)) {
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Buffer is empty, exit');
                        }
                        return false;
                    }
                    // Save the response body atomically to prevent serving partially written cache files.
                    if (!$this->functions->atomic_write_file($local_path, $data, $modified_time)) {
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Error occured while saving Buffer to file');
                        }
                    }
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Saved output buffer to $path', $local_path);
                    }
                    break;
                case 'delete':
                    if ($wp_filesystem->exists($local_path)) {
                        $wp_filesystem->delete($local_path, false, 'f');
                    }
                    break;
            }
            return true;
        }

        public function delete_file_on_api_request($url) {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Deleting local cache on rest-api request');
            }
            $this->file($url, 'delete');
        }

        public function delete_file_on_purge($url)
        {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Deleting local cache on post cache purge');
            }
            $this->file($url, 'delete');
        }
        public function delete_file_on_trashed($post_id)
        {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Local_Cache: Deleting local cache on post cache purge');
            }
            if (!empty($post_id) && is_numeric($post_id)) {
                $url = get_permalink($post_id);
                if ($url) {
                    $this->file($url, 'delete');
                }
            }
        }
    }
    new FASTPIXEL_Local_Cache();
}
