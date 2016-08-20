Feature: HTTP Post

  Scenario: It can send a post request to the given url.
    Given a WP install
    When I run `wp http post http://wp-cli.org/dev/null --debug`
    Then STDERR should contain:
      """
      [http] POST http://wp-cli.org/dev/null
      """