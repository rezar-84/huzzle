<?php

namespace Drupal\openai_dblog\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenAI log analyzer settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->client = $container->get('openai.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_dblog_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['openai_dblog.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $levels = RfcLogLevel::getLevels();
    $options = [];

    foreach ($levels as $level) {
      $options[$level->render()] = $level->render();
    }

    $form['levels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log level(s) to analyze'),
      '#options' => $options,
      '#default_value' => $this->config('openai_dblog.settings')->get('levels'),
      '#description' => $this->t('Select which log levels should be analyzed when viewed. Note that non error levels like notice and debug are noisy and may cause wasted API usage. Check your <a href="@link">OpenAI account</a> for usage details.', ['@link' => 'https://platform.openai.com/account/usage']),
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model to use'),
      '#options' => [
        'text-davinci-003' => 'text-davinci-003',
        'text-curie-001' => 'text-curie-001',
        'text-babbage-001' => 'text-babbage-001',
        'text-ada-001' => 'text-ada-001',
      ],
      '#default_value' => $this->config('openai_dblog.settings')->get('model'),
      '#description' => $this->t('Select which model to use to analyze text. See the <a href="@link">model overview</a> for details about each model.', ['@link' => 'https://platform.openai.com/docs/models/gpt-3']),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('openai_dblog.settings')
      ->set('levels', array_filter($form_state->getValue('levels')))
      ->set('model', $form_state->getValue('model'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
