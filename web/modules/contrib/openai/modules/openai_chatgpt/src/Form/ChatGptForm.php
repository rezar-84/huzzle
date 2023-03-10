<?php

declare(strict_types=1);

namespace Drupal\openai_chatgpt\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form interact with the ChatGPT endpoint.
 */
class ChatGptForm extends FormBase {

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
    return 'opeani_chatgpt_form';
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

    $form['system'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Profile'),
      '#default_value' => $this->t('You are a friendly helpful assistant inside of a Drupal website. Be encouraging and polite and ask follow up questions of the user after giving the answer.'),
      '#description' => $this->t('The "profile" helps set the behavior of the ChatGPT response. You can change/influence how it response by adjusting the above instruction. If you want to change this value after starting a conversation, you will need to reload the form first.'),
      '#required' => TRUE,
    ];

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message for ChatGPT'),
      '#description' => $this->t('Enter your text here. When submitted, OpenAI will generate a response from its Chats endpoint. Please note that each query counts against your API usage for OpenAI. Based on the complexity of your text, OpenAI traffic, and other factors, a response can sometimes take up to 10-15 seconds to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model to use'),
      '#options' => [
        'gpt-3.5-turbo' => 'gpt-3.5-turbo',
        'gpt-3.5-turbo-0301' => 'gpt-3.5-turbo-0301',
      ],
      '#default_value' => 'gpt-3.5-turbo',
      '#description' => $this->t('Select which model to use to analyze text. See the <a href="@link">model overview</a> for details about each model.', ['@link' => 'https://platform.openai.com/docs/models/gpt-3.5']),
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
      '#title' => $this->t('Response from ChatGPT'),
      '#attributes' =>
        [
          'readonly' => 'readonly',
        ],
      '#prefix' => '<div id="openai-chatgpt-response">',
      '#suffix' => '</div>',
      '#description' => $this->t('The response from OpenAI will appear in the textbox above.')
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Chat with OpenAI'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'openai-chatgpt-response',
        'progress' => [
          'type' => 'fade',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Print the last response out on the screen.
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $last_response = end($storage['messages']);
    $form['response']['#value'] = trim($last_response['content']) ?? $this->t('No answer was provided.');
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $text = $form_state->getValue('text');
    $system = $form_state->getValue('system');
    $model = $form_state->getValue('model');
    $temperature = $form_state->getValue('temperature');
    $max_tokens = $form_state->getValue('max_tokens');

    $storage = $form_state->getStorage();

    if (!empty($storage['messages'])) {
      $messages = $storage['messages'];
      $messages[] = ['role' => 'user', 'content' => trim($text)];
    } else {
      $messages = [
        ['role' => 'system', 'content' => trim($system)],
        ['role' => 'user', 'content' => trim($text)]
      ];
    }

    $response = $this->client->chat()->create(
      [
        'model' => $model,
        'messages' => $messages,
        'temperature' => (int) $temperature,
        'max_tokens' => (int) $max_tokens,
      ],
    );

    $result = $response->toArray();

    $messages[] = ['role' => 'assistant', 'content' => trim($result["choices"][0]["message"]["content"])];
    $form_state->setStorage(['messages' => $messages]);
    $form_state->setRebuild(TRUE);
  }

}
