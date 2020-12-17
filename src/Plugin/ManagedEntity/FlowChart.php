<?php
namespace Drupal\dyniva_flowchart\Plugin\ManagedEntity;

use Drupal\dyniva_core\Plugin\ManagedEntityPluginBase;
use Drupal\dyniva_core\Entity\ManagedEntity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageInterface;

/**
 * ManagedEntity Plugin.
 *
 * @ManagedEntityPlugin(
 *  id = "flowchart",
 *  label = @Translation("Flow Chart"),
 *  weight = 10
 * )
 *
 */
class FlowChart extends ManagedEntityPluginBase{
  /**
   * @inheritdoc
   */
  public function buildPage(ManagedEntity $managedEntity, EntityInterface $entity){
    $form = \Drupal::formBuilder()->getForm('\Drupal\dyniva_flowchart\Form\FlowChartBuilderForm', $managedEntity, $entity);
    return $form;
  }
  /**
   * @inheritdoc
   */
  public function getPageTitle(ManagedEntity $managedEntity, EntityInterface $entity){
    return $this->pluginDefinition['label'] . ' ' . $entity->label();
  }
  /**
   * @inheritdoc
   */
  public function isMenuTask(ManagedEntity $managedEntity){
    return TRUE;
  }
  /**
   * @inheritdoc
   */
  public function isMenuAction(ManagedEntity $managedEntity){
    return FALSE;
  }
}
