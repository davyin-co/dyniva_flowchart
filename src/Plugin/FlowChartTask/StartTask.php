<?php

namespace Drupal\dyniva_flowchart\Plugin\FlowChartTask;

use Drupal\Core\Plugin\PluginBase;
use Drupal\dyniva_flowchart\Plugin\FlowChartTaskInterface;

/**
 * @Plugin(
 *   id = "start",
 *   task_description = @Translation("The start section."),
 * )
 */
class StartTask extends PluginBase implements FlowChartTaskInterface {

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#00ff00';
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription()
  {
    return t('Start Section');
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['drawlineto', 'removelines'];
  }

}
