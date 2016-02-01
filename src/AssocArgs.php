<?php

namespace WP_HTTP_Command;

class AssocArgs
{
    protected $as;

    protected $status;

    protected $realm = 'external';

    protected $scheme = 'http';

    protected $payload;

    public function __construct(array $args = [])
    {
        $this->args = $args;
        $this->fill($args);
    }

    protected function fill(array $args)
    {
        foreach ($args as $key => $value) {
            $this->$key = $value;
        }
    }

    public function __get($prop)
    {
        return $this->$prop;
    }
}
