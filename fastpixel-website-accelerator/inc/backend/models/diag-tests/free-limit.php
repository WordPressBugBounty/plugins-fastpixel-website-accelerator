<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Free_Limit')) {
    class FASTPIXEL_Diag_Test_Free_Limit extends FASTPIXEL_Diag_Test
    {
        protected $order_id = 20;
        protected $name = 'Free Limit Reached';
        protected $activation_check = true;
        protected $display_notifications = true;
        protected $visible_on_diagnostics_page = false;

        public function __construct()
        {
            parent::__construct();
        }

        public function test()
        {
            if (function_exists('get_option')) {
                $functions = FASTPIXEL_Functions::get_instance();
                $free_limit_reached = $functions->get_option('fastpixel_free_limit_reached');
                if ($free_limit_reached) {
                    /* translators: $1 displays 2 br tags(nothing to translate), $2 is link to pricing page ("pricing page" text should be translated separately) */
                    $this->add_notification_message(sprintf(esc_html__('Unfortunately, you have reached your pageviews limit. FastPixel is no longer optimizing new pages:-( %1$s Please check our %2$s or increase the pageviews limit for this domain if your plan allows it.', 'fastpixel-website-accelerator'), '<br/>', '<a href="https://fastpixel.io/pricing/" target="_blank">' . esc_html__('pricing page', 'fastpixel-website-accelerator') .'</a>' ), 'error', false);
                }
            }        
        }

        public function activation_test()
        {
            return true;
        }

        public function l10n_name()
        {
            $this->name = esc_html__('Free Limit Reached', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Free_Limit();
}
