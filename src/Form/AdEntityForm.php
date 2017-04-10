<?php

namespace Drupal\ad_entity\Form;

use Drupal\ad_entity\Entity\AdEntityInterface;
use Drupal\ad_entity\Plugin\AdViewInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ad_entity\Plugin\AdTypeManager;
use Drupal\ad_entity\Plugin\AdViewManager;

/**
 * Class AdEntityForm.
 *
 * @package Drupal\ad_entity\Form
 */
class AdEntityForm extends EntityForm {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The Advertising type manager.
   *
   * @var \Drupal\ad_entity\Plugin\AdTypeManager
   */
  protected $typeManager;

  /**
   * The Advertising view manager.
   *
   * @var \Drupal\ad_entity\Plugin\AdViewManager
   */
  protected $viewManager;

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\ad_entity\Plugin\AdTypeManager $ad_type_manager
   *   The Advertising type manager.
   * @param \Drupal\ad_entity\Plugin\AdViewManager $ad_type_manager
   *   The Advertising view manager.
   */
  public function __construct(FormBuilderInterface $form_builder, AdTypeManager $ad_type_manager, AdViewManager $ad_view_manager) {
    $this->formBuilder = $form_builder;
    $this->typeManager = $ad_type_manager;
    $this->viewManager = $ad_view_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('ad_entity.type_manager'),
      $container->get('ad_entity.view_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $type_ids = array_keys($this->typeManager->getDefinitions());
    if (empty($type_ids)) {
      return [
        '#markup' => $this->t('For being able to create Advertising entities, you need to install some Advertising plugins first.'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ad_entity\Entity\AdEntityInterface $ad_entity */
    $ad_entity = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $ad_entity->label(),
      '#description' => $this->t("Label for the Advertising entity."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $ad_entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ad_entity\Entity\AdEntity::load',
      ],
      '#disabled' => !$ad_entity->isNew(),
    ];

    // TODO Fieldset for type, fieldset for view settings (when multiple views are allowed).
    $type_definitions = $this->typeManager->getDefinitions();
    $options = [];
    foreach ($type_definitions as $id => $definition) {
      $options[$id] = $definition['label'];
    }
    $form['type_plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t("Advertising type"),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('type_plugin_id') ?
        $form_state->getValue('type_plugin_id') : $ad_entity->get('type_plugin_id'),
      '#empty_value' => '',
      '#ajax' => [
        'callback' => [$this, 'thirdPartyChange'],
        'wrapper' => 'third-party-config',
        'effect' => 'fade',
        'method' => 'replaceWith',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];
    $form['third_party'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'third-party-config'],
    ];
    if (($type_id = $form_state->getValue('type_plugin_id')) || ($type_id = $ad_entity->get('type_plugin_id'))) {
      /** @var \Drupal\ad_entity\Plugin\AdTypeInterface $type */
      if ($type = $this->typeManager->createInstance($type_id)) {
        // Get all allowed view handlers for this type.
        $view_definitions = array_keys($this->viewManager->getDefinitions());
        $allowed_views  = [];
        foreach ($view_definitions as $view_id) {
          /** @var \Drupal\ad_entity\Plugin\AdViewInterface $handler */
          $handler = $this->viewManager->createInstance($view_id);
          if (in_array($type_id, $handler->allowedTypes())) {
            $allowed_views[$view_id] = $handler->getPluginDefinition()['label'];
          }
        }

        if (!empty($allowed_views)) {
          $form['third_party']['view_plugin_id'] = [
            '#type' => 'select',
            '#title' => $this->t("View handler"),
            '#tree' => FALSE,
            '#options' => $allowed_views,
            '#required' => TRUE,
            '#default_value' => $form_state->getValue('view_plugin_id') ?
              $form_state->getValue('view_plugin_id') : $ad_entity->get('view_plugin_id'),
            '#empty_value' => '',
          ];
        }

        // Expose the type-specific configuration.
        $definition = $type_definitions[$type_id];
        $type_form = [
          '#type' => 'fieldset',
          '#collapsible' => FALSE,
          '#collapsed' => FALSE,
          '#attributes' => ['id' => 'type-plugin-' . $type_id],
          '#title' => $this->t("Configuration for the @type type", ['@type' => $definition['label']]),
        ] + $type->entityConfigForm($form, $form_state, $ad_entity);
        $form['third_party'][$type_id] = $type_form;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $ad_entity = $this->entity;
    $status = $ad_entity->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Advertising entity.', [
          '%label' => $ad_entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Advertising entity.', [
          '%label' => $ad_entity->label(),
        ]));
    }
    $form_state->setRedirectUrl($ad_entity->toUrl('collection'));
  }

  /**
   * Rebuild callback for changed third party configs.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The third party form part.
   */
  public function thirdPartyChange(array &$form, FormStateInterface $form_state) {
    $form = $this->formBuilder->rebuildForm($this->getFormId(), $form_state, $form);
    return $form['third_party'];
  }

}
