<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Web_Server')) {
    class FASTPIXEL_Diag_Test_Web_Server extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 13;
        protected $name = 'Web Server';

        public function __construct()
        {
            parent::__construct();
        }

        public function test() {
            if (!defined('FASTPIXEL_USE_SK') || FASTPIXEL_USE_SK == false) {
                if (preg_match('/apache/i', sanitize_text_field($_SERVER['SERVER_SOFTWARE'])) && preg_match('/cgi|fpm/i', php_sapi_name())) {
                    if (file_exists(ABSPATH . '.htaccess')) {
                        $htaccess = file_get_contents(ABSPATH . '.htaccess');
                        if (preg_match_all('/RewriteRule\s+[^\[]+\[E\=HTTP_AUTHORIZATION\:\%\{HTTP\:Authorization\}\]/i', $htaccess)) {
                            $this->passed = true;
                            return;
                        }
                    }
                    /* translators: $1 is link to php.net(nothing to translate), $2 is link to contact us page(nothing to translate) */
                    $this->add_notification_message(sprintf(esc_html__('FastPixel does not work: PHP runs in CGI mode and needs rewrite rules in .htaccess to pass Basic Authorization headers. Instructions can be found on %1$s. If you need further assistance please %2$s.', 'fastpixel-website-accelerator'), '<a href="https://www.php.net/manual/en/features.http-auth.php">php.net</a>', '<a href="https://fastpixel.io/#contact"> ' . esc_html__('contact us', 'fastpixel-website-accelerator') . '</a>'));
                    $this->passed = false;
                    return;
                }
            }
            $this->passed = true;
        }

        public function l10n_name()
        {
            $this->name = esc_html__('Web Server', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Web_Server();
}
