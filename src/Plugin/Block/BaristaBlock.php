<?php

namespace Drupal\draco_coffee\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\draco_coffee\DracoCoffeeManager;
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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Draco Coffee Manager service.
   *
   * @var \Drupal\draco_coffee\DracoCoffeeManager
   */
  protected $dracoCoffeeManager;

  /**
   * The State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The cofiguration factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\draco_coffee\DracoCoffeeManager $draco_coffee_manager
   *   The Draco Coffee Manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user, DracoCoffeeManager $draco_coffee_manager, EntityTypeManagerInterface $entity_type_manager, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->dracoCoffeeManager = $draco_coffee_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('draco_coffee.manager'),
      $container->get('entity_type.manager'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['user:' . $this->currentUser->id(), 'draco_coffee:state']);
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
    /** @var AccountProxyInterface $barista */
    $barista = $this->dracoCoffeeManager->getBarista();
    if (!$barista) {
      $markup = $this->t('No coffee being served');
    }
    elseif ($barista->id() == $this->currentUser->getAccount()->id()) {
      $markup = $this->t('go make COFFEEEEEEEEEEEEEEEEEEEE!');
    }
    else {
      $markup = $this->t('User @name is making COFFEEEEEEEEEEEEEEEEEEEE', [
        '@name' => $barista->getAccountName(),
      ]);
    }

    $build['content'] = [
      '#markup' => $markup,
    ];

    return $build;
  }

}
