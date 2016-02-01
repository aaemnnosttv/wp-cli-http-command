<?php

namespace WP_HTTP_Command;

class HTTP_GET_Request extends HTTP_Request
{
    const METHOD = 'GET';

    protected function format_output(array $response)
    {
        return $response['body'];
    }
}
