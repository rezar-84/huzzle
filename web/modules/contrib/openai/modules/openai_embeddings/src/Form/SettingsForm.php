<?php

declare(strict_types=1);

namespace Drupal\openai_embeddings\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenAI Embeddings settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_embeddings_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'openai_embeddings.settings',
      'openai_embeddings.pinecone_client'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_types = $this->getSupportedEntityTypes();
    $saved_types = $this->config('openai_embeddings.settings')->get('entity_types');
    $stopwords = $this->config('openai_embeddings.settings')->get('stopwords');

    if (!empty($stopwords)) {
      $stopwords = implode(', ', $stopwords);
    }

    $form['entities'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Enable analysis of these entities and their bundles'),
      '#description' => $this->t('Select which bundles of these entity types to generate embeddings from. Note that more content that you analyze will use more of your API usage. Check your <a href="@link">OpenAI account</a> for usage and billing details.', ['@link' => 'https://platform.openai.com/account/usage']),
    ];

    foreach ($entity_types as $entity_type => $entity_label) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);

      $options = [];

      foreach ($bundles as $bundle_id => $bundle_info) {
        $options[$bundle_id] = $bundle_info['label'];
      }

      $label = $entity_label;
      $label .= (!empty($saved_types) && !empty($saved_types[$entity_type])) ? ' (' . count($saved_types[$entity_type]) . ' ' . $this->t('selected') . ')' : '';

      $form['entities']['entity_types'][$entity_type] = [
        '#type' => 'details',
        '#title' => $label,
      ];

      $form['entities']['entity_types'][$entity_type][] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => (!empty($saved_types) && !empty($saved_types[$entity_type])) ? $saved_types[$entity_type] : [],
      ];
    }

    $form['stopwords'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Stopwords'),
      '#default_value' => $stopwords,
      '#description' => $this->t('Enter a comma delimited list of words to exclude from generating embedding values for.'),
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model to use'),
      '#options' => [
        'text-embedding-ada-002' => 'text-embedding-ada-002',
      ],
      '#default_value' => $this->config('openai_embeddings.settings')->get('model'),
      '#description' => $this->t('Select which model to use to analyze text. See the <a href="@link">model overview</a> for details about each model.', ['@link' => 'https://platform.openai.com/docs/guides/embeddings/embedding-models']),
    ];

    $form['connections'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Configure API clients for vector search database services'),
      '#description' => $this->t('Searching vector/embedding data is only available one of these services.... TBD'),
    ];

    $form['connections']['pinecone'] = [
      '#type' => 'details',
      '#title' => $this->t('Pinecone'),
      '#description' => $this->t('Configure Pinecone settings (need links + description)'),
    ];

    $form['connections']['pinecone']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('openai_embeddings.pinecone_client')->get('api_key'),
      '#description' => $this->t('The API key is required to make calls to Pinecone for vector searching.'),
    ];

    $form['connections']['pinecone']['hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#default_value' => $this->config('openai_embeddings.pinecone_client')->get('hostname'),
      '#description' => $this->t('The hostname or base URI where your Pinecone instance is located.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_values = $form_state->getValue('entities')['entity_types'];

    $entity_types = [];

    foreach ($entity_values as $entity_type => $values) {
      $selected = array_filter($values[0]);

      if (count($selected)) {
        $entity_types[$entity_type] = $selected;
      }
    }

    $stopwords = explode(', ', mb_strtolower($form_state->getValue('stopwords')));
    sort($stopwords);

    $this->config('openai_embeddings.settings')
      ->set('entity_types', $entity_types)
      ->set('stopwords', $stopwords)
      ->set('model', $form_state->getValue('model'))
      ->save();

    $pinecone = $form_state->getValue('connections')['pinecone'];

    $this->config('openai_embeddings.pinecone_client')
      ->set('api_key', $pinecone['api_key'])
      ->set('hostname', $pinecone['hostname'])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Return a list of supported entity types and their bundles.
   *
   * @return array
   *   A list of available entity types as $machine_name => $label.
   */
  protected function getSupportedEntityTypes(): array {
    $entity_types = [];

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');

    $supported_types = [
      'node',
      'media',
      'taxonomy_term',
      'paragraph',
      'block_content',
    ];

    // @todo Add an alter hook so custom entities can 'opt-in'

    foreach ($entity_type_manager->getDefinitions() as $entity_name => $definition) {
      if (!in_array($entity_name, $supported_types)) {
        continue;
      }

      if ($definition instanceof ContentEntityType) {
        $label = $definition->getLabel();

        if (is_a($label, 'Drupal\Core\StringTranslation\TranslatableMarkup')) {
          /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
          $label = $label->render();
        }

        $entity_types[$entity_name] = $label;
      }
    }

    return $entity_types;
  }

}
