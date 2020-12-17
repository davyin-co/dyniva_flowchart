<?php

namespace Drupal\dyniva_flowchart\Plugin\FlowChartTask;

use Drupal\Core\Plugin\PluginBase;
use Drupal\dyniva_flowchart\Plugin\FlowChartTaskInterface;

/**
 * @Plugin(
 *   id = "interactive",
 *   task_description = @Translation("The interactive section."),
 * )
 */
class InteractiveTask extends PluginBase implements FlowChartTaskInterface {

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#0000ff';
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription()
  {
    return t('Interactive Section');
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
