<?php

namespace Drupal\draco_coffee\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\draco_coffee\DracoCoffeePot;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Draco Coffee settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Draco Coffee Pot service.
   *
   * @var \Drupal\draco_coffee\DracoCoffeePot
   */
  protected $dracoCoffeePot;

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
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Configuration Factory service.
   * @param \Drupal\draco_coffee\DracoCoffeePot $draco_coffee_pot
   *   The Draco Coffee Pot service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    DracoCoffeePot $draco_coffee_pot,
    EntityTypeManagerInterface $entity_type_manager,
    StateInterface $state
  ) {
    parent::__construct($config_factory);
    $this->dracoCoffeePot = $draco_coffee_pot;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('draco_coffee.pot'),
      $container->get('entity_type.manager'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'draco_coffee_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['draco_coffee.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('draco_coffee.settings');

    $roles = $this->getRoles();
    if (empty($roles)) {
      $form['role'] = [
        '#markup' => $this->t('Add the permission "Administer draco_coffee configuration" to a role before completing this form.'),
      ];
    }
    else {
      $form['role'] = [
        '#type' => 'select',
        '#title' => $this->t('Role of the coffee makers'),
        '#description' => $this->t('Select the role that will be used to find who will make each coffee pot.'),
        '#default_value' => $config->get('role'),
        '#options' => $roles,
      ];

      $form['pots'] = [
        '#type' => 'number',
        '#title' => $this->t('Number of times to refill the pot'),
        '#description' => $this->t('If a team needs coffee for 8 hours, then they would need 8 pots. Every hour a different user will be announced to refill the pot.'),
        '#default_value' => $config->get('pots'),
      ];

      $form['actions']['submit']['#value'] = $this->t('Start');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('draco_coffee.settings')
      ->set('role', $form_state->getValue('role'))
      ->set('pots', $form_state->getValue('pots'))
      ->save();

    $this->state->set('draco_coffee.start', time());
    $this->state->set('draco_coffee.pot_counter', 0);
    $this->dracoCoffeePot->setBarista();
    $this->dracoCoffeePot->increasePotCounter();
    $this->dracoCoffeePot->invalidateCache();

    drupal_set_message($this->t('Coffee is on the way!'));
  }

  /**
   * Helper function to get the barista roles.
   *
   * @return array
   *   An array of keyed roles.
   */
  protected function getRoles() {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple(NULL);
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    unset($roles['administrator']);

    $roles = array_filter($roles, function ($role) {
      return $role->hasPermission('administer draco_coffee configuration');
    });

    return array_map(function ($item) {
      return $item->label();
    }, $roles);
  }

}
