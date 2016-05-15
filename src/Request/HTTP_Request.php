<?php

namespace WP_HTTP_Command\Request;

use WP_CLI;
use WP_HTTP;
use WP_Http_Cookie;
use WP_Session_Tokens;
use WP_HTTP_Command\AssocArgs;

/**
 * Class HTTP_Request
 * @package WP_HTTP_Command\Request
 */
abstract class HTTP_Request
{
    /**
     * The HTTP request method
     */
    const METHOD = 'GET';

    /**
     * @var array
     */
    protected static $classes = [
        'head' => HTTP_HEAD_Request::class,
        'get'  => HTTP_GET_Request::class,
        'post' => HTTP_POST_Request::class,
    ];

    /**
     * HTTP request defaults
     * @see \WP_HTTP
     * @var array
     */
    protected $defaults = [
        'redirection' => 0,
        'timeout'     => 60,
    ];

    /**
     * HTTP_Request constructor.
     *
     * @param WP_HTTP   $http
     * @param           $uri
     * @param AssocArgs $args
     */
    public function __construct(WP_HTTP $http, $uri, AssocArgs $args)
    {
        $this->http = $http;
        $this->uri  = $uri;
        $this->args = $args;
    }


    /**
     * @param       $method
     * @param       $uri
     * @param array $args
     *
     * @return mixed
     */
    public static function make($method, $uri, array $args)
    {
        $class = static::get_class(strtolower($method));

        return new $class(new WP_Http, $uri, new AssocArgs($args));
    }

    /**
     * Get the corresponding class for the given request method
     *
     * @param $method
     *
     * @return null|string   Class name
     */
    public static function get_class($method)
    {
        if ( ! isset(static::$classes[$method])) {
            return null;
        }

        return static::$classes[$method];
    }

    /**
     * Dispatch the request and display the output
     */
    public function output()
    {
        $response = $this->dispatch($this->url());

        if (is_wp_error($response)) {
            WP_CLI::error($response);
        }

        if ($this->args->status) {
            $this->output_response_status($response);

            return;
        }

        echo $this->format_output($response);
    }

    /**
     * Format the output for the response
     *
     * @param array $response
     *
     * @return string
     */
    protected function format_output(array $response)
    {
        return @$response['body'];
    }

    /**
     * Output the HTTP status code from the response
     *
     * @param array $response
     */
    protected function output_response_status(array $response)
    {
        $http_code = $response['response']['code'];
        $message   = $response['response']['message'];
        $method    = 'line';

        if (500 <= $http_code) {
            $method = 'error';
        }
        if (400 <= $http_code) {
            $method = 'warning';
        }
        if (300 <= $http_code && $http_code < 400) {
            $message .= "\n" . 'Location: ' . $response['headers']['location'];
        }
        if (200 <= $http_code && $http_code < 300) {
            $method = 'success';
        }

        WP_CLI::$method("$http_code $message");
    }

    /**
     * Get the full URL for the request
     *
     * @return mixed
     */
    protected function url()
    {
        $uri = $this->uri;

        /**
         * Return a url for a domestic realm
         *
         * WordPress home/admin urls use the proper protocol by default,
         * but this can be overriden with the --scheme=(http|https) option.
         */
        switch ($this->args->realm) {
            case 'home':
                return home_url($uri, $this->args->scheme);
            case 'admin':
                return admin_url($uri, $this->args->scheme ?: 'admin');
        }

        if (! parse_url($uri, PHP_URL_SCHEME)) {
            $uri = "http://$uri";
        }

        /**
         * If we've gotten this far, the url is for an foreign resource.
         * The scheme will be determined by the source uri but possibly altered by user input.
         */
        return set_url_scheme($uri, $this->is_https() ? 'https' : 'http');
    }

    /**
     * Is the request for a secure URL?
     *
     * @return bool
     */
    protected function is_https()
    {
        if ($this->args->scheme) {
            return 'https' == $this->args->scheme;
        }

        return 'https' == parse_url($this->uri, PHP_URL_SCHEME);
    }

    /**
     * @param $url
     *
     * @return mixed
     */
    protected function dispatch($url)
    {
        $args = $this->get_http_args();

        WP_CLI::debug(print_r(compact('url','args'), true));

        return $this->http->request($url, $args);
    }

    /**
     * @return array
     */
    protected function get_http_args()
    {
        $args = [
            'method'    => static::METHOD,
            'sslverify' => $this->get_flag('ssl-verify', true),
        ];

        if ($this->is_domestic_realm() && $this->args->as) {
            $user_id         = $this->get_user_id($this->args->as);
            $args['cookies'] = $this->make_auth_cookies($user_id);
        }

        return array_merge($this->defaults, $args);
    }

    /**
     * Is this a request for the current site?
     *
     * @return bool
     */
    protected function is_domestic_realm()
    {
        return in_array($this->args->realm, ['home', 'admin']);
    }

    /**
     * @param int $user
     *
     * @return int
     */
    protected function get_user_id($user = 0)
    {
        if ( ! $user) {
            return 0;
        }

        if (is_numeric($user)) {
            return (int)$user;
        }

        foreach (['login', 'email', 'slug'] as $field) {
            if ($found = get_user_by($field, $user)) {
                return $found->ID;
            }
        }

        return 0;
    }

    /**
     * Generate auth and login cookies for the given user
     *
     * @param        $user_id
     *
     * @return array
     */
    protected function make_auth_cookies($user_id)
    {
        $token    = '';
        $remember = '';
        $secure   = 'https' === parse_url($this->url(), PHP_URL_SCHEME);
        /** This filter is documented in wp-includes/pluggable.php */
        $expiration = time() + apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember);
        $expire     = 0;

        // Frontend cookie is secure when the auth cookie is secure and the site's home URL is forced HTTPS.
        $secure_logged_in_cookie = $secure;

        /**
         * Filter whether the connection is secure.
         *
         * @since 3.1.0
         *
         * @param bool $secure  Whether the connection is secure.
         * @param int  $user_id User ID.
         */
        $secure = apply_filters('secure_auth_cookie', $secure, $user_id);

        /**
         * Filter whether to use a secure cookie when logged-in.
         *
         * @since 3.1.0
         *
         * @param bool $secure_logged_in_cookie Whether to use a secure cookie when logged-in.
         * @param int  $user_id                 User ID.
         * @param bool $secure                  Whether the connection is secure.
         */
        $secure_logged_in_cookie = apply_filters('secure_logged_in_cookie', $secure_logged_in_cookie, $user_id,
            $secure);

        if ($secure) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme           = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme           = 'auth';
        }

        if ('' === $token) {
            $manager = WP_Session_Tokens::get_instance($user_id);
            $token   = $manager->create($expiration);
        }

        $auth_cookie      = wp_generate_auth_cookie($user_id, $expiration, $scheme, $token);
        $logged_in_cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);

        $cookies = [
            $this->make_cookie($auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure,
                true),
            $this->make_cookie($auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure,
                true),
            $this->make_cookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN,
                $secure_logged_in_cookie, true),
        ];

        if (COOKIEPATH != SITECOOKIEPATH) {
            $cookies[] = $this->make_cookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN,
                $secure_logged_in_cookie, true);
        }

        return $cookies;
    }

    /**
     * @param $name
     * @param $value
     * @param $expire
     * @param $path
     * @param $domain
     *
     * @return WP_Http_Cookie
     */
    protected function make_cookie($name, $value, $expire, $path, $domain)
    {
        return new WP_Http_Cookie(compact('name', 'value', 'expire', 'path', 'domain'));
    }

    /**
     * Get flag value
     *
     * @param      $flag
     * @param null $default
     *
     * @return mixed
     */
    protected function get_flag($flag, $default = null)
    {
        return \WP_CLI\Utils\get_flag_value($this->args->args, $flag, $default);
    }
}
