<?php

class AISC_Admin {

    private $settings;
    private $knowledge_base;
    private $api_client;
    private $menu_slug = 'ai-smart-chatbot';

    public function __construct($settings, $knowledge_base, $api_client) {
        $this->settings = $settings;
        $this->knowledge_base = $knowledge_base;
        $this->api_client = $api_client;

        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_post_aisc_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_aisc_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_aisc_get_stats', array($this, 'handle_get_stats'));
        add_action('wp_ajax_aisc_train_url', array($this, 'handle_train_url'));
        add_action('wp_ajax_aisc_train_manual', array($this, 'handle_train_manual'));
    }

    public function add_menu_pages() {
        add_menu_page(
            __('AI Smart Chatbot', 'ai-smart-chatbot'),
            __('AI Chatbot', 'ai-smart-chatbot'),
            'manage_options',
            $this->menu_slug,
            array($this, 'render_dashboard'),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'ai-smart-chatbot'),
            __('Dashboard', 'ai-smart-chatbot'),
            'manage_options',
            $this->menu_slug,
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            $this->menu_slug,
            __('AI Settings', 'ai-smart-chatbot'),
            __('AI Settings', 'ai-smart-chatbot'),
            'manage_options',
            $this->menu_slug . '-ai-settings',
            array($this, 'render_ai_settings')
        );

        add_submenu_page(
            $this->menu_slug,
            __('Knowledge Base', 'ai-smart-chatbot'),
            __('Knowledge Base', 'ai-smart-chatbot'),
            'manage_options',
            $this->menu_slug . '-knowledge',
            array($this, 'render_knowledge_base')
        );

        add_submenu_page(
            $this->menu_slug,
            __('Design Settings', 'ai-smart-chatbot'),
            __('Design', 'ai-smart-chatbot'),
            'manage_options',
            $this->menu_slug . '-design',
            array($this, 'render_design_settings')
        );
    }

    public function render_dashboard() {
        $stats = $this->knowledge_base->get_stats();
        $connection_status = $this->api_client->test_connection();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Smart Chatbot', 'ai-smart-chatbot'); ?></h1>
            
            <div class="aisc-admin-cards">
                <div class="aisc-card">
                    <h3><?php esc_html_e('Status', 'ai-smart-chatbot'); ?></h3>
                    <p class="aisc-status <?php echo $connection_status ? 'connected' : 'disconnected'; ?>">
                        <?php echo $connection_status ? __('Connected', 'ai-smart-chatbot') : __('Disconnected', 'ai-smart-chatbot'); ?>
                    </p>
                    <p><?php esc_html_e('Backend API connection status', 'ai-smart-chatbot'); ?></p>
                </div>

                <div class="aisc-card">
                    <h3><?php esc_html_e('Documents', 'ai-smart-chatbot'); ?></h3>
                    <p class="aisc-stat-number"><?php echo isset($stats['total_documents']) ? $stats['total_documents'] : 0; ?></p>
                    <p><?php esc_html_e('Total documents in knowledge base', 'ai-smart-chatbot'); ?></p>
                </div>

                <div class="aisc-card">
                    <h3><?php esc_html_e('Chunks', 'ai-smart-chatbot'); ?></h3>
                    <p class="aisc-stat-number"><?php echo isset($stats['total_chunks']) ? $stats['total_chunks'] : 0; ?></p>
                    <p><?php esc_html_e('Text chunks indexed', 'ai-smart-chatbot'); ?></p>
                </div>

                <div class="aisc-card">
                    <h3><?php esc_html_e('Categories', 'ai-smart-chatbot'); ?></h3>
                    <p class="aisc-stat-number"><?php echo is_array($stats['categories']) ? count($stats['categories']) : 0; ?></p>
                    <p><?php esc_html_e('Content categories', 'ai-smart-chatbot'); ?></p>
                </div>
            </div>

            <div class="aisc-card aisc-full-width">
                <h3><?php esc_html_e('Quick Actions', 'ai-smart-chatbot'); ?></h3>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=' . $this->menu_slug . '-knowledge'); ?>" class="button button-primary">
                        <?php esc_html_e('Add Knowledge', 'ai-smart-chatbot'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=' . $this->menu_slug . '-ai-settings'); ?>" class="button">
                        <?php esc_html_e('Configure AI', 'ai-smart-chatbot'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=' . $this->menu_slug . '-design'); ?>" class="button">
                        <?php esc_html_e('Customize Design', 'ai-smart-chatbot'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    public function render_ai_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Settings', 'ai-smart-chatbot'); ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="aisc_save_settings">
                <?php wp_nonce_field('aisc_save_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Backend URL', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="url" name="aisc_backend_url" value="<?php echo esc_attr(get_option('aisc_backend_url', 'http://localhost:8000')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The URL of your AI backend API', 'ai-smart-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('AI Provider', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_api_provider">
                                <option value="openai" <?php selected(get_option('aisc_api_provider'), 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="anthropic" <?php selected(get_option('aisc_api_provider'), 'anthropic'); ?>>Anthropic (Claude)</option>
                                <option value="google" <?php selected(get_option('aisc_api_provider'), 'google'); ?>>Google (Gemini)</option>
                                <option value="xai" <?php selected(get_option('aisc_api_provider'), 'xai'); ?>>xAI (Grok)</option>
                                <option value="openrouter" <?php selected(get_option('aisc_api_provider'), 'openrouter'); ?>>OpenRouter</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('API Key', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="password" name="aisc_api_key" value="<?php echo esc_attr(get_option('aisc_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Model', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="text" name="aisc_model" value="<?php echo esc_attr(get_option('aisc_model', 'gpt-3.5-turbo')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('e.g., gpt-3.5-turbo, gpt-4, claude-3-sonnet', 'ai-smart-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Temperature', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="number" name="aisc_temperature" value="<?php echo esc_attr(get_option('aisc_temperature', 0.7)); ?>" min="0" max="2" step="0.1">
                            <p class="description"><?php esc_html_e('Control randomness (0 = focused, 2 = creative)', 'ai-smart-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('AI Tone', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_ai_tone">
                                <option value="professional" <?php selected(get_option('aisc_ai_tone'), 'professional'); ?>><?php esc_html_e('Professional', 'ai-smart-chatbot'); ?></option>
                                <option value="friendly" <?php selected(get_option('aisc_ai_tone'), 'friendly'); ?>><?php esc_html_e('Friendly', 'ai-smart-chatbot'); ?></option>
                                <option value="sales" <?php selected(get_option('aisc_ai_tone'), 'sales'); ?>><?php esc_html_e('Sales', 'ai-smart-chatbot'); ?></option>
                                <option value="technical" <?php selected(get_option('aisc_ai_tone'), 'technical'); ?>><?php esc_html_e('Technical', 'ai-smart-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_html_e('Save Changes', 'ai-smart-chatbot'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_knowledge_base() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Knowledge Base', 'ai-smart-chatbot'); ?></h1>
            
            <div class="aisc-admin-tabs">
                <button class="aisc-tab active" data-tab="upload"><?php esc_html_e('Upload Files', 'ai-smart-chatbot'); ?></button>
                <button class="aisc-tab" data-tab="urls"><?php esc_html_e('Add URLs', 'ai-smart-chatbot'); ?></button>
                <button class="aisc-tab" data-tab="manual"><?php esc_html_e('Manual Input', 'ai-smart-chatbot'); ?></button>
            </div>
            
            <div class="aisc-tab-content" id="tab-upload">
                <div class="aisc-card">
                    <h3><?php esc_html_e('Upload Documents', 'ai-smart-chatbot'); ?></h3>
                    <form id="aisc-upload-form" enctype="multipart/form-data">
                        <input type="file" name="file" accept=".pdf,.docx,.txt,.csv">
                        <select name="category">
                            <option value="documents"><?php esc_html_e('Documents', 'ai-smart-chatbot'); ?></option>
                            <option value="faq"><?php esc_html_e('FAQ', 'ai-smart-chatbot'); ?></option>
                            <option value="product"><?php esc_html_e('Products', 'ai-smart-chatbot'); ?></option>
                        </select>
                        <button type="submit" class="button-primary"><?php esc_html_e('Upload', 'ai-smart-chatbot'); ?></button>
                    </form>
                    <div id="aisc-upload-result"></div>
                </div>
            </div>
            
            <div class="aisc-tab-content" id="tab-urls" style="display:none;">
                <div class="aisc-card">
                    <h3><?php esc_html_e('Train from URLs', 'ai-smart-chatbot'); ?></h3>
                    <form id="aisc-url-form">
                        <textarea name="urls" rows="5" class="large-text" placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
                        <p class="description"><?php esc_html_e('Enter one URL per line', 'ai-smart-chatbot'); ?></p>
                        <input type="number" name="max_depth" value="2" min="1" max="5">
                        <label><?php esc_html_e('Crawl depth', 'ai-smart-chatbot'); ?></label>
                        <button type="submit" class="button-primary"><?php esc_html_e('Train from URLs', 'ai-smart-chatbot'); ?></button>
                    </form>
                    <div id="aisc-url-result"></div>
                </div>
            </div>
            
            <div class="aisc-tab-content" id="tab-manual" style="display:none;">
                <div class="aisc-card">
                    <h3><?php esc_html_e('Add Manual Content', 'ai-smart-chatbot'); ?></h3>
                    <form id="aisc-manual-form">
                        <input type="text" name="title" class="regular-text" placeholder="<?php esc_attr_e('Title (optional)', 'ai-smart-chatbot'); ?>">
                        <select name="content_type">
                            <option value="faq"><?php esc_html_e('FAQ', 'ai-smart-chatbot'); ?></option>
                            <option value="document"><?php esc_html_e('Document', 'ai-smart-chatbot'); ?></option>
                            <option value="product"><?php esc_html_e('Product Info', 'ai-smart-chatbot'); ?></option>
                        </select>
                        <textarea name="content" rows="10" class="large-text" placeholder="<?php esc_attr_e('Enter your content here...', 'ai-smart-chatbot'); ?>"></textarea>
                        <button type="submit" class="button-primary"><?php esc_html_e('Add Content', 'ai-smart-chatbot'); ?></button>
                    </form>
                    <div id="aisc-manual-result"></div>
                </div>
            </div>
            
            <div class="aisc-card" style="margin-top: 20px;">
                <h3><?php esc_html_e('Knowledge Base Actions', 'ai-smart-chatbot'); ?></h3>
                <button id="aisc-retrain" class="button button-primary"><?php esc_html_e('Re-train All', 'ai-smart-chatbot'); ?></button>
                <button id="aisc-clear" class="button"><?php esc_html_e('Clear Knowledge Base', 'ai-smart-chatbot'); ?></button>
            </div>
        </div>
        <?php
    }

    public function render_design_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Design Settings', 'ai-smart-chatbot'); ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="aisc_save_settings">
                <?php wp_nonce_field('aisc_save_settings'); ?>
                
                <h2><?php esc_html_e('Chat Widget Position & Behavior', 'ai-smart-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Display Mode', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="aisc_display_mode" value="floating" <?php checked(get_option('aisc_display_mode', 'floating'), 'floating'); ?>>
                                    <?php esc_html_e('Floating Widget (chat bubble)', 'ai-smart-chatbot'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="aisc_display_mode" value="embedded" <?php checked(get_option('aisc_display_mode'), 'embedded'); ?>>
                                    <?php esc_html_e('Embedded (inside page content)', 'ai-smart-chatbot'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="aisc_display_mode" value="fullscreen" <?php checked(get_option('aisc_display_mode'), 'fullscreen'); ?>>
                                    <?php esc_html_e('Fullscreen Assistant', 'ai-smart-chatbot'); ?>
                                </label>
                            </fieldset>
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
                            <fieldset>
                                <label>
                                    <input type="radio" name="aisc_initial_state" value="closed" <?php checked(get_option('aisc_initial_state', 'closed'), 'closed'); ?>>
                                    <?php esc_html_e('Closed (only icon visible)', 'ai-smart-chatbot'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="aisc_initial_state" value="open" <?php checked(get_option('aisc_initial_state'), 'open'); ?>>
                                    <?php esc_html_e('Open (chat visible on load)', 'ai-smart-chatbot'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Auto-Open Delay', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="number" name="aisc_auto_open_delay" value="<?php echo esc_attr(get_option('aisc_auto_open_delay', 0)); ?>" min="0" max="60" step="1" style="width: 80px;">
                            <span><?php esc_html_e('seconds (0 = disabled)', 'ai-smart-chatbot'); ?></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Animations', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="aisc_bounce_animation" value="1" <?php checked(get_option('aisc_bounce_animation'), 1); ?>>
                                    <?php esc_html_e('Bounce animation on page load', 'ai-smart-chatbot'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="aisc_pulse_animation" value="1" <?php checked(get_option('aisc_pulse_animation'), 1); ?>>
                                    <?php esc_html_e('Pulse notification animation on icon', 'ai-smart-chatbot'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Mobile Behavior', 'ai-smart-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Mobile Position', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <select name="aisc_mobile_position">
                                <option value="bottom-center" <?php selected(get_option('aisc_mobile_position', 'bottom-center'), 'bottom-center'); ?>><?php esc_html_e('Bottom Center', 'ai-smart-chatbot'); ?></option>
                                <option value="fullscreen" <?php selected(get_option('aisc_mobile_position'), 'fullscreen'); ?>><?php esc_html_e('Fullscreen', 'ai-smart-chatbot'); ?></option>
                                <option value="same" <?php selected(get_option('aisc_mobile_position'), 'same'); ?>><?php esc_html_e('Same as desktop', 'ai-smart-chatbot'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Enable on Mobile', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="checkbox" name="aisc_enable_mobile" value="1" <?php checked(get_option('aisc_enable_mobile', 1), 1); ?>>
                            <label><?php esc_html_e('Show chatbot on mobile devices', 'ai-smart-chatbot'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Appearance', 'ai-smart-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Primary Color', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="color" name="aisc_primary_color" value="<?php echo esc_attr(get_option('aisc_primary_color', '#0073aa')); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Secondary Color', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="color" name="aisc_secondary_color" value="<?php echo esc_attr(get_option('aisc_secondary_color', '#ffffff')); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Chat Title', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="text" name="aisc_chat_title" value="<?php echo esc_attr(get_option('aisc_chat_title', 'AI Assistant')); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Greeting Message', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <textarea name="aisc_greeting" rows="3" class="large-text"><?php echo esc_textarea(get_option('aisc_greeting', 'Hello! How can I help you?')); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Avatar URL', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="url" name="aisc_avatar_url" value="<?php echo esc_attr(get_option('aisc_avatar_url')); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Dark Mode', 'ai-smart-chatbot'); ?></th>
                        <td>
                            <input type="checkbox" name="aisc_dark_mode" value="1" <?php checked(get_option('aisc_dark_mode'), 1); ?>>
                            <label><?php esc_html_e('Enable dark mode by default', 'ai-smart-chatbot'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_html_e('Save Changes', 'ai-smart-chatbot'); ?>">
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
            'aisc_backend_url',
            'aisc_api_provider',
            'aisc_api_key',
            'aisc_model',
            'aisc_temperature',
            'aisc_position',
            'aisc_display_mode',
            'aisc_widget_style',
            'aisc_primary_color',
            'aisc_secondary_color',
            'aisc_chat_title',
            'aisc_greeting',
            'aisc_avatar_url',
            'aisc_ai_tone',
            'aisc_dark_mode',
            'aisc_initial_state',
            'aisc_auto_open_delay',
            'aisc_bounce_animation',
            'aisc_pulse_animation',
            'aisc_mobile_position',
            'aisc_enable_mobile'
        );

        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            } elseif (in_array($setting, array('aisc_dark_mode', 'aisc_bounce_animation', 'aisc_pulse_animation', 'aisc_enable_mobile'))) {
                update_option($setting, isset($_POST[$setting]) ? 1 : 0);
            }
        }

        wp_redirect(add_query_arg('message', 'saved', wp_get_referer()));
        exit;
    }

    public function handle_file_upload() {
        check_ajax_referer('aisc_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }

        $result = $this->knowledge_base->save_uploaded_file($_FILES['file']);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        $upload_result = $this->knowledge_base->upload_document($result['file_id']);

        wp_send_json_success($upload_result);
    }

    public function handle_get_stats() {
        check_ajax_referer('aisc_nonce', 'nonce');

        $stats = $this->knowledge_base->get_stats();
        wp_send_json_success($stats);
    }

    public function handle_train_url() {
        check_ajax_referer('aisc_nonce', 'nonce');

        $urls = explode("\n", sanitize_textarea_field($_POST['urls']));
        $urls = array_filter(array_map('trim', $urls));
        
        $max_depth = intval($_POST['max_depth'] ?? 2);

        $result = $this->knowledge_base->add_urls($urls, $max_depth);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function handle_train_manual() {
        check_ajax_referer('aisc_nonce', 'nonce');

        $content = sanitize_textarea_field($_POST['content']);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'faq');

        $result = $this->knowledge_base->add_manual_content($content, $title, $content_type);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
}