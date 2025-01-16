<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_WooCommerce_Compatibility')) {
    class FASTPIXEL_WooCommerce_Compatibility {
        protected $functions;
        protected $exclude_all_posts = false;
        protected $exclude_all_categories = false;
        protected $exclude_all_tags = false;
        protected $purge_all = false;
        protected $be_functions;

        public function __construct() {
            $this->functions              = FASTPIXEL_Functions::get_instance();
            $this->be_functions           = FASTPIXEL_Backend_Functions::get_instance();
            $this->exclude_all_posts      = (bool) $this->functions->get_option('fastpixel_woocommerce_exclude_products', false);
            $this->exclude_all_categories = (bool) $this->functions->get_option('fastpixel_woocommerce_exclude_categories', false);
            $this->exclude_all_tags       = (bool) $this->functions->get_option('fastpixel_woocommerce_exclude_tags', false);
            if (is_admin()) {
                add_action('woocommerce_new_product', [$this, 'purge_cache'], 10, 2); //resetting cache on product creation
                add_action('woocommerce_update_product', [$this, 'purge_cache'], 10, 2);
                add_action('woocommerce_product_object_updated_props', [$this, 'purge_cache_product_object_updated_props'], 10, 2);
                add_action('woocommerce_delete_product', [$this, 'purge_cache_on_delete'], 10, 1); //resetting cache on product deletion
                add_action('fastpixel/post/trashed', [$this, 'purge_cache_on_trash'], 10, 1); //resetting cache on product trash

                add_filter('fastpixel/backend/statuses/excluded', [$this, 'admin_check_is_excluded'], 10, 2);
                add_action('fastpixel/settings_tab/save_options', [$this, 'save_options']);
                add_action('fastpixel/settings_tab/init_settings', function () {
                    $this->register_settings();
                });
                add_filter('fastpixel/settings_tab/purge_all', [$this, 'get_purge_all_status']);
                add_filter('fastpixel/settings_tab/disabled_post_types', [$this, 'backend_remove_from_post_types_selector'], 10, 1);
            }
            add_filter('fastpixel/admin_bar/purge_this_button_exclude', [$this, 'admin_bar_purge_this_button_exclude'], 15, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'check_is_excluded'], 10, 2);
            add_action('fastpixel/is_cache_request_allowed/excludes/post_types', [$this, 'product_type_is_excluded'], 10, 2);
        }

        public function purge_cache($id, $product): void
        {
            if (class_exists('FASTPIXEL\FASTPIXEL_Backend_Cache')) {
                FASTPIXEL_Backend_Cache::get_instance()->purge_cache_by_id($id); //doing cache request
                $shop_page_id = get_option('woocommerce_shop_page_id');
                if (!empty($shop_page_id)) {
                    FASTPIXEL_Backend_Cache::get_instance()->purge_cache_by_id($shop_page_id); //doing cache request for shop page
                }
            }
        }

        public function purge_cache_product_object_updated_props($product, $propertie) {
            if ($product) {
                $this->purge_cache($product->get_id(), $product);
            }
        }

        public function purge_cache_on_delete($id, $product): void
        {
            $url = new FASTPIXEL_Url( (int) $id);
            $path = untrailingslashit($url->get_url_path());
            $this->functions->delete_cached_files($path); //removing cached files for product page
            if (class_exists('FASTPIXEL\FASTPIXEL_Backend_Cache')) {
                $shop_page_id = get_option('woocommerce_shop_page_id');
                if (!empty($shop_page_id)) {
                    FASTPIXEL_Backend_Cache::get_instance()->purge_cache_by_id($shop_page_id); //doing cache request for shop page
                }
            }
        }

        public function purge_cache_on_trash($id): void
        {
            $post = get_post($id);
            if ($post->post_type == 'product') {
                //removing cache for trashed product
                if (preg_match('/__trashed/i', $post->post_name)) {
                    $post->post_name = preg_replace('/__trashed/i', '', $post->post_name);
                }
                $url = new FASTPIXEL_Url(get_the_permalink($post));
                $path = untrailingslashit($url->get_url_path());
                $this->functions->delete_cached_files($path);
                if (class_exists('FASTPIXEL\FASTPIXEL_Backend_Cache')) {
                    $shop_page_id = get_option('woocommerce_shop_page_id');
                    if (!empty($shop_page_id)) {
                        FASTPIXEL_Backend_Cache::get_instance()->purge_cache_by_id($shop_page_id); //doing cache request for shop page
                    }
                }
            }
        }

        protected function register_settings() {
            register_setting(FASTPIXEL_TEXTDOMAIN . '-settings', 'fastpixel_woocommerce_exclude_products', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_fastpixel_woocommerce_exclude_products_cb']]);
            register_setting(FASTPIXEL_TEXTDOMAIN . '-settings', 'fastpixel_woocommerce_exclude_categories', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_fastpixel_woocommerce_exclude_categories_cb']]);
            register_setting(FASTPIXEL_TEXTDOMAIN . '-settings', 'fastpixel_woocommerce_exclude_tags', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_fastpixel_woocommerce_exclude_tags_cb']]);

            // Register a new section in the "settings" page.
            add_settings_section(
                'fastpixel_woocommerce_settings_section',
                __('WooCommerce', 'fastpixel-website-accelerator'),
                null,
                FASTPIXEL_TEXTDOMAIN,
                [
                    'before_section' => '<div class="fastpixel-woocommerce-settings-section">',
                    'after_section'  => '</div>'
                ]
            );
            $field_title = esc_html__('Exclude All Products', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_woocommerce_exclude_products',
                $field_title,
                [$this, 'fastpixel_woocommerce_exclude_products_callback'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_woocommerce_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Exclude All Categories', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_woocommerce_exclude_categories',
                $field_title,
                [$this, 'fastpixel_woocommerce_exclude_categories_callback'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_woocommerce_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Exclude All Tags', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_woocommerce_exclude_tags',
                $field_title,
                [$this, 'fastpixel_woocommerce_exclude_tags_callback'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_woocommerce_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
        }

        public function fastpixel_woocommerce_exclude_products_callback($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclude_products = $this->functions->get_option('fastpixel_woocommerce_exclude_products');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_woocommerce_exclude_products',
                'checked'     => $exclude_products,
                'label'       => $args['label'],
                'description' => esc_html__('Exclude all WooCommerce products from cache.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function fastpixel_woocommerce_exclude_categories_callback($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclude_categories = $this->functions->get_option('fastpixel_woocommerce_exclude_categories');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_woocommerce_exclude_categories',
                'checked'     => $exclude_categories,
                'label'       => $args['label'],
                'description' => esc_html__('Exclude all WooCommerce categories from cache.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function fastpixel_woocommerce_exclude_tags_callback($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $exclude_tags = $this->functions->get_option('fastpixel_woocommerce_exclude_tags');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_woocommerce_exclude_tags',
                'checked'     => $exclude_tags,
                'label'       => $args['label'],
                'description' => esc_html__('Exclude all WooCommerce tags from cache.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function save_options()
        {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) ||
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings'
            ) {
                return false;
            }
            $fastpixel_woocommerce_exclude_products = !empty($_POST['fastpixel_woocommerce_exclude_products']) && 1 == sanitize_text_field($_POST['fastpixel_woocommerce_exclude_products']) ? 1 : 0;
            $this->functions->update_option('fastpixel_woocommerce_exclude_products', (bool) $fastpixel_woocommerce_exclude_products);
            $fastpixel_woocommerce_exclude_categories = !empty($_POST['fastpixel_woocommerce_exclude_categories']) && 1 == sanitize_text_field($_POST['fastpixel_woocommerce_exclude_categories']) ? 1 : 0;
            $this->functions->update_option('fastpixel_woocommerce_exclude_categories', (bool) $fastpixel_woocommerce_exclude_categories);
            $fastpixel_woocommerce_exclude_tags = !empty($_POST['fastpixel_woocommerce_exclude_tags']) && 1 == sanitize_text_field($_POST['fastpixel_woocommerce_exclude_tags']) ? 1 : 0;
            $this->functions->update_option('fastpixel_woocommerce_exclude_tags', (bool) $fastpixel_woocommerce_exclude_tags);
        }

        //function to check if woocommerce cart/checkout/product/category/tag is excluded
        public function check_is_excluded($status, $url)
        {
            if ($this->check_product_category_is_excluded($status, $url)) {
                return true;
            }
            if ($this->check_product_tag_is_excluded($status, $url)) {
                return true;
            }
            $home_url = home_url();
            $current_url = preg_replace('/\?.*/i', '', $url->get_url());
            //checking for woocommerce
            if (function_exists('wc_get_cart_url')) {
                //comparing with cart url
                if (wc_get_cart_url() != $home_url && wc_get_cart_url() == $current_url) {
                    return true;
                }
            }
            if (function_exists('wc_get_checkout_url')) {
                //comparing with checkout url
                if (wc_get_checkout_url() != $home_url && wc_get_checkout_url() == $current_url) {
                    return true;
                }
            }
            if (function_exists('wc_get_endpoint_url')) {
                //comparing with new order url
                $order_received_url = wc_get_endpoint_url('order-received', null, wc_get_checkout_url());
                if ($order_received_url != $home_url && strpos($current_url, $order_received_url) !== false) {
                    return true;
                }
            }
            if (function_exists('wc_get_page_id')) {
                //comparing with my-account url
                $my_account_url = get_permalink(wc_get_page_id('myaccount'));
                if ($my_account_url && $my_account_url != $home_url && $my_account_url == $current_url) {
                    return true;
                }
            }
            if (function_exists('is_account_page')) {
                //comparing with account url
                if (is_account_page()) {
                    return true;
                }
            }
            return false;
        }

        public function admin_check_is_excluded($status, $args) {
            if ($status) {
                return $status;
            }
            if (!empty($args['post_type']) && $args['post_type'] == 'product' && $this->exclude_all_posts) {
                $status = true;
            }
            return $status;
        }

        public function product_type_is_excluded($post_types) {
            if ($this->exclude_all_posts) {
                $post_types[] = 'product';
            }
            return $post_types;
        }

        protected function check_product_category_is_excluded($status, $url)
        {
            if ($status) {
                return $status;
            }
            if (is_tax('product_cat') && $this->exclude_all_categories) {
                $status = true;
            }
            return $status;
        }

        protected function check_product_tag_is_excluded($status, $url)
        {
            if ($status) {
                return $status;
            }
            if (is_tax('product_tag') && $this->exclude_all_tags) {
                $status = true;
            }
            return $status;
        }

        public function admin_bar_purge_this_button_exclude($status, $args) {
            if ($status) {
                return $status;
            }
            if (empty($args['post_type'])) {
                return false;
            }
            if ($args['post_type'] == 'product') {
                return $this->admin_check_is_excluded($status, $args);
            } else if ($args['post_type'] == 'taxonomy') {
                $term = get_term($args['id']);
                if ($term->taxonomy == 'product_cat' && $this->exclude_all_categories) {
                    return true;
                } else if ($term->taxonomy == 'product_tag' && $this->exclude_all_tags) {
                    return true;
                }
            }
            return false;
        }

        public function sanitize_fastpixel_woocommerce_exclude_products_cb($value) {
            $old_value = $this->functions->get_option('fastpixel_woocommerce_exclude_products');
            if ($value != $old_value) {
                $this->purge_all = true;
            }
            return $value;
        }

        public function sanitize_fastpixel_woocommerce_exclude_categories_cb($value)
        {
            $old_value = $this->functions->get_option('fastpixel_woocommerce_exclude_categories');
            if ($value != $old_value) {
                $this->purge_all = true;
            }
            return $value;
        }

        public function sanitize_fastpixel_woocommerce_exclude_tags_cb($value)
        {
            $old_value = $this->functions->get_option('fastpixel_woocommerce_exclude_tags');
            if ($value != $old_value) {
                $this->purge_all = true;
            }
            return $value;
        }

        public function get_purge_all_status($status) {
            if ($status == true) {
                return $status;
            }
            return $this->purge_all;
        }

        public function backend_remove_from_post_types_selector($post_types) {
            $post_types[] = 'product';
            return $post_types;
        }
    }

    add_action('plugins_loaded', function () {
        if (class_exists('WooCommerce')) { //initializing only when woocommerce class is present
            new FASTPIXEL_WooCommerce_Compatibility();
        }
    });
}
