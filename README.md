WP-CLI HTTP Command
=====================

```
NAME

  wp http

DESCRIPTION

  Perform an HTTP request using the WP HTTP API

SYNOPSIS

  wp http <command>

SUBCOMMANDS

  get       Perform a GET request to the given URI
  head      Perform a HEAD request to the given URI
  post      Perform a POST request to the given URI
```

## Subcommands
Each subcommand matches to the HTTP method you wish to perform, and they all accept just 1 required parameter, the `<uri>`.

#### `<uri>`
The URI can be either a full URL, or just the request URI (requires `realm` option)

## Options

#### Realm
```
--realm=<home|admin>
```
The realm option restricts the request to the current site, and refers to which area of the site the request URI is relative to.

To perform a GET request on the home page of the site:
```
wp http get / --realm=home
```
or, to perform a GET request on the Plugins page of wp-admin:
```
wp http get plugins.php --realm=admin
```
> Since admin requests require authentication, we need to specify which user the request _should be performed as_.

#### Authentecated User
```
--as=<user>
```
When making any request to an admin realm, (unless you're specifically testing that the request is being redirected to the login page) you will want to specify which user the request should be made as.  This is as simple as providing the user ID, username, or email.

Load the admin Dashboard
```
wp http get / --realm=admin --as=1
```

#### Scheme
```
--scheme=<http|https>
```
Force a particular scheme on the request URL.

## Flags
`--status`  
When set, only the HTTP status code and message are output.  If the response is a 3xx, the location header is included as well.

`--ssl-verify` `--no-ssl-verify`  
Whether or not SSL certificates should be checked against WordPress' bundled certificates.  Good for working around self-signed certificates.
Default: `on`

# Installation
Due to the nature of this command, it cannot be installed as a plugin and thus would not be useful to install as a project dependency.  Instead, the HTTP Command is installed as a Composer package, and loaded by the local user's wp-cli config.

 
Create the wp-cli user directory, if it doesn't already exist
```
mkdir ~/.wp-cli && cd ~/.wp-cli
```
Require the http command package
```
composer require --prefer-dist aaemnnosttv/wp-cli-http-command:"^0.1"
```
Create the wp-cli config file, if it doesn't exist yet
```
touch config.yml
```
Load composer.  Edit the `config.yml` file and make sure `vendor/autoload.php` is being loaded under `require` like so
```
require:
  - vendor/autoload.php
```

That's it!  Now you should see the `http` command as an option when you run `wp` from any directory.


##### Inspiration
- [jkbrzt/httpie](https://github.com/jkbrzt/httpie)
