<?php

class AISC_AI_Provider {

    private $provider;
    private $api_key;

    public function __construct() {
        $this->provider = get_option('aisc_ai_provider', 'openai');
        $this->api_key = get_option('aisc_api_key', '');
    }

    public function chat($message, $conversation_history = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key not configured');
        }

        $messages = array();

        $system_prompt = $this->get_system_prompt();
        if ($system_prompt) {
            $messages[] = array('role' => 'system', 'content' => $system_prompt);
        }

        foreach ($conversation_history as $msg) {
            $messages[] = array('role' => $msg['role'], 'content' => $msg['content']);
        }

        $messages[] = array('role' => 'user', 'content' => $message);

        switch ($this->provider) {
            case 'openai':
                return $this->call_openai($messages);
            case 'google':
            case 'gemini':
                return $this->call_google($message, $conversation_history);
            case 'anthropic':
            case 'claude':
                return $this->call_anthropic($messages);
            case 'xai':
            case 'grok':
                return $this->call_xai($messages);
            case 'deepseek':
                return $this->call_deepseek($messages);
            case 'qwen':
                return $this->call_qwen($messages);
            case 'openrouter':
                return $this->call_openrouter($messages);
            default:
                return $this->call_openai($messages);
        }
    }

    private function get_system_prompt() {
        $tone = get_option('aisc_ai_tone', 'professional');
        
        $prompts = array(
            'professional' => 'You are a professional business assistant. Be formal, concise, and helpful.',
            'friendly' => 'You are a friendly assistant. Be casual, warm, and approachable.',
            'sales' => 'You are a sales assistant. Be persuasive, friendly, and focus on helping users find solutions.',
            'technical' => 'You are a technical support assistant. Be precise, detailed, and thorough.'
        );

        $base = isset($prompts[$tone]) ? $prompts[$tone] : $prompts['professional'];
        
        $custom_prompt = get_option('aisc_custom_prompt', '');
        if (!empty($custom_prompt)) {
            $base .= ' ' . $custom_prompt;
        }

        return $base;
    }

    private function call_openai($messages) {
        $model = get_option('aisc_model', 'gpt-3.5-turbo');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        return new WP_Error('no_response', 'No response from API');
    }

    private function call_google($message, $history) {
        $model = get_option('aisc_model', 'gemini-1.5-flash');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $contents = array();
        foreach ($history as $msg) {
            $contents[] = array('role' => $msg['role'], 'parts' => array(array('text' => $msg['content'])));
        }
        $contents[] = array('role' => 'user', 'parts' => array(array('text' => $message)));

        $args = array(
            'method' => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'contents' => $contents,
                'generationConfig' => array(
                    'temperature' => $temperature,
                    'maxOutputTokens' => 1000
                )
            )),
            'timeout' => 30
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->api_key;
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error('no_response', 'No response from Google API');
    }

    private function call_anthropic($messages) {
        $model = get_option('aisc_model', 'claude-3-haiku-20240307');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $system = '';
        $anthropic_messages = array();
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $anthropic_messages[] = $msg;
            }
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'max_tokens' => 1000,
                'temperature' => $temperature,
                'system' => $system,
                'messages' => $anthropic_messages
            )),
            'timeout' => 30
        );

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['content'][0]['text'])) {
            return $body['content'][0]['text'];
        }

        return new WP_Error('no_response', 'No response from Claude API');
    }

    private function call_xai($messages) {
        $model = get_option('aisc_model', 'grok-beta');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        );

        $response = wp_remote_post('https://api.x.ai/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        return new WP_Error('no_response', 'No response from Grok API');
    }

    private function call_deepseek($messages) {
        $model = get_option('aisc_model', 'deepseek-chat');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        );

        $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        return new WP_Error('no_response', 'No response from DeepSeek API');
    }

    private function call_qwen($messages) {
        $model = get_option('aisc_model', 'qwen-turbo');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        );

        $response = wp_remote_post('https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        return new WP_Error('no_response', 'No response from Qwen API');
    }

    private function call_openrouter($messages) {
        $model = get_option('aisc_model', 'openai/gpt-3.5-turbo');
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => 'AI Smart Chatbot'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        );

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        return new WP_Error('no_response', 'No response from OpenRouter API');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    public function get_provider_name() {
        $providers = array(
            'openai' => 'OpenAI (GPT)',
            'google' => 'Google Gemini',
            'anthropic' => 'Anthropic Claude',
            'xai' => 'xAI Grok',
            'deepseek' => 'DeepSeek',
            'qwen' => 'Alibaba Qwen',
            'openrouter' => 'OpenRouter (multi-model)'
        );
        
        return isset($providers[$this->provider]) ? $providers[$this->provider] : $this->provider;
    }
}