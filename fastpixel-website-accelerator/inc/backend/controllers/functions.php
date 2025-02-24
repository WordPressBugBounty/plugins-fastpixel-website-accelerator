<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Backend_Functions')) {
    class FASTPIXEL_Backend_Functions
    {
        public static $instance;
        protected $functions;
        protected $excludes;
        protected $serve_stale;

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->serve_stale = $this->functions->get_option('fastpixel_serve_stale');
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Notices();
            }
            return self::$instance;
        }

        public function cache_status_display($url, $data = null)
        {
            $cache_status = [
                'status' => '',
                'status_display' => '',
                'html_created_time' => ''
            ];
            $check_result = $this->functions->check_post_cache_status($url);
            //return early if check failed
            if ($check_result == false) {
                return $cache_status;
            }
            if (empty($data['url'])) { 
                if (!is_array($data)) {
                    $data = [
                        'url' => $url
                    ];
                } else {
                    $data['url'] = $url;
                }
            }
            $excluded = apply_filters('fastpixel/backend_functions/cache_status_display/excluded', false, $data);
            if ($excluded) {
                $cache_status['status_display'] = '<span class="have-popup"><strong>' . esc_html__('Excluded', 'fastpixel-website-accelerator') . '</strong></span>';
                $cache_status['status_display'] .= '<span class="have-popup dashicons dashicons-editor-help"></span>';
                $cache_status['status_display'] .= '<div class="pop-up">' . esc_html__('URL is excluded or has dynamic content.', 'fastpixel-website-accelerator') . '</div>';
                $cache_status['status'] = 'excluded';
                return $cache_status;
            }

            $cache_status['html_created_time'] = $check_result['html_created_time'];
            //checking page status
            if ($check_result['error'] != false && $check_result['error_time'] > $check_result['last_cache_request_time']) {
                $cache_status['status_display'] = '<span class="have-popup error"><strong>' . esc_html__('Error', 'fastpixel-website-accelerator') . '</strong></span>';
                $cache_status['status'] = 'error';
                /* translators: %s should be an error text */
                $cache_status['status_display'] .= '<div class="pop-up">' . sprintf(esc_html__('Error: %s', 'fastpixel-website-accelerator'), $check_result['error']) . '</div>';
            } else {
                if ($check_result['have_cache'] && !$check_result['need_cache']) {
                    $cache_status['status_display'] = '<span class="have-popup cached"><strong>' . esc_html__('Cached', 'fastpixel-website-accelerator') . '</strong></span>';
                    $cache_status['status'] = 'cached';
                    /* translators: %s should be a date string */
                    $cache_status['status_display'] .= '<div class="pop-up">' . sprintf(esc_html__('Cached at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['html_created_time'])) . '</div>';
                } else if ($check_result['have_cache'] && $check_result['need_cache'] && $this->serve_stale) {
                    $cache_status['status_display'] = '<div class="stale-container"><span class="have-popup cached invalidated"><strong>' . esc_html__('Stale', 'fastpixel-website-accelerator') . '</strong></span><span class="stale-loader"></span></div>';
                    $cache_status['status'] = 'stale';
                    /* translators: %s should be a date string */
                    $cache_status['status_display'] .= '<div class="pop-up">' . sprintf(esc_html__('Cached at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['html_created_time'])) . '<br/>' .
                        /* translators: %s should be a date string */
                        sprintf(esc_html__('Cache requested at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['last_cache_request_time'])) . '</div>';
                } else {
                    if ($check_result['last_cache_request_time'] && ($check_result['have_cache'] == false || $check_result['last_cache_request_time'] > $check_result['html_created_time'])) {
                        $cache_status['status_display'] = '<div class="queued-container"><span class="have-popup queued">' . esc_html__('Queued', 'fastpixel-website-accelerator') . '</span><span class="queued-loader"></span></div>';
                        $cache_status['status'] = 'queued';
                        /* translators: %s should be a date string */
                        $cache_status['status_display'] .= '<div class="pop-up">' . 
                            /* translators: %s is used to display date and time when page cache was requested */
                            sprintf(esc_html__('Cache requested at: %s', 'fastpixel-website-accelerator'), gmdate('Y-m-d H:i:s', $check_result['last_cache_request_time'])) . 
                            /* translators: */
                            ( $check_result['error'] != false ? '<br/>' . sprintf(esc_html__('Last Error: %s', 'fastpixel-website-accelerator'), $check_result['error']) : '' ) .
                        '</div>';
                    } else {
                        $cache_status['status_display'] = '<span class="not-cached">' . esc_html__('Not Cached', 'fastpixel-website-accelerator') . '</span>';
                        $cache_status['status_display'] .= '<span class="have-popup dashicons dashicons-editor-help"></span>';
                        $cache_status['status_display'] .= '<div class="pop-up">' . esc_html__('To make efficient use of your website\'s resources, pages are processed and cached as they are visited by external visitors. You can manually force a page to be processed by clicking "Cache Now."', 'fastpixel-website-accelerator') . '</div>';
                        $cache_status['status'] = 'not-cached';
                    }
                }
            }
            return $cache_status;
        }

        public function get_home_url() {
            if (function_exists('get_home_url')) {
                if (defined('ICL_SITEPRESS_VERSION')) { //WPML is installed, need to use it for getting home url
                    return apply_filters('wpml_home_url', get_home_url());
                } else {
                    return get_home_url();
                }
            }
            return false;
        }

        //TODO: Check if we need to display paginated urls
        public function paginate_links($args) {
            global $wp_rewrite;

            // Setting up default values based on the current URL.
            $pagenum_link = html_entity_decode($args['base']);
            $url_parts = explode('?', $pagenum_link);

            // Append the format placeholder to the base URL.
            $pagenum_link = trailingslashit($url_parts[0]) . '%_%';

            // URL base depends on permalink settings.
            $format = $wp_rewrite->using_index_permalinks() && !strpos($pagenum_link, 'index.php') ? 'index.php/' : '';
            $format .= $wp_rewrite->using_permalinks() ? user_trailingslashit(rtrim($wp_rewrite->pagination_base, '/') . '/%#%', 'paged') : '?paged=%#%';

            $args['base']   = $pagenum_link; // http://example.com/all_posts.php%_% : %_% is replaced by format (below).
            $args['format'] = $format; // ?page=%#% : %#% is replaced by the page number.

            $total = (int) $args['total'];
            $page_links = array();

            for ($n = 1; $n <= $total; $n++):
                $link = str_replace('%_%', 1 == $n ? '' : $args['format'], $args['base']);
                $link = str_replace('%#%', $n, $link);
                $page_links[] = $link;
            endfor;

            return $page_links;
        }

        public function print_checkbox($args = [], $display = false)
        {
            $defaults = array(
                'field_name'   => '',
                'checked'      => false,
                'label'        => '',
                'switch_class' => false,
                'data'         => [],
                'disabled'     => false,
                'description'  => ''
            );

            $args = wp_parse_args($args, $defaults);
            $switch_class = ($args['switch_class'] !== false) ? 'class="' . $args['switch_class'] . '"' : '';
            $checked = checked($args['checked'], true, false);
            $field_name = esc_attr($args['field_name']);
            $label = $args['label'];
            $description = $args['description'];
            $data = implode(' ', $args['data']);
            $disabled = $args['disabled'];
            $disabled = (true === $disabled) ? 'disabled' : '';
            if (empty($field_name)) {
                return false;
            }
            $switch = sprintf('<switch %1$s>
            <label>
                <input type="checkbox" class="fastpixel-switch" id="%2$s" name="%2$s" value="1" %3$s %4$s %5$s>
                <div class="the_switch">&nbsp;</div>
                %6$s
            </label>
            </switch>
            <span class="fastpixel-switch-description">%7$s</span>', $switch_class, $field_name, $checked, $disabled, $data, $label, $description);
            $output = '<setting id="' . $field_name . '-container" class="switch"><content>' . $switch . '</content></setting>';
            if ($display) {
                echo $output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                return $output;
            }
        }

        public function print_input($args = [], $display = false)
        {
            $defaults = array(
                'type'        => 'text',
                'field_name'  => '',
                'field_value' => '',
                'label'       => '',
                'class'       => 'fastpixel-input',
                'data'        => [],
                'disabled'    => false,
                'description' => '',
                'error'       => ''
            );

            $args = wp_parse_args($args, $defaults);
            $error = !empty($args['error']) ? $args['error'] : '';
            $args['class'] .= !empty($args['error']) ? ' fastpixel-input-error' : '';
            $type = $args['type'];
            $class = ($args['class'] !== false) ? 'class="' . $args['class'] . '"' : '';
            $field_value = $args['field_value'];
            $field_name = esc_attr($args['field_name']);
            $label = $args['label'];
            $description = $args['description'];
            $data = implode(' ', $args['data']);
            $disabled = (!empty($args['disabled']) && true == $args['disabled']) ? 'disabled' : '';
            if (empty($field_name)) {
                return false;
            }

            $output = sprintf('<setting id="%4$s-container" class="fastpixel-input-setting"><content>
            <span class="fastpixel-input-row">
            <label class="fastpixel-input-label">%1$s</label>
            <input type="%2$s" %3$s name="%4$s" %5$s %6$s value="%7$s" />
            <span class="fastpixel-error-text">%9$s</span>
            </span>
            <span class="fastpixel-input-description">%8$s</span>
            </content></setting>', $label, $type, $class, $field_name, $disabled, $data, $field_value, $description, $error);
            if ($display) {
                echo $output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                return $output;
            }
        }

        public function print_textarea($args = [], $display = false)
        {
            $defaults = array(
                'field_name'  => '',
                'field_value' => '',
                'label'       => '',
                'class'       => 'fastpixel-textarea',
                'data'        => [],
                'disabled'    => false,
                'description' => ''
            );

            $args = wp_parse_args($args, $defaults);
            $class = ($args['class'] !== false) ? 'class="' . $args['class'] . '"' : '';
            $field_value = !empty($args['field_value']) ? $args['field_value'] : '';
            $field_name = esc_attr($args['field_name']);
            $label = $args['label'];
            $description = $args['description'];
            $data = implode(' ', $args['data']);
            $disabled = (!empty($args['disabled']) && true == $args['disabled']) ? 'disabled' : '';
            if (empty($field_name)) {
                return false;
            }

            $textarea = sprintf('<label class="fastpixel-textarea-label">%1$s</label>
            <textarea %2$s name="%3$s" %4$s %5$s>%6$s</textarea>
            <span class="fastpixel-textarea-description">%7$s</span>', $label, $class, $field_name, $disabled, $data, $field_value, $description);
            $output = '<setting id="' . $field_name . '-container" class="fastpixel-textarea-setting"><content>' . $textarea . '</content></setting>';
            if ($display) {
                echo $output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                return $output;
            }
        }

        public function print_horizontal_selector($args = [], $display = false) {
            $defaults = array(
                'field_name'   => '',
                'field_values' => [],
                'selected'     => false,
                'label'        => '',
                'class'        => 'fastpixel-horizontal-selector',
                'data'         => [],
                'disabled'     => false,
                'description'  => '',
            );
            $args = wp_parse_args($args, $defaults);
            $field_name = esc_attr($args['field_name']);
            $class = ($args['class'] !== false) ? 'class="' . $args['class'] . '"' : '';
            $field_values = !empty($args['field_values']) ? $args['field_values'] : [];
            $selected = !empty($args['selected']) ? $args['selected'] : false;
            $value_descriptions = !empty($args['value_descriptions']) ? $args['value_descriptions'] : [];
            $label = $args['label'];
            $description = $args['description'];
            $data = implode(' ', $args['data']);
            if (empty($field_name)) {
                return false;
            }

            $radio_buttons = '';
            foreach ($field_values as $value => $radio_label) {
                $checked = '';
                if ($value == $selected) {
                    $checked = 'checked';
                }
                $radio_buttons .= sprintf('<label class="fastpixel-horizontal-selector-label"><input type="radio" class="fastpixel-horizontal-selector-radio" name="%1$s" value="%2$s" %3$s><span>%4$s</span></label>', $field_name, $value, $checked, $radio_label);
            }
            $descriptions = '';
            if (!empty($value_descriptions)) {
                foreach($value_descriptions as $key => $value) {
                    $descriptions .= sprintf('<p class="fastpixel-horizontal-selector-settings-description fastpixel-desc-hidden" data-value="%1$s">%2$s</p>', $key, $value);
                }
            } else {
                $descriptions = $description;
            }
            $selector = sprintf('<content>
                    <name>%1$s</name>
                    <div class="fastpixel-horizontal-options">%2$s</div>
                <info>
                %3$s
                </info>
                </content>', $label, $radio_buttons, $descriptions); //, $label, $class, $field_name, $data, $field_value, $description);
            $output = '<setting id="' . $field_name . '-container" class="fastpixel-horizontal-selector">' . $selector . '</setting>';
            if ($display) {
                echo $output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                return $output;
            }
        }

        public function print_save_button()
        {
            echo '<button class="save-button" name="settings-submit"><i class="fastpixel-icon save"></i>' . esc_html__('Save Settings', 'fastpixel-website-accelerator') . '</button>';
        }
    }

    new FASTPIXEL_Backend_Functions();
}
