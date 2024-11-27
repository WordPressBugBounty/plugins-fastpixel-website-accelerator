<?php
namespace FASTPIXEL;

defined('ABSPATH') || die('no jokes please');

if (!class_exists('FASTPIXEL\FASTPIXEL_Debug')) {
    class FASTPIXEL_Debug {

        public static $enabled = false;
        protected static $message_type = 0;
        protected static $destination = FASTPIXEL_PLUGIN_DIR . '/fastpixel_log.txt';

        public function __construct() {
            if (defined('FASTPIXEL_DEBUG') && FASTPIXEL_DEBUG == true) {
                self::$enabled = true;
            }
        }

        public static function log($message = false, $object = 'undefined'): void
        {
            if (!self::$enabled) {
                return;
            }
            if ($message) {
                $msg = $message;
                if (is_bool($object)) {
                    $msg .= ' -> ' . ($object == true ? 'True' : 'False');
                } else {
                    if ($object != 'undefined') {
                        $msg .= ' -> ' . var_export($object, true);
                    }
                }
                $destination = null;
                if (self::$message_type == 3) {
                    $destination = self::$destination;
                }
                error_log(sprintf("FASTPIXEL OPTIMIZER: %s \r\n", $msg), self::$message_type, $destination);
            }
        }
    }
    new FASTPIXEL_Debug();
}
