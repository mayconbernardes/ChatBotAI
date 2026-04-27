<?php
/**
 * Plugin Name: AI Smart Chatbot
 * Plugin URI: https://example.com/ai-smart-chatbot
 * Description: A simple AI chatbot for WordPress with OpenAI integration
 * Version: 2.0.0
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

define('AISC_VERSION', '2.0.0');
define('AISC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AISC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AISC_PLUGIN_BASENAME', plugin_basename(__FILE__));

class AI_Smart_Chatbot {

    private static $instance = null;
    private $openai;
    private $admin;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->load_classes();
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_aisc_chat', array($this, 'handle_chat'));
        add_action('wp_ajax_nopriv_aisc_chat', array($this, 'handle_chat'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
    }

    private function load_classes() {
        require_once AISC_PLUGIN_DIR . 'includes/class-aisc-openai.php';
        require_once AISC_PLUGIN_DIR . 'admin/class-aisc-admin.php';

        $this->openai = new AISC_AI_Provider();
        $this->admin = new AISC_Admin($this->openai);
    }

    public function enqueue_public_assets() {
        if (!get_option('aisc_enabled', true)) {
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
            'i18n' => array(
                'placeholder' => __('Type your message...', 'ai-smart-chatbot'),
                'send' => __('Send', 'ai-smart-chatbot'),
                'error' => __('Sorry, something went wrong.', 'ai-smart-chatbot'),
            ),
            'settings' => $this->get_frontend_settings(),
        ));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ai-smart-chatbot') === false) {
            return;
        }

        wp_enqueue_style('aisc-admin', AISC_PLUGIN_URL . 'admin/css/admin.css', array(), AISC_VERSION);
        wp_enqueue_script('aisc-admin', AISC_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), AISC_VERSION, true);
    }

    private function get_frontend_settings() {
        return array(
            'position' => get_option('aisc_position', 'bottom-right'),
            'displayMode' => get_option('aisc_display_mode', 'floating'),
            'initialState' => get_option('aisc_initial_state', 'closed'),
            'autoOpenDelay' => get_option('aisc_auto_open_delay', 0),
            'bounceAnimation' => get_option('aisc_bounce_animation', false),
            'pulseAnimation' => get_option('aisc_pulse_animation', false),
            'mobilePosition' => get_option('aisc_mobile_position', 'bottom-center'),
            'primaryColor' => get_option('aisc_primary_color', '#0073aa'),
            'secondaryColor' => get_option('aisc_secondary_color', '#ffffff'),
            'title' => get_option('aisc_chat_title', 'AI Assistant'),
            'greeting' => get_option('aisc_greeting', 'Hello! How can I help you?'),
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

        $history = $this->get_conversation_history($session_id);
        $history[] = array('role' => 'user', 'content' => $message);

        $response = $this->openai->chat($message, $history);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $history[] = array('role' => 'assistant', 'content' => $response);
        $this->save_conversation_history($session_id, $history);

        wp_send_json_success(array(
            'answer' => $response,
            'session_id' => $session_id
        ));
    }

    private function get_conversation_history($session_id) {
        $history = get_transient('aisc_history_' . $session_id);
        return $history ?: array();
    }

    private function save_conversation_history($session_id, $history) {
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        set_transient('aisc_history_' . $session_id, $history, HOUR_IN_SECONDS);
    }

    public function render_chat_widget() {
        if (!get_option('aisc_enabled', true)) {
            return;
        }

        $settings = $this->get_frontend_settings();
        ?>
        <div id="aisc-widget" class="aisc-widget" data-position="<?php echo esc_attr($settings['position']); ?>">
            <button id="aisc-toggle" class="aisc-toggle" aria-label="<?php esc_attr_e('Open chat', 'ai-smart-chatbot'); ?>">
                <span class="aisc-icon-chat">💬</span>
                <span class="aisc-icon-close">✕</span>
            </button>
            <div id="aisc-chat-container" class="aisc-chat-container">
                <div class="aisc-chat-header">
                    <span class="aisc-title"><?php echo esc_html($settings['title']); ?></span>
                </div>
                <div id="aisc-messages" class="aisc-messages"></div>
                <div class="aisc-input-container">
                    <input type="text" id="aisc-input" placeholder="<?php esc_attr_e('Type your message...', 'ai-smart-chatbot'); ?>" autocomplete="off">
                    <button id="aisc-send" aria-label="<?php esc_attr_e('Send', 'ai-smart-chatbot'); ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}

function AISC() {
    return AI_Smart_Chatbot::get_instance();
}

register_activation_hook(__FILE__, 'aisc_activate');

function aisc_activate() {
    add_option('aisc_enabled', true);
    add_option('aisc_position', 'bottom-right');
    add_option('aisc_display_mode', 'floating');
    add_option('aisc_initial_state', 'closed');
    add_option('aisc_primary_color', '#0073aa');
    add_option('aisc_secondary_color', '#ffffff');
    add_option('aisc_chat_title', 'AI Assistant');
    add_option('aisc_greeting', 'Hello! How can I help you?');
    add_option('aisc_dark_mode', false);
}

add_action('plugins_loaded', 'AISC');