Feature: The http timeout can be passed as an option to any of the sub-commands.

  Scenario: If the timeout is set, then the request will be cut off after that time.
    Given a WP install
    And a running web server
    And a timeout-simulation.php file:
      """
      <?php sleep(10);
      """
    When I try `wp http get /timeout-simulation.php --realm=home --timeout=1`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: cURL error 28: Operation timed out
      """