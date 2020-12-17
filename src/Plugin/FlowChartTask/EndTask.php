<?php

namespace Drupal\dyniva_flowchart\Plugin\FlowChartTask;

use Drupal\Core\Plugin\PluginBase;
use Drupal\dyniva_flowchart\Plugin\FlowChartTaskInterface;

/**
 * @Plugin(
 *   id = "end",
 *   task_description = @Translation("The end section."),
 * )
 */
class EndTask extends PluginBase implements FlowChartTaskInterface {


  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#ff0000';
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription()
  {
    return t('End Section');
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'removelines', 'remove'];
  }

}
