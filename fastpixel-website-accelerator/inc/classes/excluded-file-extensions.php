<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_File_Extensions')) {
    class FASTPIXEL_Excluded_File_Extensions
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $excluded_file_extensions = [
            //images
            'svg',
            'png',
            'ico',
            'jpeg',
            'jpg',
            'bmp',
            'gif',
            'webp',
            //documents
            'txt',
            'doc',
            'xml',
            'xsl',
            'pdf',
            //fonts
            'woff',
            'woff2',
            'otf',
            'ttf',
            'eof',
            //styles
            'css',
            //javascript
            'js',
            //archives
            'zip',
            'rar',
            'tar',
            'gz',
            '7z',
            //audio video
            'mp3',
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'swf',
            'webm',
            'm4v',
            'mkv',
            'm4a',
            'wav',
            'ogg',
            'flac',
            'aac',
            'wma',
            'aiff',
            'mpg',
            'mpeg',
            //other
            'json',
            'csv',
            'xls',
            'map'
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 10, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'is_excluded'], 10, 2);
            add_filter('fastpixel/rest-api/excluded', [$this, 'is_excluded'], 10, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_File_Extensions();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            $file_extension = pathinfo(basename($url->get_url()), PATHINFO_EXTENSION);
            if (!empty($file_extension) && (in_array($file_extension, $this->excluded_file_extensions))) {
                return true;
            }
            return false;
        }
    }
    new FASTPIXEL_Excluded_File_Extensions();
}
