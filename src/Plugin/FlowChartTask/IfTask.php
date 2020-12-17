<?php

namespace Drupal\dyniva_flowchart\Plugin\FlowChartTask;

use Drupal\Core\Plugin\PluginBase;
use Drupal\dyniva_flowchart\Plugin\FlowChartTaskInterface;

/**
 * @Plugin(
 *   id = "if",
 *   task_description = @Translation("The If section."),
 * )
 */
class IfTask extends PluginBase implements FlowChartTaskInterface {

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#daa520';
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription()
  {
    return t('If Section');
  }
  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'drawfalselineto', 'removelines', 'remove'];
  }

}
