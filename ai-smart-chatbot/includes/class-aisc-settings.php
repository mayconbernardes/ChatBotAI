<?php

class AISC_Settings {

    private $options_group = 'aisc_settings';
    private $options_name = 'aisc_options';

    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting($this->options_group, 'aisc_backend_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting($this->options_group, 'aisc_api_provider', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_model', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_temperature', array(
            'type' => 'number',
            'sanitize_callback' => 'floatval'
        ));

        register_setting($this->options_group, 'aisc_position', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_widget_style', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_primary_color', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'aisc_secondary_color', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'aisc_chat_title', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_greeting', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field'
        ));

        register_setting($this->options_group, 'aisc_ai_tone', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'aisc_avatar_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting($this->options_group, 'aisc_dark_mode', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting($this->options_group, 'aisc_enable_lead_capture', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        register_setting($this->options_group, 'aisc_display_on', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_array')
        ));
    }

    public function sanitize_array($input) {
        if (!is_array($input)) {
            return array();
        }
        return array_map('sanitize_text_field', $input);
    }

    public function get($key, $default = '') {
        $value = get_option($key, $default);
        return $value ?: $default;
    }

    public function update($key, $value) {
        return update_option($key, $value);
    }
}