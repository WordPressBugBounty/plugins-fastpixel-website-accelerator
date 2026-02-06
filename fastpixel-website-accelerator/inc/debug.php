<?php
namespace FASTPIXEL;

defined('ABSPATH') || die('no jokes please');

if (!class_exists('FASTPIXEL\FASTPIXEL_Debug')) {
    //TODO this is a fix for upgrading from 1.1.0 to 1.2.0 when advanced-cache.php is not regenerated
    if( !defined('FASTPIXEL_CACHE_DIR')) {
        define('FASTPIXEL_CACHE_DIR', rtrim(WP_CONTENT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . FASTPIXEL_TEXTDOMAIN);
    }

    class FASTPIXEL_Debug {
        const DEST_SYSTEM = 1;
        const DEST_FILE = 3;

        const FLAGS_DESTINATION = 3;
        const FLAG_FRONT = 4;
        const FLAG_REQUEST = 8;

        public static $enabled = false;
        protected static $message_type = 0; //TODO make this switch to 3 if FASTPIXEL_DEBUG is properly set (flag)

        protected static $destination = FASTPIXEL_CACHE_DIR . DIRECTORY_SEPARATOR . 'fastpixel_log.txt';

        public function __construct() {
            if (defined('FASTPIXEL_DEBUG') && FASTPIXEL_DEBUG !== false) {
                self::$enabled = true;
                self::$message_type = FASTPIXEL_DEBUG & self::FLAGS_DESTINATION; //first two bits for destination
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
                if (self::$message_type == self::DEST_FILE) {
                    $destination = self::$destination;
                }
                //echo('dadada2: ' . $destination . ' TYPE: ' . self::$message_type . ' MESSAGE: ' . $msg);
                error_log(sprintf("FASTPIXEL OPTIMIZER: %s \r\n", $msg), self::$message_type, $destination);
            }
        }
    }
    new FASTPIXEL_Debug();
}
