<?php

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('http', WP_HTTP_Command\HTTP_Command::class);
}
