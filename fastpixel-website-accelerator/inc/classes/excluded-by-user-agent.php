<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_By_User_Agent')) {
    class FASTPIXEL_Excluded_By_User_Agent
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        /**
         * Performance / scoring tools that must ALWAYS be served the optimized cache so their
         * measurements reflect the optimized page. They take precedence over every exclusion
         * entry below - default or user-defined - so they can never be accidentally bypassed.
         * Matched case-insensitively as a substring of the visitor User-Agent header.
         */
        protected $safelist_user_agents = [
            'Lighthouse',  //Google PageSpeed Insights & Lighthouse (User-Agent contains "Chrome-Lighthouse")
            'PageSpeed',   //Google PageSpeed Insights ("Google-PageSpeed-Insights")
            'Page Speed',  //Google Page Speed Insights (spaced variant)
            'GTmetrix',
            'Pingdom',
            'PTST',        //WebPageTest
        ];
        /**
         * User agents that bypass optimization out of the box. Each entry is matched
         * case-insensitively against the visitor User-Agent header (substring match, or the
         * * character may be used as a wildcard). This list is intentionally NOT shown in the
         * plugin UI - users extend it through the "User-agent Exclusions" field.
         */
        protected $default_user_agents = [
            //Search engine crawlers - index content server-side, no benefit from front-end optimization
            'Googlebot',          //Google (also covers Googlebot-Image, -News, -Video, -Mobile)
            'Bingbot',            //Microsoft Bing
            'msnbot',             //Microsoft (legacy)
            'Slurp',              //Yahoo
            'DuckDuckBot',        //DuckDuckGo
            'Baiduspider',        //Baidu
            'YandexBot',          //Yandex (specific token, so it won't match the Yandex Browser)
            'Sogou',              //Sogou
            'Exabot',             //Exalead
            'Applebot',           //Apple - Siri/Spotlight (also covers Applebot-Extended AI fetches)
            'PetalBot',           //Huawei Petal Search
            'SeznamBot',          //Seznam
            'Qwantify',           //Qwant
            'MojeekBot',          //Mojeek
            //AI crawlers / assistants - scrape content for training or answers, not real visitors
            'GPTBot',             //OpenAI (training)
            'ChatGPT',            //OpenAI - ChatGPT browsing (covers ChatGPT-User)
            'OAI-SearchBot',      //OpenAI - SearchGPT
            'ClaudeBot',          //Anthropic
            'Claude-Web',         //Anthropic (legacy)
            'anthropic-ai',       //Anthropic
            'PerplexityBot',      //Perplexity
            'Perplexity-User',    //Perplexity (user-initiated)
            'CCBot',              //Common Crawl (widely used for AI datasets)
            'GoogleOther',        //Google - non-search / AI & research crawler
            'Amazonbot',          //Amazon
            'Bytespider',         //ByteDance / TikTok
            'Meta-ExternalAgent', //Meta AI
            'FacebookBot',        //Meta - AI crawler (distinct from facebookexternalhit)
            'Diffbot',            //Diffbot
            'ImagesiftBot',       //Hive
            'Omgilibot',          //webz.io
            'YouBot',             //You.com
            'cohere-ai',          //Cohere
            'DuckAssistBot',      //DuckDuckGo AI
            'AI2Bot',             //Allen Institute for AI
            'Timpibot',           //Timpi
            'PanguBot',           //Huawei
            //Aggressive third-party SEO / backlink crawlers - high volume, would needlessly fill the optimization queue
            'AhrefsBot',
            'SemrushBot',
            'MJ12bot',
            'DotBot',
            'BLEXBot',
            'rogerbot',
            //Social / link-preview scrapers - fetch markup only, never execute JS, so optimization brings no benefit
            'facebookexternalhit',
            'Facebot',
            'Twitterbot',
            'LinkedInBot',
            'Pinterest',
            'Slackbot',
            'TelegramBot',
            'WhatsApp',
            'Discordbot',
            //Uptime / availability monitors - only check that the page responds
            'UptimeRobot',
            'StatusCake',
            'Site24x7',
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            /**
             * Hooking the two front-end gates so a matching visitor is neither served an optimized
             * page nor queued for optimization. The fastpixel/rest-api/excluded filter is
             * intentionally NOT hooked here: during the optimization service callback the
             * User-Agent header belongs to the service, not to the original visitor, so the
             * visitor agent can't be evaluated at that point.
             */
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 11, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'is_excluded'], 11, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_By_User_Agent();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url)
        {
            if ($excluded == true) {
                return $excluded;
            }
            if (empty($_SERVER['HTTP_USER_AGENT'])) {
                return false;
            }
            $user_agent = $this->functions->sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
            if ($user_agent === '') {
                return false;
            }
            //Safelisted performance tools (e.g. Google PageSpeed Insights) are never excluded,
            //so they are always served the optimized cache and their scores reflect it.
            foreach ($this->safelist_user_agents as $safe) {
                if (stripos($user_agent, $safe) !== false) {
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('Excluded By User Agent: safelisted, never excluded', $user_agent);
                    }
                    return false;
                }
            }
            foreach ($this->get_excluded_user_agents() as $pattern) {
                $pattern = trim($pattern);
                if ($pattern === '') {
                    continue;
                }
                if (strpos($pattern, '*') !== false) {
                    //regular expression match, for now we will support only * (mirrors page exclusions)
                    $regex = '/' . preg_replace('/\\\\?\*/i', '.*?', preg_quote($pattern, '/')) . '/i';
                    if (preg_match($regex, $user_agent)) {
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Excluded By User Agent regexp: agent', $user_agent);
                            FASTPIXEL_Debug::log('Excluded By User Agent regexp: pattern', $regex);
                        }
                        return true;
                    }
                } elseif (stripos($user_agent, $pattern) !== false) {
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('Excluded By User Agent direct: agent', $user_agent);
                        FASTPIXEL_Debug::log('Excluded By User Agent direct: pattern', $pattern);
                    }
                    return true;
                }
            }
            return false;
        }

        protected function get_excluded_user_agents(): array
        {
            if (function_exists('get_option')) {
                $raw = $this->functions->get_option('fastpixel_user_agent_exclusions', '');
            } else {
                $raw = $this->config->get_option('fastpixel_user_agent_exclusions');
            }

            $user_agents = $this->default_user_agents;
            //user agents contain spaces, so split on new lines only - one entry per line
            $rows = preg_split('/\r\n|\r|\n/', trim((string) $raw));
            if (!is_array($rows)) {
                return $user_agents;
            }

            foreach ($rows as $row) {
                $row = trim((string) $row);
                if ($row === '') {
                    continue;
                }
                $user_agents[] = $row;
            }

            return $user_agents;
        }
    }
    new FASTPIXEL_Excluded_By_User_Agent();
}
