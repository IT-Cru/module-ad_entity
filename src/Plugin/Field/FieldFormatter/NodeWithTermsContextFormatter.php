<?php

namespace Drupal\ad_entity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'node_with_terms_context' formatter.
 *
 * @FieldFormatter(
 *   id = "node_with_terms_context",
 *   label = @Translation("Context from node with taxonomy (without trees)"),
 *   field_types = {
 *     "ad_entity_context"
 *   }
 * )
 */
class NodeWithTermsContextFormatter extends TaxonomyContextFormatterBase {

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
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $appliance_mode = $this->getSetting('appliance_mode');

    /** @var \Drupal\node\Entity\Node $node */
    $node = $items->getEntity();
    $nid = $node->id();
    $aggregated_items = [$items];
    $node_terms = $this->termStorage->getNodeTerms([$nid]);
    if (!empty($node_terms[$nid])) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      foreach ($node_terms[$nid] as $term) {
        $field_definitions = $term->getFieldDefinitions();
        /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
        foreach ($field_definitions as $definition) {
          if ($definition->getType() == 'ad_entity_context') {
            $field_name = $definition->getName();
            if ($term_items = $term->get($field_name)) {
              $aggregated_items[] = $term_items;
            }
          }
        }
      }
    }

    if ($appliance_mode == 'frontend' || $appliance_mode == 'both') {
      foreach ($aggregated_items as $items) {
        foreach ($items as $item) {
          $element[] = $this->buildElementFromItem($item);
        }
      }
    }

    if ($appliance_mode == 'backend' || $appliance_mode == 'both') {
      foreach ($aggregated_items as $items) {
        foreach ($items as $item) {
          $this->addItemToContextData($item);
        }
      }
    }

    return $element;
  }

}
