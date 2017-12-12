<?php

namespace Drupal\draco_coffee;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * DracoCoffeeManager service.
 */
class DracoCoffeeManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a DracoCoffeeManager object.
   *
   * @param \Drupal\draco_coffee\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The Entity Type Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Time service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, TimeInterface $time) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * Cron implementation.
   */
  public function cron() {
    $config = $this->configFactory->get('draco_coffee.settings');
    if (empty($this->state->get('draco_coffee.start')) ||
      empty($config->get('role')) ||
      empty($config->get('pots')) ||
      empty($config->get('interval'))) {
      return;
    }

    if ($this->isRefillNeeded()) {
      $this->setBarista();
      $this->increasePotCounter();

    }
    else {
      $this->clear();
    }
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['draco_coffee:state']);
  }

  /**
   * Checks if the pot needs to be refilled.
   *
   * We compare the number of pots already made against the configured value,
   * and then we check if there are remaining pots to make by comparing
   * the time of the last pot with the current time.
   *
   * @return bool
   *   TRUE if a new pot is needed. FALSE otherwise.
   */
  protected function isRefillNeeded() {
    $config = $this->configFactory->get('draco_coffee.settings');
    $next_pot = $this->state->get('draco_coffee.start') + ($this->state->get('draco_coffee.pot_counter') * 3600);
    return (($this->state->get('draco_coffee.pot_counter') < $config->get('pots')) &&
      ($next_pot < $this->time->getCurrentTime()));
  }

  /**
   * Sets the editor to become barista for the next coffee pot.
   */
  protected function setBarista() {
    $config = $this->configFactory->get('draco_coffee.settings');
    $candidates = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('status', 1)
      ->condition('roles', $config->get('role'))
      ->execute();
    $victim = $candidates[array_rand($candidates)];
    $this->state->set('draco_coffee.barista', $victim);
  }

  /**
   * Increases the pot counter.
   */
  protected function increasePotCounter() {
    $this->state->set('draco_coffee.pot_counter', $this->state->get('draco_coffee.pot_counter') + 1);
  }

  /**
   * Checks if a given user identifier is the barista.
   *
   * @param int $uid
   *   A user identifier.
   *
   * @return bool
   *   TRUE if the given user is the barista. FALSE otherwise.
   */
  public function isBarista($uid) {
    return $uid == $this->state->get('draco_coffee.barista');
  }

  /**
   * Clears status variables after the last pot has been drunk.
   */
  protected function clearLastPot() {
    $config = $this->configFactory->get('draco_coffee.settings');
    // Check if the last pot has been served.
    if ($this->state->get('draco_coffee.pot_counter') < $config->get('pots')) {
      return;
    }

    // Check if an hour has passed since the last pot was served.
    $last_pot = $this->state->get('draco_coffee.start') +
      ($this->state->get('draco_coffee.pot_counter') * 3600) +
      3600;
    if ($last_pot < $this->time->getCurrentTime()) {
      $this->state->delete('draco_coffee.start');
      $this->state->delete('draco_coffee.barista');
    }
  }

  /**
   * Checks if there is coffee being served at the current time.
   *
   * @return bool
   *   TRUE if coffee is being served. FALSE otherwise.
   */
  public function isCoffeeBeingServed() {
    return !empty($this->state->get('draco_coffee.barista'));
  }

  /**
   * Returns the user account of the current barista.
   */
  public function getBarista() {
    return $this->entityTypeManager->getStorage('user')
      ->load($this->state->get('draco_coffee.barista'));
  }

}
