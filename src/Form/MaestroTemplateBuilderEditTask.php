<?php

namespace Drupal\dyniva_flowchart\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\dyniva_flowchart\Ajax\FireJavascriptCommand;

/**
 * Maestro Template Editor Edit a Task Form.
 */
class MaestroTemplateBuilderEditTask extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'template_edit_task';
  }

  /**
   * {@inheritdoc}
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    // We cancel the modal dialog by first sending down the form's error state as the cancel is a submit.
    // we then close the modal.
    $response = new AjaxResponse();
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    // Remove the session variable for the task being edited.
    $_SESSION['dyniva_flowchart']['maestro_editing_task'] = '';
    $response->addCommand(new HtmlCommand('#edit-task-form', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If we have errors in the form, show those.
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response = new AjaxResponse();
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#edit-task-form', $form));
      return $response;
    }
    // otherwise, we can get on to saving the task.
    else {
      $taskID = $form_state->getValue('task_id');
      $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($taskID);

      $task_name = $form_state->getValue('task_name');
      $task_description = $form_state->getValue('task_description');

      $paragraph->task_name->value = $task_name;
      $paragraph->task_description = $task_description;
      $paragraph->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveForm(array &$form, FormStateInterface $form_state) {
    // If we have errors in the form, show those.
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response = new AjaxResponse();
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#edit-task-form', $form));
      return $response;
    }
    // Save of the task has already been done in the submit.  We now are only responsible for updating the UI and updating the form.
    $taskID = $form_state->getValue('task_id');

    $task_name = $form_state->getValue('task_name');
    $task_description = $form_state->getValue('task_description');

    $update = [
      'label' => $task_name,
      'taskid' => $taskID,
    ];

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroUpdateMetaData', $update));
    $response->addCommand(new HtmlCommand('#edit-task-form', $form));
    $response->addCommand(new FireJavascriptCommand('maestroShowSavedMessage', []));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Ajax callback for add-new-form button click.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = null) {
    $taskID = Xss::filter($_SESSION['flowchart_builder_form']['maestro_editing_task']);
    $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($taskID);
    // Need to validate this taskID and template to ensure that they exist.
    if ($taskID == '' || $paragraph == NULL) {
      $form = [
        '#title' => t('Error!'),
        '#markup' => t('The section or template you are attempting to edit does not exist'),
      ];
      return $form;
    }

    $form = [
      '#title' => $this->t('Editing Section') . ': ' . '(' . $taskID . ')',
      '#prefix' => '<div id="edit-task-form">',
      '#suffix' => '</div>',
    ];

    $form['task_id'] = [
      '#type' => 'hidden',
      '#value' => $taskID,
    ];
    $form['task_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The name for the section'),
      '#required' => TRUE,
      '#default_value' => $paragraph->task_name->value,
    ];

    $form['task_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('The short description for the section'),
      '#default_value' => $paragraph->task_description->value,
      // '#format' => 'full_html'
      '#required' => TRUE,
    ];
    // Save button in an actions bar:
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Section'),
      '#required' => TRUE,
      '#ajax' => [
    // Use saveFrom rather than submitForm to alleviate the issue of calling a save handler twice.
        'callback' => [$this, 'saveForm'],
        'wrapper' => '',
      ],
    ];

    $form['actions']['close'] = [
      '#type' => 'button',
      '#value' => $this->t('Close'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'cancelForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

}
