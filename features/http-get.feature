Feature: HTTP Get

  Scenario: It can accept a relative url for the given realm.
    Given a WP install
    And a running web server
    And a index.html file:
      """
      All systems go!
      """
    When I run `wp http get /index.html --realm=home --debug`
    Then STDOUT should contain:
      """
      All systems go!
      """
    And STDERR should contain:
      """
      [http] GET http://127.0.0.1:8888/
      """
    When I run `wp http get / --realm=admin --status --debug`
    Then STDOUT should contain:
      """
      302 Found
      Location: http://127.0.0.1:8888/wp-login.php
      """
    And STDERR should contain:
      """
      [http] GET http://127.0.0.1:8888/wp-admin/
      """

  Scenario: It can get an external url.
    Given a WP install
    When I run `wp http get http://wp-cli.org --debug`
    Then STDOUT should contain:
      """
      </html>
      """
    And STDERR should contain:
      """
      [http] GET http://wp-cli.org
      """

  Scenario: It can limit the output to only the status code.
    Given a WP install
    When I run `wp http get http://wp-cli.org --status`
    Then STDOUT should contain:
      """
      Success: 200 OK
      """