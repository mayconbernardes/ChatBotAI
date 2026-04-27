<?php

class AISC_OpenAI {

    private $api_key;
    private $model;

    public function __construct() {
        $this->api_key = get_option('aisc_api_key', '');
        $this->model = get_option('aisc_model', 'gpt-3.5-turbo');
    }

    public function chat($message, $conversation_history = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key not configured');
        }

        $messages = array();

        $system_prompt = $this->get_system_prompt();
        if ($system_prompt) {
            $messages[] = array(
                'role' => 'system',
                'content' => $system_prompt
            );
        }

        foreach ($conversation_history as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }

        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );

        $response = $this->call_api($messages);

        return $response;
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

    private function call_api($messages) {
        $temperature = floatval(get_option('aisc_temperature', 0.7));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $this->model,
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

    public function is_configured() {
        return !empty($this->api_key);
    }
}