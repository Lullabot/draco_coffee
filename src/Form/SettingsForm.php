<?php

namespace Drupal\draco_coffee\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\draco_coffee\DracoCoffeeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Draco Coffee settings for this site.
 */
class SettingsForm extends ConfigFormBase {

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
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Configuration Factory service.
   * @param \Drupal\draco_coffee\DracoCoffeeManager $draco_coffee_manager
   *   The Draco Coffee Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State API service.
   */
  public function __construct(
    \Drupal\Core\Config\ConfigFactoryInterface $config_factory, DracoCoffeeManager $draco_coffee_manager, StateInterface $state
  ) {
    parent::__construct($config_factory);
    $this->dracoCofeeManager = $draco_coffee_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('draco_coffee.manager'),
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
    $config = $this->config('draco_coffee.settings');

    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role of the coffee makers'),
      '#default_value' => $config->get('role'),
      '#options' => user_role_names(TRUE),
    ];

    $form['pots'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of pots'),
      '#default_value' => $config->get('pots'),
    ];

    $form['interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Interval to refill'),
      '#default_value' => $config->get('interval'),
      '#options' => [
        '1' => 'one hour',
        '2' => 'two hours',
        '3' => 'three hours',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('draco_coffee.settings')
      ->set('role', $form_state->getValue('role'))
      ->set('pots', $form_state->getValue('pots'))
      ->set('interval', $form_state->getValue('interval'))
      ->save();
    parent::submitForm($form, $form_state);

    $this->state->set('draco_coffee.start', time());
    $this->state->set('draco_coffee.pot_counter', 0);
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['draco_coffee:state']);
  }

}
