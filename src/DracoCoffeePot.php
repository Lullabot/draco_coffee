<?php

namespace Drupal\draco_coffee;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\State\StateInterface;

/**
 * DracoCoffeePot service.
 */
class DracoCoffeePot {

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

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
   * Constructs a DracoCoffeePot object.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tag invalidator service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Time service.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, TimeInterface $time) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * Finds the next barista.
   */
  public function makeCoffee() {
    $config = $this->configFactory->get('draco_coffee.settings');
    if (empty($this->state->get('draco_coffee.start')) ||
      empty($config->get('role')) ||
      empty($config->get('pots'))) {
      return;
    }

    if ($this->isRefillNeeded()) {
      $this->setBarista();
      $this->increasePotCounter();
    }
    else {
      $this->clear();
    }

    $this->invalidateCache();
  }

  /**
   * Checks if the pot needs to be refilled.
   *
   * We compare the number of pots already made against the configured value,
   * and then we check if there are remaining pots to make by comparing
   * the time of the next pot with the current time.
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
  public function setBarista() {
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
  public function increasePotCounter() {
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
  protected function clear() {
    $config = $this->configFactory->get('draco_coffee.settings');
    // Check if the last pot has been served.
    if ($this->state->get('draco_coffee.pot_counter') < $config->get('pots')) {
      return;
    }

    // Check if an hour has passed since the last pot was served.
    $last_pot = $this->state->get('draco_coffee.start') +
      ($this->state->get('draco_coffee.pot_counter') * 3600);
    if ($last_pot < $this->time->getCurrentTime()) {
      $this->state->delete('draco_coffee.start');
      $this->state->delete('draco_coffee.barista');
    }

    // Invalidate cache since we changed the state.
    $this->invalidateCache();
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
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The user account or an empty account object if there is no barista.
   */
  public function getBarista() {
    $uid = $this->state->get('draco_coffee.barista');
    $account = NULL;

    if (!empty($uid)) {
      $account = $this->entityTypeManager->getStorage('user')
        ->load($this->state->get('draco_coffee.barista'));
    }

    if (empty($account)) {
      $account = new AccountProxy();
    }

    return $account;
  }

  /**
   * Returns the role for baristas.
   */
  public function getRole() {
    $config = $this->configFactory->get('draco_coffee.settings');
    return $config->get('role');
  }

  /**
   * Returns the custom tag used to mark the State API data as valid/invalid.
   *
   * @return string
   *   The custom cache tag.
   */
  public function getCacheTag() {
    return 'draco_coffee.barista';
  }

  /**
   * Invalidates the custom tag.
   */
  public function invalidateCache() {
    $this->cacheTagsInvalidator->invalidateTags([$this->getCacheTag()]);
    $this->state->resetCache();
  }

  /**
   * Returns the pot counter as an ordinal.
   *
   * @return string
   *   The current pot number as an ordinal.
   */
  public function getPotCounter() {
    $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    $number = $this->state->get('draco_coffee.pot_counter');

    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
      return $number . 'th';
    }
    else {
      return $number . $ends[$number % 10];
    }
  }

}
