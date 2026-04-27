<?php

class AISC_Admin {

    private $ai_provider;
    private $menu_slug = 'ai-smart-chatbot';

    public function __construct($ai_provider) {
        $this->ai_provider = $ai_provider;
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
        $provider = get_option('aisc_ai_provider', 'openai');
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
                <p><?php esc_html_e('Please add your API key to activate the chatbot.', 'ai-smart-chatbot'); ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="aisc_save_settings">
                <?php wp_nonce_field('aisc_save_settings'); ?>
                
                <h2><?php esc_html_e('AI Provider', 'ai-smart-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Select Provider', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_ai_provider" id="aisc-provider">
                                <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="google" <?php selected($provider, 'google'); ?>>Google Gemini</option>
                                <option value="anthropic" <?php selected($provider, 'anthropic'); ?>>Anthropic Claude</option>
                                <option value="xai" <?php selected($provider, 'xai'); ?>>xAI Grok</option>
                                <option value="deepseek" <?php selected($provider, 'deepseek'); ?>>DeepSeek</option>
                                <option value="qwen" <?php selected($provider, 'qwen'); ?>>Alibaba Qwen</option>
                                <option value="openrouter" <?php selected($provider, 'openrouter'); ?>>OpenRouter (multi-model)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('API Key', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="password" name="aisc_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="API key...">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Model', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_model" id="aisc-model">
                                <?php if ($provider === 'openai'): ?>
                                <option value="gpt-4o" <?php selected(get_option('aisc_model'), 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?php selected(get_option('aisc_model'), 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                                <option value="gpt-4-turbo" <?php selected(get_option('aisc_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('aisc_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                <?php elseif ($provider === 'google'): ?>
                                <option value="gemini-1.5-pro" <?php selected(get_option('aisc_model'), 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                                <option value="gemini-1.5-flash" <?php selected(get_option('aisc_model'), 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                                <option value="gemini-1.5-flash-8b" <?php selected(get_option('aisc_model'), 'gemini-1.5-flash-8b'); ?>>Gemini 1.5 Flash 8B</option>
                                <?php elseif ($provider === 'anthropic'): ?>
                                <option value="claude-3-5-sonnet-20241022" <?php selected(get_option('aisc_model'), 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                <option value="claude-3-opus-20240229" <?php selected(get_option('aisc_model'), 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
                                <option value="claude-3-haiku-20240307" <?php selected(get_option('aisc_model'), 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku</option>
                                <?php elseif ($provider === 'xai'): ?>
                                <option value="grok-2" <?php selected(get_option('aisc_model'), 'grok-2'); ?>>Grok-2</option>
                                <option value="grok-2-vision-1212" <?php selected(get_option('aisc_model'), 'grok-2-vision-1212'); ?>>Grok-2 Vision</option>
                                <option value="grok-beta" <?php selected(get_option('aisc_model'), 'grok-beta'); ?>>Grok Beta</option>
                                <?php elseif ($provider === 'deepseek'): ?>
                                <option value="deepseek-chat" <?php selected(get_option('aisc_model'), 'deepseek-chat'); ?>>DeepSeek Chat</option>
                                <option value="deepseek-coder" <?php selected(get_option('aisc_model'), 'deepseek-coder'); ?>>DeepSeek Coder</option>
                                <?php elseif ($provider === 'qwen'): ?>
                                <option value="qwen-plus" <?php selected(get_option('aisc_model'), 'qwen-plus'); ?>>Qwen Plus</option>
                                <option value="qwen-turbo" <?php selected(get_option('aisc_model'), 'qwen-turbo'); ?>>Qwen Turbo</option>
                                <option value="qwen-long" <?php selected(get_option('aisc_model'), 'qwen-long'); ?>>Qwen Long</option>
                                <?php elseif ($provider === 'openrouter'): ?>
                                <option value="openai/gpt-4o" <?php selected(get_option('aisc_model'), 'openai/gpt-4o'); ?>>GPT-4o (via OpenRouter)</option>
                                <option value="openai/gpt-4o-mini" <?php selected(get_option('aisc_model'), 'openai/gpt-4o-mini'); ?>>GPT-4o Mini (via OpenRouter)</option>
                                <option value="anthropic/claude-3.5-sonnet" <?php selected(get_option('aisc_model'), 'anthropic/claude-3.5-sonnet'); ?>>Claude 3.5 Sonnet (via OpenRouter)</option>
                                <option value="google/gemini-pro-1.5" <?php selected(get_option('aisc_model'), 'google/gemini-pro-1.5'); ?>>Gemini Pro 1.5 (via OpenRouter)</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
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
            'aisc_ai_provider',
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