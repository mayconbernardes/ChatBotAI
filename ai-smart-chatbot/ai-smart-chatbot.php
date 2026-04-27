<?php
/**
 * Plugin Name: AI Smart Chatbot
 * Plugin URI: https://example.com/ai-smart-chatbot
 * Description: A highly customizable AI chatbot with RAG system that answers based on your website content.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-smart-chatbot
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AISC_VERSION', '1.0.0');
define('AISC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AISC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AISC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AISC_API_ENDPOINT', get_option('aisc_backend_url', 'http://localhost:8000'));

class AI_Smart_Chatbot {

    private static $instance = null;
    private $admin;
    private $settings;
    private $knowledge_base;
    private $api_client;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this-> load_classes();
    }

    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action(' REST_API', array($this, 'register_rest_routes'));
        add_action('wp_ajax_aisc_chat', array($this, 'handle_chat'));
        add_action('wp_ajax_nopriv_aisc_chat', array($this, 'handle_chat'));
        add_shortcode('ai_smart_chatbot', array($this, 'shortcode_handler'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
    }

    private function load_classes() {
        require_once AISC_PLUGIN_DIR . 'includes/class-aisc-settings.php';
        require_once AISC_PLUGIN_DIR . 'includes/class-aisc-api-client.php';
        require_once AISC_PLUGIN_DIR . 'includes/class-aisc-knowledge-base.php';
        require_once AISC_PLUGIN_DIR . 'includes/class-aisc-embeddings.php';
        require_once AISC_PLUGIN_DIR . 'admin/class-aisc-admin.php';

        $this->settings = new AISC_Settings();
        $this->api_client = new AISC_API_Client();
        $this->knowledge_base = new AISC_Knowledge_Base($this->api_client);
        $this->admin = new AISC_Admin($this->settings, $this->knowledge_base, $this->api_client);
    }

    public function load_textdomain() {
        load_plugin_textdomain('ai-smart-chatbot', false, dirname(AISC_PLUGIN_BASENAME) . '/languages');
    }

    public function enqueue_public_assets() {
        if (!$this->should_load_assets()) {
            return;
        }

        wp_enqueue_style(
            'aisc-chatbot',
            AISC_PLUGIN_URL . 'public/css/chatbot.css',
            array(),
            AISC_VERSION
        );

        wp_enqueue_script(
            'aisc-chatbot',
            AISC_PLUGIN_URL . 'public/js/chatbot.js',
            array('jquery'),
            AISC_VERSION,
            true
        );

        wp_localize_script('aisc-chatbot', 'aiscData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aisc_nonce'),
            'endpoint' => AISC_API_ENDPOINT,
            'i18n' => array(
                'placeholder' => __('Type your message...', 'ai-smart-chatbot'),
                'send' => __('Send', 'ai-smart-chatbot'),
                'error' => __('Sorry, something went wrong.', 'ai-smart-chatbot'),
                'noanswer' => __("I don't have that information", 'ai-smart-chatbot'),
            ),
            'settings' => $this->get_frontend_settings(),
        ));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ai-smart-chatbot') === false) {
            return;
        }

        wp_enqueue_style(
            'aisc-admin',
            AISC_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            AISC_VERSION
        );

        wp_enqueue_script(
            'aisc-admin',
            AISC_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            AISC_VERSION,
            true
        );
    }

    private function should_load_assets() {
        global $post;
        $display_on = get_option('aisc_display_on', array('all'));
        
        if (in_array('all', $display_on)) {
            return true;
        }

        if (is_singular() && $post) {
            return in_array($post->ID, $display_on);
        }

        return false;
    }

    private function get_frontend_settings() {
        return array(
            'position' => get_option('aisc_position', 'bottom-right'),
            'primaryColor' => get_option('aisc_primary_color', '#0073aa'),
            'secondaryColor' => get_option('aisc_secondary_color', '#ffffff'),
            'fontFamily' => get_option('aisc_font_family', 'inherit'),
            'avatarUrl' => get_option('aisc_avatar_url', ''),
            'title' => get_option('aisc_chat_title', 'AI Assistant'),
            'greeting' => get_option('aisc_greeting', 'Hello! How can I help you?'),
            'tone' => get_option('aisc_ai_tone', 'professional'),
            'widgetStyle' => get_option('aisc_widget_style', 'bubble'),
            'darkMode' => get_option('aisc_dark_mode', false),
        );
    }

    public function handle_chat() {
        check_ajax_referer('aisc_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message is required'));
        }

        $response = $this->api_client->chat($message, $session_id);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        wp_send_json_success($response);
    }

    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'style' => 'bubble',
        ), $atts);

        return '<div class="aisc-shortcode-container" data-style="' . esc_attr($atts['style']) . '"></div>';
    }

    public function render_chat_widget() {
        if (!$this->should_load_assets()) {
            return;
        }

        $settings = $this->get_frontend_settings();
        ?>
        <div id="aisc-widget" class="aisc-widget aisc-<?php echo esc_attr($settings['widgetStyle']); ?>" 
             data-position="<?php echo esc_attr($settings['position']); ?>">
            <button id="aisc-toggle" class="aisc-toggle" aria-label="<?php esc_attr_e('Open chat', 'ai-smart-chatbot'); ?>">
                <span class="aisc-icon-chat">💬</span>
                <span class="aisc-icon-close">✕</span>
            </button>
            <div id="aisc-chat-container" class="aisc-chat-container">
                <div class="aisc-chat-header">
                    <?php if (!empty($settings['avatarUrl'])): ?>
                        <img src="<?php echo esc_url($settings['avatarUrl']); ?>" alt="Avatar" class="aisc-avatar">
                    <?php endif; ?>
                    <span class="aisc-title"><?php echo esc_html($settings['title']); ?></span>
                </div>
                <div id="aisc-messages" class="aisc-messages">
                    <div class="aisc-message aisc-message-bot">
                        <div class="aisc-message-content">
                            <?php echo esc_html($settings['greeting']); ?>
                        </div>
                    </div>
                </div>
                <div class="aisc-input-container">
                    <input type="text" id="aisc-input" placeholder="<?php esc_attr_e('Type your message...', 'ai-smart-chatbot'); ?>" autocomplete="off">
                    <button id="aisc-send" aria-label="<?php esc_attr_e('Send message', 'ai-smart-chatbot'); ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_api_client() {
        return $this->api_client;
    }

    public function get_settings() {
        return $this->settings;
    }

    public function get_knowledge_base() {
        return $this->knowledge_base;
    }
}

function AISC() {
    return AI_Smart_Chatbot::get_instance();
}

add_action('plugins_loaded', 'AISC');