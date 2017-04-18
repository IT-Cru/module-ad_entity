<?php

namespace Drupal\ad_entity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ad_entity\Plugin\AdContextManager;

/**
 * Plugin implementation of the 'ad_entity_node_with_tree_context' formatter.
 *
 * @FieldFormatter(
 *   id = "ad_entity_node_with_tree_context",
 *   label = @Translation("Context from node including taxonomy tree aggregation"),
 *   field_types = {
 *     "ad_entity_context"
 *   }
 * )
 */
class NodeWithTreeAggregationContextFormatter extends ContextFormatterBase {

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    switch ($field_definition->getTargetEntityTypeId()) {
      case 'node':
        return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('ad_entity.context_manager'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term')
    );
  }

  /**
   * Constructs a new AdContextFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\ad_entity\Plugin\AdContextManager $context_manager
   *   The Advertising context manager.
   * @param \Drupal\taxonomy\TermStorageInterface $term_storage
   *   The term storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AdContextManager $context_manager, TermStorageInterface $term_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $context_manager);
    $this->termStorage = $term_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    /** @var \Drupal\node\Entity\Node $node */
    $node = $items->getEntity();
    $nid = $node->id();
    $aggregated_items = [$items];
    $node_terms = $this->termStorage->getNodeTerms([$nid]);
    if (!empty($node_terms[$nid])) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      foreach ($node_terms[$nid] as $tid => $term) {
        $field_definitions = $term->getFieldDefinitions();
        $parents = $this->termStorage->loadAllParents($tid);
        /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
        foreach ($field_definitions as $definition) {
          if ($definition->getType() == 'ad_entity_context') {
            $field_name = $definition->getName();
            if ($term_items = $term->get($field_name)) {
              $aggregated_items[] = $term_items;
            }
            foreach ($parents as $parent) {
              if ($parent_items = $parent->get($field_name)) {
                $aggregated_items[] = $parent_items;
              }
            }
          }
        }
      }
    }

    foreach ($aggregated_items as $items) {
      foreach ($items as $item) {
        $element[] = $this->buildElementFromItem($item);
      }
    }

    return $element;
  }

}