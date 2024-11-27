<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_WooComm')) {
    class FASTPIXEL_WooComm {
        protected $functions;

        public function __construct() {
            $this->functions = FASTPIXEL_Functions::get_instance();
            add_action('woocommerce_new_product', [$this, 'purge_cache'], 10, 2); //resetting cache on product creation
            add_action('woocommerce_update_product', [$this, 'purge_cache'], 10, 2);
            add_action('woocommerce_product_object_updated_props', [$this, 'purge_cache_product_object_updated_props'], 10, 2);
            add_action('woocommerce_delete_product', [$this, 'purge_cache_on_delete'], 10, 1); //resetting cache on product deletion
            add_action('fastpixel/post/trashed', [$this, 'purge_cache_on_trash'], 10, 1); //resetting cache on product trash
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
    }

    if (is_admin()) {
        add_action('init', function () {
            if (class_exists('WooCommerce')) { //initializing only when woocommerce class is present
                new FASTPIXEL_WooComm();
            }
        });
    }
}
