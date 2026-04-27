<?php

class AISC_Embeddings {

    private $api_client;

    public function __construct($api_client) {
        $this->api_client = $api_client;
    }

    public function get_embedding($text) {
        return array();
    }

    public function chunk_text($text, $chunk_size = 1000, $overlap = 200) {
        $text = trim($text);
        if (empty($text)) {
            return array();
        }

        $chunks = array();
        $length = strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = $start + $chunk_size;

            if ($end < $length) {
                $break_point = strrpos(substr($text, $start, $end - $start), "\n\n");
                if ($break_point === false) {
                    $break_point = strrpos(substr($text, $start, $end - $start), ". ");
                }
                if ($break_point === false) {
                    $break_point = strrpos(substr($text, $start, $end - $start), " ");
                }

                if ($break_point !== false && $break_point > 0) {
                    $end = $start + $break_point + 1;
                }
            }

            $chunk = substr($text, $start, $end - $start);
            $chunk = trim($chunk);

            if (!empty($chunk)) {
                $chunks[] = $chunk;
            }

            $start = $end - $overlap;

            if ($start < 0) {
                $start = 0;
            }
        }

        return $chunks;
    }
}