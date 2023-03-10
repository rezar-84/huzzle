<?php

namespace Drupal\openai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure OpenAI client settings for this site.
 */
class ApiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['openai.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('openai.settings')->get('api_key'),
      '#description' => $this->t('The API key is required to interface with OpenAI services. Get your API key by signing up on the <a href="@link" target="_blank">OpenAI website</a>.', ['@link' => 'https://openai.com/api']),
    ];

    $form['api_org'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization name/ID'),
      '#default_value' => $this->config('openai.settings')->get('api_org'),
      '#description' => $this->t('The organization name or ID on your OpenAI account. This is required for some OpenAI services to work correctly.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('openai.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_org', $form_state->getValue('api_org'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
