<?php

class AISC_API_Client {

    private $endpoint;
    private $timeout = 30;

    public function __construct() {
        $this->endpoint = defined('AISC_API_ENDPOINT') ? AISC_API_ENDPOINT : get_option('aisc_backend_url', 'http://localhost:8000');
    }

    public function set_endpoint($endpoint) {
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function chat($message, $session_id = '') {
        $url = $this->endpoint . '/api/v1/chat';

        $body = array(
            'message' => $message,
            'session_id' => $session_id
        );

        return $this->post($url, $body);
    }

    public function get_knowledge_stats() {
        $url = $this->endpoint . '/api/v1/knowledge/stats';
        return $this->get($url);
    }

    public function train_from_url($urls, $max_depth = 2) {
        $url = $this->endpoint . '/api/v1/train/url';
        
        $body = array(
            'urls' => $urls,
            'max_depth' => $max_depth
        );

        return $this->post($url, $body);
    }

    public function train_from_manual($content, $title = '', $content_type = 'faq') {
        $url = $this->endpoint . '/api/v1/train/manual';

        $body = array(
            'content' => $content,
            'title' => $title,
            'content_type' => $content_type
        );

        return $this->post($url, $body);
    }

    public function upload_file($file_path, $category = 'documents') {
        $url = $this->endpoint . '/api/v1/upload/file';

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found');
        }

        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name);

        $boundary = wp_generate_password(24);
        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        );

        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: ' . $file_type['type'] . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="category"' . "\r\n\r\n";
        $body .= $category . "\r\n";
        $body .= '--' . $boundary . '--' . "\r\n";

        return $this->post($url, $body, $headers);
    }

    public function retrain() {
        $url = $this->endpoint . '/api/v1/train/retrain';
        return $this->post($url, array());
    }

    public function clear_knowledge($category = null) {
        $url = $this->endpoint . '/api/v1/knowledge/clear';
        $body = $category ? array('category' => $category) : array();
        return $this->delete($url, $body);
    }

    private function request($method, $url, $body = null, $headers = array()) {
        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => array_merge(array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ), $headers)
        );

        if ($body !== null) {
            if (is_array($body)) {
                $args['body'] = json_encode($body);
            } else {
                $args['body'] = $body;
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('api_error', 'API request failed', array('code' => $code, 'body' => $body));
        }

        return json_decode($body, true);
    }

    private function get($url, $headers = array()) {
        return $this->request('GET', $url, null, $headers);
    }

    private function post($url, $body = array(), $headers = array()) {
        return $this->request('POST', $url, $body, $headers);
    }

    private function delete($url, $body = array()) {
        return $this->request('DELETE', $url, $body);
    }

    public function test_connection() {
        $url = $this->endpoint . '/health';
        $response = $this->get($url);

        if (is_wp_error($response)) {
            return false;
        }

        return isset($response['status']) && $response['status'] === 'healthy';
    }
}