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
 *
 */
class MaestroTemplateBuilderCanvas extends FormBase {

  /**
   *
   */
  public function getFormId() {
    return 'template_canvas';
  }

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Everything in the base form is mandatory.  nothing really to check here.
  }

  /**
   *
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    // We cancel the modal dialog by first sending down the form's error state as the cancel is a submit.
    // we then close the modal.
    $response = new AjaxResponse();
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $response->addCommand(new HtmlCommand('#template-canvas', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   *
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
      $response->addCommand(new HtmlCommand('#template-canvas', $form));
      return $response;
    }
    else {
      $templateMachineName = $form_state->getValue('template_machine_name');
      $height = $form_state->getValue('canvas_height');
      $width = $form_state->getValue('canvas_width');

      // Create the new task entry in the template.
      $template = MaestroEngine::getTemplate($templateMachineName);
      $template->canvas_height = $height;
      $template->canvas_width = $width;

      $template->save();
      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('alterCanvas', ['height' => $height, 'width' => $width]));
      $response->addCommand(new CloseModalDialogCommand());
      return $response;
    }
  }

  /**
   * Ajax callback for add-new-form button click.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    // Need to validate this template to ensure that it exists.
    if ($template == NULL) {
      $form = [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t("The template you are attempting to add a task to doesn't exist"),
      ];
      return $form;
    }

    $form = [
      '#title' => $this->t('Change canvas size'),
      '#markup' => '<div id="maestro-template-error" class="messages messages--error"></div>',
    ];
    $form['#prefix'] = '<div id="template-canvas">';
    $form['#suffix'] = '</div>';

    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#title' => $this->t('machine name of the template'),
      '#default_value' => $templateMachineName,
      '#required' => TRUE,
    ];

    $form['canvas_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height of the canvas in pixels'),
      '#default_value' => $template->canvas_height,
      '#size' => 10,
      '#required' => TRUE,
    ];

    $form['canvas_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width of the canvas in pixels'),
      '#default_value' => $template->canvas_width,
      '#size' => 10,
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Canvas'),
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
