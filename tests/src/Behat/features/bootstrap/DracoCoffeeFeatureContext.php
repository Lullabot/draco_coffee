<?php

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Drupal\block\Entity\Block;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\user\Entity\Role;
use \Drupal\user\Entity\User;

/**
 * Behat steps for testing the draco_coffee module.
 *
 * @codingStandardsIgnoreStart
 */
class DracoCoffeeFeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * Setup for the test suite, enable some required modules and add content
   * title.
   *
   * @BeforeSuite
   */
  public static function prepare(BeforeSuiteScope $scope) {
    /** @var \Drupal\Core\Extension\ModuleHandler $moduleHandler */
    $moduleHandler = \Drupal::service('module_handler');
    if (!$moduleHandler->moduleExists('draco_coffee')) {
      \Drupal::service('module_installer')->install(['draco_coffee']);
    }

    // Also uninstall the inline form errors module for easier testing.
    if ($moduleHandler->moduleExists('inline_form_errors')) {
      \Drupal::service('module_installer')->uninstall(['inline_form_errors']);
    }

    // Make the Barista block visible.
    self::placeBlock('local_actions_block');
  }

  /**
   * @Given there are editors Tom and Iggy
   */
  public function thereAreEditorsTomAndIggy() {
    // Create the editor role.
    $data = ['id' => 'BDD_editor', 'label' => 'Editor'];
    $role = Role::create($data);
    $role->grantPermission('administer draco_coffee configuration');
    $role->save();

    // Create users.
    $users = [
      (object) [
        'name' => 'tom.waits',
        'pass' => 'claphands',
        'roles' => 'BDD_editor',
      ],
      (object) [
        'name' => 'iggy.pop',
        'pass' => 'passenger',
        'roles' => 'BDD_editor',
      ],
    ];
    foreach ($users as $user) {
      $account = $this->userCreate($user);
    }
  }

  /**
   * @When I hack the state to set Tom as the barista
   */
  public function iHackTheStateToSetTomAsTheBarista() {
    $result = \Drupal::entityTypeManager()->getStorage('user')
      ->getQuery()
      ->condition('name', 'tom.waits')
      ->execute();
    $uid = reset($result);
    \Drupal::state()->set('draco_coffee.barista', $uid);
    \Drupal::service('draco_coffee.pot')->invalidateCache();
  }

  /**
   * @When I force the next pot to be made
   */
  public function iForceTheNextPotToBeMade() {
    \Drupal::state()->set('draco_coffee.start', 1);
    \Drupal::service('draco_coffee.pot')->makeCoffee();
  }


  /**
   * Copied from BlockCreationTrait::placeBlock.
   *
   * Creates a block instance based on default settings.
   *
   * @param string $plugin_id
   *   The plugin ID of the block type for this block instance.
   * @param array $settings
   *   (optional) An associative array of settings for the block entity.
   *   Override the defaults by specifying the key and value in the array, for
   *   example:
   *
   * @code
   *     $this->drupalPlaceBlock('system_powered_by_block', array(
   *       'label' => t('Hello, world!'),
   *     ));
   * @endcode
   *   The following defaults are provided:
   *   - label: Random string.
   *   - ID: Random string.
   *   - region: 'sidebar_first'.
   *   - theme: The default theme.
   *   - visibility: Empty array.
   *
   * @return \Drupal\block\Entity\Block
   *   The block entity.
   *
   * @todo
   *   Add support for creating custom block instances.
   */
  public static function placeBlock($plugin_id) {
    $values = [
      // A unique ID for the block instance.
      'id' => 'BDD_barista_block',
      // The plugin block id as defined in the class.
      'plugin' => 'draco_coffee_barista',
      // The machine name of the theme region.
      'region' => 'sidebar_first',
      'settings' => [
        'label' => 'BDD Barista',
      ],
      // The machine name of the theme.
      'theme' => 'bartik',
      'visibility' => [],
      'weight' => 100,
    ];
    $block = Block::create($values);
    $block->save();
  }

  /**
   * Removes test blocks.
   *
   * @AfterSuite
   */
  public static function cleanupBlocks() {
    $storage = \Drupal::entityTypeManager()->getStorage('block');
    $ids = $storage->getQuery()
      ->condition('id', 'BDD_', 'STARTS_WITH')
      ->execute();
    $entities = $storage->loadMultiple($ids);
    $storage->delete($entities);
  }

  /**
   * Removes test roles.
   *
   * @AfterScenario
   */
  public static function cleanupRoles() {
    $storage = \Drupal::entityTypeManager()->getStorage('user_role');
    $ids = $storage->getQuery()
      ->condition('id', 'BDD_', 'STARTS_WITH')
      ->execute();
    $entities = $storage->loadMultiple($ids);
    $storage->delete($entities);
  }

}
