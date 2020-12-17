<?php

namespace Drupal\dyniva_flowchart\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * The Task Interface.
 */
interface FlowChartTaskInterface extends PluginInspectionInterface {

  /**
   * Get the task's short description.  Useful for things like labels.
   */
  public function shortDescription();
  /**
   * Returns the task's defined colours.  This is useful if you want to let the tasks decide on what colours to paint themselves in the UI.
   */
  public function getTaskColours();

  /**
   * Returns an array of consistenly keyed array elements that define what this task can do in the template builder.
   * Elements are:
   * edit, drawlineto, drawfalselineto, removelines, remove.
   */
  public function getTemplateBuilderCapabilities();

}
