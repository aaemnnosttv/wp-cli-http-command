<?php

namespace WP_HTTP_Command;

use WP_HTTP_Command\Request\HTTP_Request;

/**
 * Perform an HTTP request using the WP HTTP API
 */
class HTTP_Command extends \WP_CLI_Command
{

    /**
     * Perform a HEAD request to the given URI
     *
     * ## OPTIONS
     *
     * <uri>
     * : The home/admin URI fragment or full URL if external
     *
     * [--realm=<home|admin>]
     * : The realm the URI is targeting
     *
     * [--as=<user>]
     * : the authenticated user to perform the request as (home and admin realms only)
     *
     * [--scheme=<scheme>]
     * : URL scheme to enforce
     *
     * [--status]
     * : Only return the HTTP status code
     *
     * [--format=<table|json|csv>]
     * : Output format for headers. Default: table
     *
     * @synopsis <uri> [--realm=<home|admin>] [--as=<user>] [--scheme=<scheme>] [--status] [--format=<table|json|csv>] [--ssl-verify]
     */
    public function head($_, $assoc)
    {
        $this->dispatch(HTTP_Request::make('head', $_[0], $assoc));
    }

    /**
     * Perform a GET request to the given URI
     *
     * ## OPTIONS
     *
     * <uri>
     * : The home/admin URI fragment or full URL if external
     *
     * [--realm=<home|admin>]
     * : The realm the URI is targeting
     *
     * [--as=<user>]
     * : the authenticated user to perform the request as (home and admin realms only)
     *
     * [--scheme=<scheme>]
     * : URL scheme to enforce
     *
     * [--status]
     * : Only return the HTTP status code
     *
     * @synopsis <uri> [--realm=<home|admin>] [--as=<user>] [--scheme=<scheme>] [--status] [--ssl-verify]
     */
    public function get($_, $assoc)
    {
        $this->dispatch(HTTP_Request::make('get', $_[0], $assoc));
    }

    /**
     * Perform a POST request to the given URI
     *
     * ## OPTIONS
     *
     * <uri>
     * : The home/admin URI fragment or full URL if external
     *
     * [--payload=<data>]
     * : The data to send as the POST body
     *
     * [--realm=<home|admin>]
     * : The realm the URI is targeting
     *
     * [--as=<user>]
     * : the authenticated user to perform the request as (home and admin realms only)
     *
     * [--scheme=<scheme>]
     * : URL scheme to enforce
     *
     * [--status]
     * : Only return the HTTP status code
     *
     *
     * @synopsis <uri> [--payload=<data>] [--realm=<home|admin>] [--as=<user>] [--scheme=<scheme>] [--status] [--ssl-verify]
     */
    public function post($_, $assoc)
    {
        $this->dispatch(HTTP_Request::make('post', $_[0], $assoc));
    }

    /**
     * Fire off the request and display the output
     *
     * @param  HTTP_Request $request
     * @return void
     */
    protected function dispatch(HTTP_Request $request)
    {
        $request->output();
    }
}
