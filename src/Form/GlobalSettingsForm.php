<?php

namespace Drupal\ad_entity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ad_entity\Plugin\AdTypeManager;

/**
 * Class GlobalSettingsForm.
 *
 * @package Drupal\ad_entity\Form
 */
class GlobalSettingsForm extends ConfigFormBase {

  /**
   * The Advertising type manager.
   *
   * @var \Drupal\ad_entity\Plugin\AdTypeManager
   */
  protected $typeManager;

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\ad_entity\Plugin\AdTypeManager $ad_type_manager
   *   The Advertising type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AdTypeManager $ad_type_manager) {
    parent::__construct($config_factory);
    $this->typeManager = $ad_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('ad_entity.type_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ad_entity.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ad_entity_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ad_entity.settings');

    $form['common'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#title' => $this->t('Settings for any type of advertisement'),
      '#weight' => 10,
    ];
    $default_behavior = $config->get('enable_responsive_behavior') !== NULL ?
      (int) $config->get('enable_responsive_behavior') : 1;
    $form['common']['enable_responsive_behavior'] = [
      '#type' => 'radios',
      '#title' => 'Responsive behavior',
      '#options' => [0 => $this->t("Disabled"), 1 => $this->t("Enabled")],
      '#description' => $this->t("When enabled, advertisement will be dynamically initialized on breakpoint changes (e.g. when switching from narrow to wide). When disabled, advertisement will only be initialized based on the initial breakpoint during page load."),
      '#default_value' => $default_behavior,
    ];

    $type_ids = array_keys($this->typeManager->getDefinitions());

    if (!empty($type_ids)) {
      $form['settings_tabs'] = [
        '#type' => 'vertical_tabs',
        '#default_tab' => 'edit-' . key($type_ids),
        '#weight' => 20,
      ];

      foreach ($type_ids as $type_id) {
        /** @var \Drupal\ad_entity\Plugin\AdTypeInterface $type */
        $type = $this->typeManager->createInstance($type_id);
        $label = $type->getPluginDefinition()['label'];
        $form[$type_id] = [
          '#type' => 'details',
          '#group' => 'settings_tabs',
          '#attributes' => ['id' => 'edit-' . $type_id],
          '#title' => $this->t("@type types", ['@type' => $label]),
          '#tree' => TRUE,
        ] + $type->globalSettingsForm($form, $form_state, $config);
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $config = $this->config('ad_entity.settings');

    $type_ids = array_keys($this->typeManager->getDefinitions());
    foreach ($type_ids as $type_id) {
      /** @var \Drupal\ad_entity\Plugin\AdTypeInterface $type */
      $type = $this->typeManager->createInstance($type_id);
      $type->globalSettingsValidate($form, $form_state, $config);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('ad_entity.settings');
    $config->set('enable_responsive_behavior', (bool) $form_state->getValue('enable_responsive_behavior'));

    $type_ids = array_keys($this->typeManager->getDefinitions());
    foreach ($type_ids as $type_id) {
      $values = $form_state->getValue($type_id, []);
      if (!empty($values)) {
        $config->set($type_id, $values);
      }

      /** @var \Drupal\ad_entity\Plugin\AdTypeInterface $type */
      $type = $this->typeManager->createInstance($type_id);
      $type->globalSettingsSubmit($form, $form_state, $config);
    }

    $config->save();
  }

}
