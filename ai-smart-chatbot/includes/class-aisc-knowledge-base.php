<?php

class AISC_Knowledge_Base {

    private $api_client;

    public function __construct($api_client) {
        $this->api_client = $api_client;
    }

    public function get_stats() {
        $result = $this->api_client->get_knowledge_stats();
        
        if (is_wp_error($result)) {
            return array(
                'total_documents' => 0,
                'total_chunks' => 0,
                'categories' => array()
            );
        }
        
        return $result;
    }

    public function add_urls($urls, $max_depth = 2) {
        if (empty($urls)) {
            return new WP_Error('empty_urls', 'No URLs provided');
        }

        return $this->api_client->train_from_url($urls, $max_depth);
    }

    public function add_manual_content($content, $title = '', $content_type = 'faq') {
        if (empty($content)) {
            return new WP_Error('empty_content', 'No content provided');
        }

        return $this->api_client->train_from_manual($content, $title, $content_type);
    }

    public function upload_document($file_id, $category = 'documents') {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/aisc_documents/' . $file_id;

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found');
        }

        return $this->api_client->upload_file($file_path, $category);
    }

    public function retrain() {
        return $this->api_client->retrain();
    }

    public function clear($category = null) {
        return $this->api_client->clear_knowledge($category);
    }

    public function save_uploaded_file($file, $category = 'documents') {
        $upload_dir = wp_upload_dir();
        $aisc_dir = $upload_dir['basedir'] . '/aisc_documents';

        if (!file_exists($aisc_dir)) {
            wp_mkdir_p($aisc_dir);
        }

        $file_id = wp_unique_filename($aisc_dir, $file['name']);
        $destination = $aisc_dir . '/' . $file_id;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return array(
                'file_id' => $file_id,
                'path' => $destination,
                'name' => $file['name']
            );
        }

        return new WP_Error('upload_failed', 'Failed to save uploaded file');
    }
}