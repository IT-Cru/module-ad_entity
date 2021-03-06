<?php

namespace Drupal\ad_entity_dfp\Plugin\ad_entity\AdView;

use Drupal\ad_entity\Entity\AdEntityInterface;
use Drupal\ad_entity\Plugin\AdViewBase;

/**
 * Default view handler plugin for DFP advertisement.
 *
 * @AdView(
 *   id = "dfp_default",
 *   label = "Default view for a DFP tag",
 *   library = "ad_entity_dfp/default_view",
 *   allowedTypes = {
 *     "dfp"
 *   }
 * )
 */
class DFPView extends AdViewBase {

  /**
   * {@inheritdoc}
   */
  public function build(AdEntityInterface $entity) {
    return [
      '#theme' => 'dfp_default',
      '#ad_entity' => $entity,
    ];
  }

}
