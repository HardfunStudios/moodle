<?php

class block_reactions extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_reactions');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = 'Hello world!!!';

        return $this->content;
    }
}
