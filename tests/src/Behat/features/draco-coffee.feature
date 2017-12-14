@javascript @api
Feature: Draco Coffee test
  In order to make sure that editors have coffee
  As an authenticated user
  I need to be able to manage coffee pots

  Scenario: Configure a set of coffee pots and verify that they are shown
    Given there are editors Tom and Iggy
    When I am logged in as "tom.waits"
    And I am on "admin/config/system/draco-coffee"
    And I select "Editor" from "Role of the coffee makers"
    And I fill in "Number of times to refill the pot" with "5"
    And I press "Start"
    Then I should see "Coffee is on the way!"

  Scenario: Test the logic to pick a barista
    Given there are editors Tom and Iggy
    When I am logged in as "tom.waits"
    And I am on "admin/config/system/draco-coffee"
    And I select "Editor" from "Role of the coffee makers"
    And I fill in "Number of times to refill the pot" with "5"
    And I press "Start"
    And I hack the state to set Tom as the barista
    And am on "/"
    And I should see "go make the 1st pot of COFFEEEEEEEEEEEEEEEEEEEE!"
    And I am logged in as "iggy.pop"
    And I should see "User tom.waits is making the 1st pot of COFFEEEEEEEEEEEEEEEEEEEE"

  Scenario: Coffee block changes when cron runs
    Given there are editors Tom and Iggy
    When I am logged in as "tom.waits"
    And I am on "admin/config/system/draco-coffee"
    And I select "Editor" from "Role of the coffee makers"
    And I fill in "Number of times to refill the pot" with "5"
    And I press "Start"
    And am on "/"
    And I should see "1st pot"
    And I force the next pot to be made
    And am on "/"
    And I should see "2nd pot"
