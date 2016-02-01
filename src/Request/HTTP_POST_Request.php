<?php

namespace WP_HTTP_Command;

class HTTP_POST_Request extends HTTP_Request
{
    const METHOD = 'POST';

    protected function get_http_args()
    {
        $args = parent::get_http_args();

        $args['body'] = $this->args->payload;

        return $args;
    }

    protected function format_output(array $response)
    {
        return $response['body'];
    }
}
