<?php

namespace Drupal\dyniva_flowchart\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this site.
 */
class MaestroTemplateBuilderSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dyniva_flowchart_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dyniva_flowchart.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dyniva_flowchart.settings');

    $form['dyniva_flowchart_admin_settings']['#prefix'] = $this->t('Changes to these settings require a Drupal cache clear to take effect.');

    $form['dyniva_flowchart_local_library'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Use Raphael as a local library?"),
      '#default_value' => $config->get('dyniva_flowchart_local_library'),
      '#description' => $this->t('When checked, the template builder will look locally in /libraries/raphael for raphael.js.  Unchecked will use the remote library location.'),
    ];

    $default = $config->get('dyniva_flowchart_remote_library_location');
    $form['dyniva_flowchart_remote_library_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI used to pull the Raphael JS library.'),
      '#default_value' => isset($default) ? $default : '//cdnjs.cloudflare.com/ajax/libs/raphael/2.2.7/raphael.js',
      '#description' => $this->t('Defaults to //cdnjs.cloudflare.com/ajax/libs/raphael/2.2.7/raphael.js'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dyniva_flowchart.settings')
      ->set('dyniva_flowchart_local_library', $form_state->getValue('dyniva_flowchart_local_library'))
      ->save();

    $this->config('dyniva_flowchart.settings')
      ->set('dyniva_flowchart_remote_library_location', $form_state->getValue('dyniva_flowchart_remote_library_location'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
