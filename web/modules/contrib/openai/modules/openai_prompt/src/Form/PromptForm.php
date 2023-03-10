<?php

declare(strict_types=1);

namespace Drupal\openai_prompt\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt OpenAI for answers.
 */
class PromptForm extends FormBase {

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opeani_prompt_prompt';
  }

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->client = $container->get('openai.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, OpenAI will generate a response. Please note that each query counts against your API usage for OpenAI.'),
      '#description' => $this->t('Based on the complexity of your prompt, OpenAI traffic, and other factors, a response can sometimes take up to 10-15 seconds to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
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
      '#default_value' => 'text-davinci-003',
      '#description' => $this->t('Select which model to use to analyze text. See the <a href="@link">model overview</a> for details about each model.', ['@link' => 'https://platform.openai.com/docs/models/gpt-3']),
    ];

    $form['temperature'] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#min' => 0,
      '#max' => 2,
      '#step' => .1,
      '#default_value' => '0.4',
      '#description' => $this->t('What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic.'),
    ];

    $form['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max tokens'),
      '#min' => 0,
      '#max' => 4096,
      '#step' => 1,
      '#default_value' => '128',
      '#description' => $this->t('The maximum number of tokens to generate in the completion. The token count of your prompt plus max_tokens cannot exceed the model\'s context length. Most models have a context length of 2048 tokens (except for the newest models, which support 4096).'),
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Response from OpenAI'),
      '#attributes' =>
        [
          'readonly' => 'readonly',
        ],
      '#prefix' => '<div id="openai-prompt-response">',
      '#suffix' => '</div>',
      '#description' => $this->t('The response from OpenAI will appear in the textbox above.')
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask OpenAI'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'openai-prompt-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $prompt = $form_state->getValue('prompt');
    $model = $form_state->getValue('model');
    $temperature = $form_state->getValue('temperature');
    $max_tokens = $form_state->getValue('max_tokens');

    $response = $this->client->completions()->create(
      [
        'model' => $model,
        'prompt' => trim($prompt),
        'temperature' => (int) $temperature,
        'max_tokens' => (int) $max_tokens,
      ],
    );

    $result = $response->toArray();

    $form['response']['#value'] = trim($result["choices"][0]["text"]) ?? $this->t('No answer was provided.');
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
