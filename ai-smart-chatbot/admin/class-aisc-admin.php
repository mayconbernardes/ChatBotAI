<?php

class AISC_Admin {

    private $openai;
    private $menu_slug = 'ai-smart-chatbot';

    public function __construct($openai) {
        $this->openai = $openai;
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_post_aisc_save_settings', array($this, 'save_settings'));
    }

    public function add_menu_pages() {
        add_menu_page(
            __('AI Smart Chatbot', 'ai-smart-chatbot'),
            __('AI Chatbot', 'ai-smart-chatbot'),
            'manage_options',
            $this->menu_slug,
            array($this, 'render_settings'),
            'dashicons-format-chat',
            30
        );
    }

    public function render_settings() {
        $api_key = get_option('aisc_api_key', '');
        $is_configured = !empty($api_key);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Smart Chatbot', 'ai-smart-chatbot'); ?></h1>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved successfully!', 'ai-smart-chatbot'); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!$is_configured): ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e('Please add your OpenAI API key to activate the chatbot.', 'ai-smart-chatbot'); ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="aisc_save_settings">
                <?php wp_nonce_field('aisc_save_settings'); ?>
                
                <h2><?php esc_html_e('API Configuration', 'ai-smart-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('OpenAI API Key', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="password" name="aisc_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="sk-...">
                            <p class="description"><?php esc_html_e('Get your API key from OpenAI dashboard', 'ai-smart-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Model', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_model">
                                <option value="gpt-4o" <?php selected(get_option('aisc_model'), 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4-turbo" <?php selected(get_option('aisc_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('aisc_model', 'gpt-3.5-turbo'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('AI Tone', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_ai_tone">
                                <option value="professional" <?php selected(get_option('aisc_ai_tone', 'professional'), 'professional'); ?>><?php esc_html_e('Professional', 'ai-smart-chatbot'); ?></option>
                                <option value="friendly" <?php selected(get_option('aisc_ai_tone'), 'friendly'); ?>><?php esc_html_e('Friendly', 'ai-smart-chatbot'); ?></option>
                                <option value="sales" <?php selected(get_option('aisc_ai_tone'), 'sales'); ?>><?php esc_html_e('Sales', 'ai-smart-chatbot'); ?></option>
                                <option value="technical" <?php selected(get_option('aisc_ai_tone'), 'technical'); ?>><?php esc_html_e('Technical', 'ai-smart-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Temperature', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="number" name="aisc_temperature" value="<?php echo esc_attr(get_option('aisc_temperature', 0.7)); ?>" min="0" max="2" step="0.1">
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Chat Widget Settings', 'ai-smart-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Enable Chatbot', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="checkbox" name="aisc_enabled" value="1" <?php checked(get_option('aisc_enabled', true), 1); ?>>
                            <label><?php esc_html_e('Show chatbot on website', 'ai-smart-chatbot'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Position', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_position">
                                <option value="bottom-right" <?php selected(get_option('aisc_position', 'bottom-right'), 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'ai-smart-chatbot'); ?></option>
                                <option value="bottom-left" <?php selected(get_option('aisc_position'), 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'ai-smart-chatbot'); ?></option>
                                <option value="top-right" <?php selected(get_option('aisc_position'), 'top-right'); ?>><?php esc_html_e('Top Right', 'ai-smart-chatbot'); ?></option>
                                <option value="top-left" <?php selected(get_option('aisc_position'), 'top-left'); ?>><?php esc_html_e('Top Left', 'ai-smart-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Initial State', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_initial_state">
                                <option value="closed" <?php selected(get_option('aisc_initial_state', 'closed'), 'closed'); ?>><?php esc_html_e('Closed (icon only)', 'ai-smart-chatbot'); ?></option>
                                <option value="open" <?php selected(get_option('aisc_initial_state'), 'open'); ?>><?php esc_html_e('Open', 'ai-smart-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Primary Color', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="color" name="aisc_primary_color" value="<?php echo esc_attr(get_option('aisc_primary_color', '#0073aa')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Title', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="text" name="aisc_chat_title" value="<?php echo esc_attr(get_option('aisc_chat_title', 'AI Assistant')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Greeting Message', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="text" name="aisc_greeting" value="<?php echo esc_attr(get_option('aisc_greeting', 'Hello! How can I help you?')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_html_e('Save Settings', 'ai-smart-chatbot'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    public function save_settings() {
        check_admin_referer('aisc_save_settings');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $settings = array(
            'aisc_api_key',
            'aisc_model',
            'aisc_ai_tone',
            'aisc_temperature',
            'aisc_enabled',
            'aisc_position',
            'aisc_initial_state',
            'aisc_primary_color',
            'aisc_chat_title',
            'aisc_greeting',
            'aisc_dark_mode'
        );

        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }

        wp_redirect(add_query_arg('message', 'saved', wp_get_referer()));
        exit;
    }
}