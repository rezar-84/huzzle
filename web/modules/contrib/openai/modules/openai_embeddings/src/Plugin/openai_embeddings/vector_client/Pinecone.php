<?php

declare(strict_types=1);

namespace Drupal\openai_embeddings\Plugin\openai_embeddings\vector_client;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\openai_embeddings\Annotation\VectorClient;
use Drupal\openai_embeddings\VectorClientInterface;
use GuzzleHttp\Client;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\openai_embeddings\VectorClientPluginBase;

/**
 * @VectorClient(
 *   id = "pinecone",
 *   label = "Pinecone",
 *   description = "Client implementation to connect and use the Pinecone API.",
 * )
 */
class Pinecone extends VectorClientPluginBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The configuration factory object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new Pinecone client.
   *
   * @param \Drupal\Core\Http\ClientFactory $factory
   *   The HTTP client factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientFactory $factory, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->config = $config_factory->getEditable('plugin.plugin_configuration.vector_client.' . $plugin_id);

    $this->client = $factory->fromOptions([
      'headers' => [
        'Content-Type' => 'application/json',
        'API-Key' => $this->config->get('api_key'),
      ],
      'base_uri' => $this->config->get('hostname')
    ]);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client_factory'),
      $container->get('config.factory'),
    );
  }

  /**
   * Submits a query to the API service.
   *
   * @param array $vector
   *   An array of floats. The size must match the vector size in Pinecone.
   * @param int $top_k
   *   How many matches should be returned.
   * @param string $namespace
   *   The namespace to use, if any.
   * @param bool $include_metadata
   *   Includes metadata for the records returned.
   * @param bool $include_values
   *   Includes the values for the records returned. Not usually recommended.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The API response.
   */
  public function query(array $vector, int $top_k = 5, bool $include_metadata = FALSE, bool $include_values = FALSE, array $filters = [], string $namespace = '') {
    $payload = [
      'vector' => $vector,
      'topK' => $top_k,
      'includeMetadata' => $include_metadata,
      'includeValues' => $include_values,
      'namespace' => $namespace,
    ];

    if (!empty($filters)) {
      $payload['filter'] = $filters;
    }

    return $this->client->post(
      '/query',
      [
        'json' => $payload
      ]
    );
  }

  /**
   * Upserts an array of vectors to Pinecone.
   *
   * @param array $vectors
   *   An array of vector objects.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The API response.
   */
  public function upsert(array $vectors) {
    return $this->client->post(
      '/vectors/upsert',
      [
        'json' => [
          'vectors' => $vectors,
        ]
      ]
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
    $this->config
      ->set('hostname', $this->configuration['hostname'])
      ->set('api_key', $this->configuration['api_key'])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'hostname' => '',
      'api_key' => '',
    ];
  }

  public function insert(array $parameters) {
    // TODO: Implement insert() method.
  }

  public function update(array $parameters) {
    // TODO: Implement update() method.
  }

  public function delete(array $parameters) {
    // TODO: Implement delete() method.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config->get('api_key'),
      '#description' => $this->t('The API key is required to make calls to Pinecone for vector searching.'),
    ];

    $form['hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#default_value' => $this->config->get('hostname'),
      '#description' => $this->t('The hostname or base URI where your Pinecone instance is located.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement validateConfigurationForm() method.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }
}
