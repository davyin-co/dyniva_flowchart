<?php

namespace Drupal\dyniva_flowchart\Form;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\dyniva_flowchart\Ajax\FireJavascriptCommand;

/**
 *
 */
class FlowChartBuilderForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flowchart_builder_form';
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
    $entity = $form_state->get('entity');
    $task_state = $form_state->get('task_state');
    $items = [];
    foreach($entity->paragraphs as $item) {
      $items[$item->entity->id()] = $item->entity;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    foreach($task_state  as $item) {
      switch ($item['action']) {
        case 'add':
          $items[$item['task_id']] = $storage->load($item['task_id']);
          break;
        case 'update':
          $new = $storage->load($item['task_id']);
          $task = $items[$item['task_id']];
          $task->task_name = $new->task_name;
          $task->task_description = $new->task_description;
          break;
        case 'move':
          $task = $items[$item['task_id']];
          $task->top->value = $item['top'];
          $task->left->value = $item['left'];
          break;
        case 'to':
          $task = $items[$item['task_id']];
          $task->to[] = $item['to'];
          break;
        case 'false_to':
          $task = $items[$item['task_id']];
          $task->false_to[] = $item['to'];
          break;
        case 'remove_task':
          unset($items[$item['task_id']]);
        case 'remove_lines':
          if(isset($items[$item['task_id']])) {
            $task = $items[$item['task_id']];
            $task->to = [];
            $task->false_to = [];
          }
          foreach($items as $task) {
            $to_value = array_column($task->to->getValue(), 'value');
            $index = array_search($item['task_id'], $to_value);
            if($index !== FALSE) {
              array_splice($to_value,$index,1);
              $task->to = $to_value;
            }
            $false_to_value = array_column($task->false_to->getValue(), 'value');
            $index = array_search($item['task_id'], $false_to_value);
            if($index !== FALSE) {
              array_splice($false_to_value,$index,1);
              $task->false_to = $false_to_value;
            }
          }
          break;

        default:
          # code...
          break;
      }
    }
    foreach($items as $item) {
      $item->save();
    }
    $entity->paragraphs = array_values($items);
    $entity->save();
  }

  /**
   * Ajax callback to set a session variable that we use to then signal the modal dialog for task editing to appear.
   */
  public function editTask(array &$form, FormStateInterface $form_state) {
    // Set a task editing session variable here
    // TODO: if the task menu is changed to a dynamic ajax-generated form based on
    // task capabilities, we won't need this session and the Ajax URL in the edit task
    // callback can pass in the task ID.
    $_SESSION['flowchart_builder_form']['maestro_editing_task'] = $form_state->getValue('task_clicked');
    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    $response->addCommand(new FireJavascriptCommand('maestroEditTask', []));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $managedEntity = NULL, EntityInterface $entity = NULL) {
    if ($entity == NULL) {
      $form = [
        '#title' => t('Error!'),
        '#markup' => t("The template you are attempting to add a section to doesn't exist"),
      ];
      return $form;
    }

    if(empty($form_state->get('task_state'))) {
      $form_state->set('entity', $entity);
      $form_state->set('task_state', []);
    }
    $validated_css = 'maestro-template-validation-div-hide';

    $form = [
      '#markup' => '<div id="maestro-template-error" class="messages messages--error"></div>
                    <div id="maestro-template-validation" class="maestro-template-validation-div messages messages--error ' . $validated_css . '">'
      . $this->t('This template requires validation before it can be used.') . '</div>',
    ];

    $height = 1200;
    $width = 1000;
    // Allow the task to define its own colours
    // these are here for now.
    $taskColours = [
      'start' => '#00ff00',
      'end'   => '#ff0000',
      'if' => 'orange',
      'interactive' => '#0000ff',
    ];

    /*
     * We build our task array here
     * This array is passed to DrupalSettings and used in the template UI
     */
    $tasks = [];
    /**
     * @var \Drupal\dyniva_flowchart\FlowChartTasksPluginManager $task_manager
     */
    $task_manager = \Drupal::service('plugin.manager.flowchart_tasks');
    foreach ($entity->paragraphs as $item) {
      $task = $item->entity;
      if($task && $task_manager->hasDefinition($task->task_type->value)) {
        // Fetch this task's template builder capabilities.
        $task_plugin= $task_manager->createInstance($task->task_type->value);
        $capabilities = $task_plugin->getTemplateBuilderCapabilities();
        // For our template builder, we'll prefix each capability with "maestro_template_".
        foreach ($capabilities as $key => $c) {
          $capabilities[$key] = 'maestro_template_' . $c;
        }

        $tasks[] = [
          'taskname' => $task_plugin->shortDescription(),
          'type' => $task->task_type->value,
          'uilabel' => $task->task_name->value,
          'id' => $task->id(),
          'left' => $task->left->value,
          'top' => $task->top->value,
          'raphael' => '',
          'to' => array_column($task->to->getValue(),'value'),
          'pointedfrom' => '',
          'falsebranch' => array_column($task->false_to->getValue(), 'value'),
          'lines' => [],
          'capabilities' => $capabilities,
          'participate_in_workflow_status_stage' => '',
          'workflow_status_stage_number' => '',
          'workflow_status_stage_message' => '',
        ];
      }
    }
    $taskColours = [];
    $plugins = $task_manager->getDefinitions();
    foreach ($plugins as $key => $taskPlugin) {
      $task = $task_manager->createInstance($taskPlugin['id']);
      $taskColours[$key] = $task->getTaskColours();
    }

    /*
     * Add new task button on the menu above the UI editor
     */
    $form['add_new_task'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Section'),
      '#url' => Url::fromRoute('dyniva_flowchart.add_new', ['node' => $entity->id()]),
      '#attributes' => [
        'title' => $this->t('Add Section to flowchart'),
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

    // $form['change_canvas_size'] = [
    //   '#type' => 'link',
    //   '#title' => $this->t('canvas'),
    //   '#url' => Url::fromRoute('dyniva_flowchart.canvas', ['node' => $entity->id()]),
    //   '#attributes' => [
    //     'title' => $this->t('Change Canvas Size'),
    //     'class' => ['use-ajax', 'maestro-canvas-button'],
    //     'data-dialog-type' => 'modal',
    //     'data-dialog-options' => Json::encode([
    //       'width' => 400,
    //     ]),
    //   ],
    // ];


    /*
     * Modal to edit the template
     */
    $form['edit_template'] = [
      '#markup' => '<div id="maestro_div_template" style="width:' . $width . 'px; height: ' . $height . 'px;"></div>',
    ];
    // We will now render the legend.
    // $legend = '';
    // $legend_render_array = [
    //   '#theme' => 'template_task_legend',
    // ];
    // $legend = \Drupal::service('renderer')->renderPlain($legend_render_array);

    // $form['task_legend'] = [
    //   '#type' => 'details',
    //   '#title' => $this->t('Legend'),
    //   '#markup' => $legend,
    //   '#attributes' => [
    //     'class' => ['maestro-task-legend'],
    //   ],
    // ];

    /*
     * Need to know which template we're editing.
     */
    $form['nid'] = [
      '#type' => 'hidden',
      '#default_value' => $entity->id(),
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
      '#submit' => [[$this, 'removeTaskSubmit']],
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
      '#submit' => [[$this, 'drawLineSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallbackNoOp'],
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
      '#submit' => [[$this, 'drawFalseLineSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallbackNoOp'],
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
      '#submit' => [[$this, 'moveTaskSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallbackNoOp'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];
    // Hidden submit ajax button that is called by the JS UI when we have released a task.
    $form['add_task_complete'] = [
    // During the task's move operation.  This updates the template with task position info.
      '#type' => 'submit',
      '#value' => 'Submit Task add element',
      '#submit' => [[$this, 'addTaskSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallbackNoOp'],
        'wrapper' => '',
      ],
      '#prefix' => '<div class="maestro_hidden_element">',
      '#suffix' => '</div>',
    ];
    // Hidden submit ajax button that is called by the JS UI when we have released a task.
    $form['update_task_complete'] = [
    // During the task's move operation.  This updates the template with task position info.
      '#type' => 'submit',
      '#value' => 'Submit Task update element',
      '#submit' => [[$this, 'updateTaskSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallbackNoOp'],
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
      '#url' => Url::fromRoute('dyniva_flowchart.edit_task', ['node' => $entity->id()]),
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
      '#type' => 'submit',
      '#value' => t('Remove Lines'),
      '#submit' => [[$this, 'removeLinesSubmit']],
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


    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function moveTaskSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskMoved = $form_state->getValue('task_clicked');
    $top = $form_state->getValue('task_top');
    $left = $form_state->getValue('task_left');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'move',
      'task_id' => $taskMoved,
      'top' => $top,
      'left' => $left,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function addTaskSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskMoved = $form_state->getValue('task_clicked');
    $top = $form_state->getValue('task_top');
    $left = $form_state->getValue('task_left');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'add',
      'task_id' => $taskMoved,
      'top' => $top,
      'left' => $left,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function updateTaskSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskMoved = $form_state->getValue('task_clicked');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'update',
      'task_id' => $taskMoved,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function drawFalseLineSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'false_to',
      'task_id' => $taskFrom,
      'to' => $taskTo,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function drawLineSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskFrom = $form_state->getValue('task_line_from');
    $taskTo = $form_state->getValue('task_line_to');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'to',
      'task_id' => $taskFrom,
      'to' => $taskTo,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to signal the UI to go into line drawing mode.
   */
  public function drawFalseLineTo(array &$form, FormStateInterface $form_state)
  {
    $taskFrom = $form_state->getValue('task_clicked');
    $task = \Drupal::entityTypeManager()->getStorage('paragraph')->load($taskFrom);
    if ($task->task_type->value == 'end') {
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
   * Ajax callback to signal the UI to go into line drawing mode.
   */
  public function drawLineTo(array &$form, FormStateInterface $form_state)
  {
    $taskFrom = $form_state->getValue('task_clicked');
    $task = \Drupal::entityTypeManager()->getStorage('paragraph')->load($taskFrom);
    if ($task->task_type->value == 'end') {
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
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function removeLinesSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskToRemoveLines = $form_state->getValue('task_clicked');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'remove_lines',
      'task_id' => $taskToRemoveLines,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function removeLines(array &$form, FormStateInterface $form_state)
  {
    $taskToRemoveLines = $form_state->getValue('task_clicked');

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroRemoveTaskLines', ['task' => $taskToRemoveLines]));
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }
  /**
   * Ajax callback to remove the lines pointing to and from a task.
   */
  public function removeTaskSubmit(array &$form, FormStateInterface $form_state)
  {
    $taskToRemove = $form_state->getValue('task_clicked');

    $task_state = $form_state->get('task_state');
    $task_state[] = [
      'action' => 'remove_task',
      'task_id' => $taskToRemove,
    ];
    $form_state->set('task_state', $task_state);
    $form_state->setRebuild();
  }
  /**
   * Ajax callback to remove a task from the template.
   */
  public function removeTaskComplete(array &$form, FormStateInterface $form_state)
  {
    $taskToRemove = $form_state->getValue('task_clicked');

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroRemoveTask', ['task' => $taskToRemove]));
    return $response;
  }
  /**
   * Ajax callback to complete the false line drawing routine when the second task has been selected.
   */
  public function ajaxCallbackCloseTaskMenu(array &$form, FormStateInterface $form_state)
  {
    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroCloseTaskMenu', []));
    return $response;
  }
  /**
   * Ajax callback to complete the move of a task when the mouse button is released.
   */
  public function ajaxCallbackNoOp(array &$form, FormStateInterface $form_state)
  {
    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroNoOp', []));
    return $response;
  }

}
