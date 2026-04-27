<?php

class AISC_i18n {

    private $translations = array();
    private $current_locale = 'en_US';

    public function __construct() {
        $this->load_translations();
        add_action('plugins_loaded', array($this, 'init'), 1);
    }

    public function init() {
        $this->current_locale = $this->get_locale();
        $this->load_translations();
    }

    private function get_locale() {
        $locale = get_locale();
        
        $available = array('en_US', 'fr_FR', 'pt_BR');
        
        foreach ($available as $lang) {
            if (strpos($locale, substr($lang, 0, 2)) === 0) {
                return $lang;
            }
        }
        
        return 'en_US';
    }

private function load_translations() {
        $lang_files = array(
            'en_US' => plugin_dir_path(__FILE__) . '../languages/en_US.po',
            'fr_FR' => plugin_dir_path(__FILE__) . '../languages/fr_FR.po',
            'pt_BR' => plugin_dir_path(__FILE__) . '../languages/pt_BR.po',
            'es_ES' => plugin_dir_path(__FILE__) . '../languages/es_ES.po',
            'zh_CN' => plugin_dir_path(__FILE__) . '../languages/zh_CN.po',
            'ru_RU' => plugin_dir_path(__FILE__) . '../languages/ru_RU.po',
            'de_DE' => plugin_dir_path(__FILE__) . '../languages/de_DE.po',
        );

        foreach ($lang_files as $locale => $file) {
            if (file_exists($file)) {
                $this->translations[$locale] = $this->parse_po_file($file);
            }
        }
    }

    private function get_locale() {
        $locale = get_locale();
        
        $available = array(
            'en_US' => array('en', 'en_US'),
            'fr_FR' => array('fr', 'fr_FR'),
            'pt_BR' => array('pt', 'pt_BR', 'pt_PT'),
            'es_ES' => array('es', 'es_ES', 'es_MX'),
            'zh_CN' => array('zh', 'zh_CN', 'zh_TW'),
            'ru_RU' => array('ru', 'ru_RU'),
            'de_DE' => array('de', 'de_DE', 'de_AT', 'de_CH'),
        );
        
        foreach ($available as $locale_code => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($locale, $prefix) === 0) {
                    return $locale_code;
                }
            }
        }
        
        return 'en_US';
    }
        }
    }

    private function parse_po_file($file) {
        $translations = array();
        $content = file_get_contents($file);
        
        preg_match_all('/msgid\s+"(.*?)"\s+msgstr\s+"(.*?)"/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $key = stripslashes($match[1]);
            $value = stripslashes($match[2]);
            if (!empty($key)) {
                $translations[$key] = $value;
            }
        }

        if (empty($translations)) {
            $temp = include $file;
            if (is_array($temp)) {
                $translations = $temp;
            }
        }

        return $translations;
    }

    public function tr($key, $default = '') {
        $locale = $this->current_locale;
        
        if (isset($this->translations[$locale][$key])) {
            return $this->translations[$locale][$key];
        }
        
        if (isset($this->translations['en_US'][$key])) {
            return $this->translations['en_US'][$key];
        }
        
        return $default ?: $key;
    }

    public static function get($key, $default = '') {
        $instance = new self();
        return $instance->tr($key, $default);
    }
}