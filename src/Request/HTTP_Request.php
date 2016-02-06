<?php

namespace WP_HTTP_Command\Request;

use WP_CLI;
use WP_HTTP;
use WP_Http_Cookie;
use WP_Session_Tokens;
use WP_HTTP_Command\AssocArgs;

abstract class HTTP_Request
{
    const METHOD = 'GET';

    protected static $classes = [
        'head' => HTTP_HEAD_Request::class,
        'get'  => HTTP_GET_Request::class,
        'post' => HTTP_POST_Request::class,
    ];

    protected $defaults = [
        'redirection' => 0,
        'timeout'     => 60,
    ];

    public function __construct(WP_HTTP $http, $uri, AssocArgs $args)
    {
        $this->http = $http;
        $this->uri  = $uri;
        $this->args = $args;
    }

    public static function make($method, $uri, array $args)
    {
        $class = static::get_class(strtolower($method));

        return new $class(new WP_Http, $uri, new AssocArgs($args));
    }

    public static function get_class($method)
    {
        if (! isset(static::$classes[ $method ])) {
            return null;
        }

        return static::$classes[ $method ];
    }

    public function output()
    {
        $response = $this->dispatch($this->url());

        if (is_wp_error($response)) {
            WP_CLI::error($response->get_error_message());

            return;
        }

        if ($this->args->status) {
            $this->output_response_status($response);

            return;
        }

        echo $this->format_output($response);
    }

    /**
     * @param array $response
     *
     * @return null
     */
    protected function format_output(array $response)
    {
        return @$response[ 'body' ];
    }

    protected function output_response_status(array $response)
    {
        $method = 'success';

        $http_code = $response['response']['code'];
        $message   = $response['response']['message'];

        if (500 <= $http_code) {
            $method = 'error';
        }
        if (300 <= $http_code) {
            $method = 'warning';
        }
        if (300 <= $http_code && $http_code < 400) {
            $message .= ' | Location: ' . $response['headers']['location'];
        }

        \WP_CLI::$method("$http_code $message");
    }

    /**
     * Get the full URL for the request
     *
     * @return mixed
     */
    protected function url()
    {
        $uri = $this->uri;

        switch ($this->args->realm) {
            case 'home':
                return home_url($uri, $this->args->scheme);
            case 'admin':
                return admin_url($uri, $this->args->scheme);
        }

        if ( ! parse_url($uri, PHP_URL_SCHEME)) {
            return "http://$uri";
        }

        return set_url_scheme($uri, $this->args->scheme);
    }

    /**
     * Is the request for a secure URL?
     *
     * @return bool
     */
    protected function is_https()
    {
        if ('https' === $this->args->scheme) {
            return true;
        }

        return (bool)'https' === parse_url($this->uri, PHP_URL_SCHEME);
    }

    protected function dispatch($url)
    {
        return $this->http->request($url, $this->get_http_args());
    }

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

    protected function is_domestic_realm()
    {
        return in_array($this->args->realm, ['home', 'admin']);
    }

    protected function get_user_id($user = 0)
    {
        if (! $user) {
            return 0;
        }

        if (is_numeric($user)) {
            return (int) $user;
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
        $secure   = $this->is_https();
        /** This filter is documented in wp-includes/pluggable.php */
        $expiration = time() + apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember);
        $expire     = 0;

        // Frontend cookie is secure when the auth cookie is secure and the site's home URL is forced HTTPS.
        $secure_logged_in_cookie = $secure && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME);

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
