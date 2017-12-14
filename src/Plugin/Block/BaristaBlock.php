<?php

namespace Drupal\draco_coffee\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\draco_coffee\DracoCoffeePot;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Barista' block.
 *
 * @Block(
 *   id = "draco_coffee_barista",
 *   admin_label = @Translation("Draco Coffee barista"),
 *   category = @Translation("Draco Coffee")
 * )
 */
class BaristaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Draco Coffee Pot service.
   *
   * @var \Drupal\draco_coffee\DracoCoffeePot
   */
  protected $dracoCoffeePot;

  /**
   * Constructs a new ExampleBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\draco_coffee\DracoCoffeePot $draco_coffee_pot
   *   The Draco Coffee Pot service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,AccountProxyInterface $current_user, DracoCoffeePot $draco_coffee_pot) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->dracoCoffeePot = $draco_coffee_pot;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('draco_coffee.pot')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['user:' . $this->currentUser->id(), $this->dracoCoffeePot->getCacheTag()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'administer draco_coffee configuration');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\Core\Session\AccountProxyInterface $barista */
    $barista = $this->dracoCoffeePot->getBarista();
    if (empty($barista->id())) {
      $markup = $this->t('No coffee being served');
    }
    elseif ($barista->id() == $this->currentUser->getAccount()->id()) {
      $markup = $this->t('go make the @ordinal pot of COFFEEEEEEEEEEEEEEEEEEEE!', [
        '@ordinal' => $this->dracoCoffeePot->getPotCounter(),
      ]);
    }
    else {
      $markup = $this->t('User @name is making the @ordinal pot of COFFEEEEEEEEEEEEEEEEEEEE', [
        '@name' => $barista->getAccountName(),
        '@ordinal' => $this->dracoCoffeePot->getPotCounter(),
      ]);
    }

    $build['content'] = [
      '#markup' => $markup,
    ];

    return $build;
  }

}
