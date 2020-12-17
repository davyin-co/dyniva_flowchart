<?php

namespace Drupal\dyniva_flowchart\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\dyniva_flowchart\Ajax\FireJavascriptCommand;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Maestro Template Builder Add New form.
 */
class MaestroTemplateBuilderAddNew extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'template_add_new';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Everything in the base form is mandatory.  nothing really to check here.
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

    $response->addCommand(new HtmlCommand('#template-add-new-form', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do we have any errors?  if so, handle them by returning the form's HTML and replacing the form.
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response = new AjaxResponse();
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#template-add-new-form', $form));
      return $response;
    }
    else {
      $task_name = $form_state->getValue('task_name');
      $task_type = $form_state->getValue('task_type');
      $task_description = $form_state->getValue('task_description');

      // Create the new task entry in the template.
      $task_manager = \Drupal::service('plugin.manager.flowchart_tasks');
      $task_plugin = $task_manager->createInstance($task_type);
      $capabilities = $task_plugin->getTemplateBuilderCapabilities();

      foreach ($capabilities as $key => $c) {
        $capabilities[$key] = 'maestro_template_' . $c;
      }

      $values = [
        'task_name' => $task_name,
        'type' => 'flowchart_task',
        'task_type' => $task_type,
        'task_description' => $task_description,
        'top' => 15,
        'left' => 15,
      ];

      $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->create($values);
      $paragraph->save();

      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('addNewTask', [
        'id' => $paragraph->id(),
        'label' => $task_plugin->shortDescription(),
        'type' => $task_type,
        'capabilities' => $capabilities,
        'uilabel' => $task_name,
      ]));
      $response->addCommand(new CloseModalDialogCommand());
      return $response;
    }
  }

  /**
   * Ajax callback for add-new-form button click.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node);
    if ($node == NULL) {
      $form = [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t("The template you are attempting to add a section to doesn't exist"),
      ];
      return $form;
    }

    $form = [
      '#title' => $this->t('Add a new section'),
      '#markup' => '<div id="maestro-template-error" class="messages messages--error">dddd</div>',
    ];
    $form['#prefix'] = '<div id="template-add-new-form">';
    $form['#suffix'] = '</div>';

    // // Add all the task types here.
    $manager = \Drupal::service('plugin.manager.flowchart_tasks');
    $plugins = $manager->getDefinitions();

    $options = [];
    foreach ($plugins as $plugin) {
      // if ($plugin['id'] != 'start') {
        $task = $manager->createInstance($plugin['id']);
        $options[$task->getPluginId()] = $task->shortDescription();
      // }
    }

    $form['nid'] = [
      '#type' => 'hidden',
      '#title' => $this->t('machine name of the template'),
      '#default_value' => $node->id(),
      '#required' => TRUE,
    ];

    $form['task_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The name for the new section'),
      '#required' => TRUE,
    ];

    $form['task_description'] = [
      '#type' => 'text_format',
      '#required' => TRUE,
      '#title' => $this->t('The short description for the new section'),
      // '#format' => 'full_html'
    ];

    $form['task_type'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#title' => $this->t('Which section would you like to create?'),
      '#required' => TRUE,
    ];



    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Section'),
      '#required' => TRUE,
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'cancelForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

}
