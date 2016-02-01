<?php

if ( defined('WP_CLI') && class_exists('WP_CLI', false)) {
    WP_CLI::add_command('http', WP_HTTP_Command\HTTP_Command::class);
}
