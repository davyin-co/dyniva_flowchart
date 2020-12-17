<?php

namespace Drupal\dyniva_flowchart\Form;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\dyniva_flowchart\Ajax\FireJavascriptCommand;

/**
 *
 */
class MaestroTemplateBuilderForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'template_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Ajax callback to set a session variable that we use to then signal the modal dialog for task editing to appear.
   */
  public function editTask(array &$form, FormStateInterface $form_state) {
    // Set a task editing session variable here
    // TODO: if the task menu is changed to a dynamic ajax-generated form based on
    // task capabilities, we won't need this session and the Ajax URL in the edit task
    // callback can pass in the task ID.
    $_SESSION['dyniva_flowchart']['maestro_editing_task'] = $form_state->getValue('task_clicked');
    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    $response->addCommand(new FireJavascriptCommand('maestroEditTask', []));
    return $response;
  }

  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function moveTaskComplete(array &$form, FormStateInterface $form_state) {
    $taskMoved = $form_state->getValue('task_clicked');
    $top = $form_state->getValue('task_top');
    $left = $form_state->getValue('task_left');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $template->tasks[$taskMoved]['top'] = $top;
    $template->tasks[$taskMoved]['left'] = $left;
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroNoOp', []));
    return $response;
  }

  /**
   * Ajax callback to complete the line drawing routine when the second task has been selected.
   */
  public function drawLineComplete(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $templateTaskFrom = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    $templateTaskTo = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskTo);

    $nextsteps = explode(',', $templateTaskFrom['nextstep']);
    if (!array_search($taskTo, $nextsteps)) {
      // Add it to the task.
      if ($templateTaskFrom['nextstep'] != '') {
        $templateTaskFrom['nextstep'] .= ',';
      }
      $templateTaskFrom['nextstep'] .= $taskTo;
      $template->tasks[$taskFrom]['nextstep'] = $templateTaskFrom['nextstep'];
      // We want to force the designer to validate the template to be sure.
      $template->validated = FALSE;
      $template->save();
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback to signal the UI to go into line drawing mode.
   */
  public function drawLineTo(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    if ($task['tasktype'] == 'MaestroEnd') {
      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => t('You are not able to draw a line FROM an end task!')]));
      return $response;
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroDrawLineTo', ['taskid' => $taskFrom]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback to signal the UI to go into line drawing mode.
   */
  public function drawFalseLineTo(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    if ($task['tasktype'] == 'MaestroEnd') {
      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => t('You are not able to draw a line FROM an end task!')]));
      return $response;
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroDrawFalseLineTo', ['taskid' => $taskFrom]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback to complete the false line drawing routine when the second task has been selected.
   */
  public function drawFalseLineComplete(array &$form, FormStateInterface $form_state) {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);

    $templateTaskFrom = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskFrom);
    $templateTaskTo = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskTo);

    $nextsteps = explode(',', $templateTaskFrom['nextfalsestep']);
    if (!array_search($taskTo, $nextsteps)) {
      // Add it to the task.
      if ($templateTaskFrom['nextfalsestep'] != '') {
        $templateTaskFrom['nextfalsestep'] .= ',';
      }
      $templateTaskFrom['nextfalsestep'] .= $taskTo;
      $template->tasks[$taskFrom]['nextfalsestep'] = $templateTaskFrom['nextfalsestep'];
      // We want to force the designer to validate the template to be sure.
      $template->validated = FALSE;
      $template->save();
    }

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * Ajax callback to remove a task from the template.
   */
  public function removeTaskComplete(array &$form, FormStateInterface $form_state) {
    $taskToRemove = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');
    $response = new AjaxResponse();
    if ($taskToRemove == 'start') {
      // can't delete the start task!
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => t('You are not able to delete a Start task')]));
      return $response;
    }

    $returnValue = MaestroEngine::removeTemplateTask($templateMachineName, $taskToRemove);
    if ($returnValue === FALSE) {
      $response->addCommand(new FireJavascriptCommand('maestroSignalError', ['message' => t('There was an error removing the Task. Unable to Complete the removal')]));
      return $response;
    }
    else {
      MaestroEngine::setTemplateToUnvalidated($templateMachineName);
      $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
      $response->addCommand(new FireJavascriptCommand('maestroRemoveTask', ['task' => $taskToRemove]));
      return $response;
    }
  }

  /**
   * Ajax callback to remove the lines pointing to and from a task.
   */
  public function removeLines(array &$form, FormStateInterface $form_state) {
    $taskToRemoveLines = $form_state->getValue('task_clicked');
    $templateMachineName = $form_state->getValue('template_machine_name');

    $template = MaestroEngine::getTemplate($templateMachineName);
    $templateTask = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskToRemoveLines);
    $pointers = MaestroEngine::getTaskPointersFromTemplate($templateMachineName, $taskToRemoveLines);
    $tasks = $template->tasks;

    // We now have $templateTask that needs the 'nextstep' parameter cleared.
    $tasks[$taskToRemoveLines]['nextstep'] = '';
    $tasks[$taskToRemoveLines]['nextfalsestep'] = '';
    // Now to remove this task from the tasks that point to it.
    foreach ($pointers as $pointer) {
      $nextsteps = explode(',', $tasks[$pointer]['nextstep']);
      $key = array_search($taskToRemoveLines, $nextsteps);
      if ($key !== FALSE) {
        unset($nextsteps[$key]);
      }
      $tasks[$pointer]['nextstep'] = implode(',', $nextsteps);
      // How about false branches now.
      $nextfalsesteps = explode(',', $tasks[$pointer]['nextfalsestep']);
      $key = array_search($taskToRemoveLines, $nextfalsesteps);
      if ($key !== FALSE) {
        unset($nextfalsesteps[$key]);
      }
      $tasks[$pointer]['nextfalsestep'] = implode(',', $nextfalsesteps);
    }
    $template->tasks = $tasks;
    $template->save();

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroRemoveTaskLines', ['task' => $taskToRemoveLines]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    // If this template we're editing doesn't actually exist, bail.
    if ($template == NULL) {
      $form = [
        '#title' => t('Error!'),
        '#markup' => t("The template you are attempting to add a task to doesn't exist"),
      ];
      return $form;
    }

    $validated_css = 'maestro-template-validation-div-hide';
    if ((property_exists($template, 'validated') && !$template->validated) || !property_exists($template, 'validated')) {
      $validated_css = '';
    }

    $form = [
      '#markup' => '<div id="maestro-template-error" class="messages messages--error"></div>
                    <div id="maestro-template-validation" class="maestro-template-validation-div messages messages--error ' . $validated_css . '">'
      . $this->t('This template requires validation before it can be used.') . '</div>',
    ];

    $height = $template->canvas_height;
    $width = $template->canvas_width;
    // Allow the task to define its own colours
    // these are here for now.
    $taskColours = [
      'MaestroStart' => '#00ff00',
      'MaestroEnd'   => '#ff0000',
      'MaestroSetProcessVariable' => '#a0a0a0',
      'MaestroTaskTypeIf' => 'orange',
      'MaestroInteractive' => '#0000ff',
    ];

    /*
     * We build our task array here
     * This array is passed to DrupalSettings and used in the template UI
     */
    $tasks = [];
    foreach ($template->tasks as $taskID => $task) {
      // Fetch this task's template builder capabilities.
      $this_task = MaestroEngine::getPluginTask($task['tasktype']);
      $capabilities = $this_task->getTemplateBuilderCapabilities();
      // For our template builder, we'll prefix each capability with "maestro_template_".
      foreach ($capabilities as $key => $c) {
        $capabilities[$key] = 'maestro_template_' . $c;
      }

      $tasks[] = [
        'taskname' => $task['label'],
        'type' => $task['tasktype'],
        'uilabel' => $this->t(str_replace('Maestro', '', $task['tasktype'])),
        'id' => $task['id'],
        'left' => $task['left'],
        'top' => $task['top'],
        'raphael' => '',
        'to' => explode(',', $task['nextstep']),
        'pointedfrom' => '',
        'falsebranch' => explode(',', $task['nextfalsestep']),
        'lines' => [],
        'capabilities' => $capabilities,
        'participate_in_workflow_status_stage' => isset($task['participate_in_workflow_status_stage']) ? $task['participate_in_workflow_status_stage'] : '',
        'workflow_status_stage_number' => isset($task['workflow_status_stage_number']) ? $task['workflow_status_stage_number'] : '',
        'workflow_status_stage_message' => isset($task['workflow_status_stage_message']) ? $this->t('Status Message') . ': ' . $task['workflow_status_stage_message'] : '',
      ];
    }
    $taskColours = [];
    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();
    foreach ($plugins as $key => $taskPlugin) {
      $task = $manager->createInstance($taskPlugin['id'], [0, 0]);
      $taskColours[$key] = $task->getTaskColours();
    }

    /*
     * Add new task button on the menu above the UI editor
     */
    $form['add_new_task'] = [
      '#type' => 'link',
      '#title' => $this->t('add task'),
      '#url' => Url::fromRoute('dyniva_flowchart.add_new', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'title' => $this->t('Add Task to Template'),
        'class' => ['use-ajax',
          'maestro-add-new-button',
          'maestro-add-new-task-button',
        ],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
        ]),
      ],
    ];

    $form['change_canvas_size'] = [
      '#type' => 'link',
      '#title' => $this->t('canvas'),
      '#url' => Url::fromRoute('dyniva_flowchart.canvas', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'title' => $this->t('Change Canvas Size'),
        'class' => ['use-ajax', 'maestro-canvas-button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 400,
        ]),
      ],
    ];

    /*
     * Run the validity checker
     */
    $form['run_validity_check'] = [
      '#type' => 'link',
      '#title' => $this->t('validity check'),
      // '#suffix' =>'<div id="maestro_div_template" style="width:' . $width . 'px; height: ' . $height . 'px;"></div>',  //right after this button is where we attach our Raphael template builder
      '#url' => Url::fromRoute('dyniva_flowchart.maestro_run_validity_check', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'title' => $this->t('Perform Validity Check'),
        'class' => ['use-ajax', 'maestro-canvas-button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
        ]),
      ],
    ];

    /*
     * Modal to edit the template
     */
    $form['edit_template'] = [
      '#type' => 'link',
      '#title' => $this->t('edit template'),
    // Right after this button is where we attach our Raphael template builder.
      '#suffix' => '<div id="maestro_div_template" style="width:' . $width . 'px; height: ' . $height . 'px;"></div>',
      '#url' => Url::fromRoute('entity.maestro_template.edit_form', ['maestro_template' => $templateMachineName, 'is_modal' => 'modal']),
      '#attributes' => [
        'title' => $this->t('Edit Template'),
        'class' => ['use-ajax', 'maestro-canvas-button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
        ]),
      ],
    ];

    // We will now render the legend.
    $legend = '';
    $legend_render_array = [
      '#theme' => 'template_task_legend',
    ];
    $legend = \Drupal::service('renderer')->renderPlain($legend_render_array);

    $form['task_legend'] = [
      '#type' => 'details',
      '#title' => $this->t('Legend'),
      '#markup' => $legend,
      '#attributes' => [
        'class' => ['maestro-task-legend'],
      ],
    ];

    /*
     * Need to know which template we're editing.
     */
    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#default_value' => $templateMachineName,
    ];

    /*
     * This is our fieldset menu.  We make this pop up dynamically wherever we want based on css and some simple javascript.
     *
     */
    $form['menu'] = [
      '#type' => 'fieldset',
      '#title' => '',
      '#attributes' => [
        'class' => ['maestro-popup-menu'],
      ],
      '#prefix' => '
        <div id="maestro-task-menu" class="ui-dialog ui-widget ui-widget-content ui-corner-all ui-front">
        <div class="ui-dialog-titlebar ui-widget-header ui-corner-all ui-helper-clearfix">
        <span id="task-menu-title" class="ui-dialog-title">' . t('Task Menu') . '</span>
        <span id="close-task-menu" class="ui-button-icon-primary ui-icon ui-icon-closethick"></span></div>'
      ,
      '#suffix' => '</div>',
    ];

    // Our field to store which task the edit button was clicked on.
    $form['menu']['task_clicked'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_line_from'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_line_to'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_top'] = [
      '#type' => 'hidden',
    ];

    $form['menu']['task_left'] = [
      '#type' => 'hidden',
    ];

    // This is our built-in task remove button
    // this is hidden as we use the remove_task_link to fire the submit as we just want to make sure
    // that you really do want to delete this task.
    // Hidden submit ajax button that is called by the JS UI when we have acknowledged.
    $form['remove_task_complete'] = [
    // That we really do want to remove the task from the template.
      '#type' => 'submit',
      '#value' => 'Remove',
      '#ajax' => [
        'callback' => [$this, 'removeTaskComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden submit ajax button that is called by the JS UI when we are in line drawing mode.
    $form['draw_line_complete'] = [
    // And the JS UI has detected that we've clicked on the task to draw the line TO.
      '#type' => 'submit',
      '#value' => 'Submit Draw Line',
      '#ajax' => [
        'callback' => [$this, 'drawLineComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden submit ajax button that is called by the JS UI when we are in false line drawing mode.
    $form['draw_false_line_complete'] = [
    // And the JS UI has detected that we've clicked on the task to draw the false line TO.
      '#type' => 'submit',
      '#value' => 'Submit False Draw Line',
      '#ajax' => [
        'callback' => [$this, 'drawFalseLineComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden submit ajax button that is called by the JS UI when we have released a task.
    $form['move_task_complete'] = [
    // During the task's move operation.  This updates the template with task position info.
      '#type' => 'submit',
      '#value' => 'Submit Task Move Coordinates',
      '#ajax' => [
        'callback' => [$this, 'moveTaskComplete'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];

    // Hidden link to modal that is called in the JS UI when we have set the appropriate task.
    $form['edit_task_complete'] = [
    // In the UI to be editing.
      '#type' => 'link',
      '#title' => 'Edit Task',
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
      '#url' => Url::fromRoute('dyniva_flowchart.edit_task', ['templateMachineName' => $templateMachineName]),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
          'height' => 500,
        ]),
      ],
    ];

    // End of hidden elements
    // The following are the buttons/links that show up on the task menu.
    $form['menu']['edit_this_task'] = [
      '#type' => 'button',
      '#value' => t('Edit Task'),
      '#ajax' => [
        'callback' => [$this, 'editTask'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_edit',
      ],
    ];

    $form['menu']['draw_line_to'] = [
      '#type' => 'button',
      '#value' => t('Draw Line To'),
      '#ajax' => [
        'callback' => [$this, 'drawLineTo'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_drawlineto',
      ],
    ];

    $form['menu']['draw_false_line_to'] = [
      '#type' => 'button',
      '#value' => t('Draw False Line To'),
      '#ajax' => [
        'callback' => [$this, 'drawFalseLineTo'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_drawfalselineto',
      ],
    ];

    $form['menu']['remove_lines'] = [
      '#type' => 'button',
      '#value' => t('Remove Lines'),
      '#ajax' => [
        'callback' => [$this, 'removeLines'],
        'wrapper' => '',
      ],
      '#attributes' => [
        'maestro_capabilities_id' => 'maestro_template_removelines',
      ],
    ];

    $form['menu']['remove_task_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => t('Remove Task'),
      '#attributes' => [
    // Gives us some padding from the other task mechanisms.
        'style' => 'margin-top: 20px;',
        'onclick' => 'maestro_submit_form(event)',
        'class' => ['button'],
        'maestro_capabilities_id' => 'maestro_template_remove',
    // This form element type does not have an ID by default.
        'id' => 'maestro_template_remove',
      ],
    ];
    // End of visible task menu items.
    $form['#attached'] = [
      'library' => ['dyniva_flowchart/maestrojs',
        'dyniva_flowchart/maestro_raphael',
        'dyniva_flowchart/maestro_tasks_css',
      ],
      'drupalSettings' => [
      // These are the template's tasks generated at the beginning of this form.
        'maestro' => ($tasks),
        'maestroTaskColours' => ($taskColours),
        'baseURL' => base_path(),
        'canvasHeight' => $height,
        'canvasWidth' => $width,
      ],
    ];

    $form['#cache'] = [
      'max-age' => 0,
    ];

    // Notification areas at the top and bottom of the editor to ensure that messages appear in both places so people can see them.
    // we send notifications to the divs by class name in the jQuery UI portion of the editor.
    $form['#prefix'] = '<div id="template-message-area-one" class="maestro-template-message-area messages messages--status"></div>';
    $form['#suffix'] = '<div id="template-message-area-two" class="maestro-template-message-area messages messages--status"></div>';

    return $form;
  }

}
