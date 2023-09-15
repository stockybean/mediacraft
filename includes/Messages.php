<?php

class Messages {
    private $messages = [];

    public function addMessage($context, $message, $type, $severity) {
        $this->messages[] = ['title' => $context, 'message' => $message, 'type' => $type, 'severity' => $severity];
    }


    public function getMessages($type = '', $severity = '') {

        $requested_messages = array_filter($this->messages, function($message) use ($type, $severity) {
            if( isset($type) && $message['type'] === $type ) return true;
            if( isset($severity) && $message['severity'] === $severity ) return true;
            return false;
        });

        if( empty($requested_messages) ) return false;

        switch ($type) {
            case 'generating':
                $type_title = 'Sizes intentionally skipped';
                break;
            
            case 'rendering':
                $type_title = 'Sizes that did not get rendered';
                break;

            default:
                $type_title = 'All Messages';
                break;
        }

        $output = '<div class="message-container">';
        $output .= '<h6>' . $type_title . '</h6>';
        $output .= '<ul class="message-list">';

        foreach( $requested_messages as $message ) {
            $output .= $this->messageMarkup($message);
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }

    private function messageMarkup($message) {
        return "<li class=\"message {$message['severity']}\"><span>{$message['title']}</span>{$message['message']}</li>";
    }
}
