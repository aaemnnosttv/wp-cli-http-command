Feature: HTTP Get

  Scenario: It can get the target url.
    Given a WP install
    When I run `wp http get http://wp-cli.org --debug`
    Then STDOUT should contain:
      """
      <!DOCTYPE
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

  Scenario: It can accept a relative url for the given realm.
    Given a WP install
    When I run `wp http get / --realm=home --debug`
    And STDERR should contain:
      """
      [http] GET http://example.com/
      """
    When I run `wp http get / --realm=admin --debug`
    And STDERR should contain:
      """
      [http] GET http://example.com/wp-admin/
      """