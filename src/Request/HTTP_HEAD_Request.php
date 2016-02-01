<?php

namespace WP_HTTP_Command;

class HTTP_HEAD_Request extends HTTP_Request
{
    const METHOD = 'HEAD';

    protected function format_output(array $response)
    {
        $headers = [];

        foreach ($response['headers'] as $header => $value) {
            if (is_array($value)) {
                $value = join("\n", $value);
            }

            $headers[ ] = compact('header', 'value');
        }

        $args = (array) $this->args;
        $formatter = new \WP_CLI\Formatter($args, ['header', 'value']);

        ob_start();

        $formatter->display_items($headers);

        return ob_get_clean();
    }
}
