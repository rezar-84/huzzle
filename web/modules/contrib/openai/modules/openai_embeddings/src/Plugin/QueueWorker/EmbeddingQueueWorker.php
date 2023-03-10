<?php

namespace Drupal\openai_embeddings\Plugin\QueueWorker;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\openai\Utility\StringHelper;
use Drupal\openai_embeddings\Http\PineconeClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use OpenAI\Client;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Queue worker for OpenAI Embeddings module.
 *
 * @QueueWorker(
 *   id = "embedding_queue",
 *   title = @Translation("Embedding Queue"),
 *   cron = {"time" = 30}
 * )
 */
final class EmbeddingQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected $client;

  /**
   * The Pinecone client.
   *
   * @var \Drupal\openai_embeddings\Http\PineconeClient
   */
  protected $pinecone;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, Connection $connection, ConfigFactoryInterface $config_factory, Client $client, PineconeClient $pinecone_client, LoggerChannelFactoryInterface $logger_channel_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->database = $connection;
    $this->config = $config_factory->get('openai_embeddings.settings');
    $this->client = $client;
    $this->pinecone = $pinecone_client;
    $this->logger = $logger_channel_factory->get('openai_embeddings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('openai.client'),
      $container->get('openai_embeddings.pinecone_client'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      $entity = $this->entityTypeManager->getStorage($data['entity_type'])
        ->load($data['entity_id']);

      if (!$entity instanceof EntityInterface) {
        throw new EntityStorageException("Could not load {$data['entity_type']} entity with an ID of {$data['entity_id']}.");
      }

      $fields = $this->entityFieldManager->getFieldDefinitions($data['entity_type'], $data['bundle']);
      $field_types = $this->getFieldTypes();
      $stopwords = $this->config->get('stopwords');
      $model = $this->config->get('model');

      foreach ($fields as $field) {
        if (in_array($field->getType(), $field_types)) {
          $field_values = $entity->get($field->getName())->getValue();

          foreach ($field_values as $delta => $data) {
            if (!mb_strlen($data['value'])) {
              continue;
            }

            $text = StringHelper::prepareText($data['value'], [], 8000);

            foreach ($stopwords as $word) {
              $text = $this->removeStopWord($word, $text);
            }

            // @todo The entity should be inserted as one string and not several entries
            try {
              $response = $this->client->embeddings()->create([
                'model' => $model,
                'input' => $text,
              ]);

              $embeddings = $response->toArray();

              $namespace = $entity->getEntityTypeId() . ':' . $field->getName();

              $vectors = [
                'id' => $this->generateUniqueId($entity, $field->getName(), $delta),
                'values' => $embeddings["data"][0]["embedding"],
                'metadata' => [
                  'entity_id' => $entity->id(),
                  'entity_type' => $entity->getEntityTypeId(),
                  'bundle' => $entity->bundle(),
                  'field_name' => $field->getName(),
                  'field_delta' => $delta,
                ]
              ];

              $this->pinecone->upsert($vectors, $namespace);

              $this->database->merge('openai_embeddings')
                ->keys(
                  [
                    'entity_id' => $entity->id(),
                    'entity_type' => $entity->getEntityTypeId(),
                    'bundle' => $entity->bundle(),
                    'field_name' => $field->getName(),
                    'field_delta' => $delta,
                  ]
                )
                ->fields(
                  [
                    'embedding' => json_encode(['data' => $embeddings["data"][0]["embedding"]]) ?? [],
                    'data' => json_encode(['usage' => $embeddings["usage"]]),
                  ]
                )
                ->execute();

              // sleep for 1.2 second(s)
              sleep(1);
              usleep(200000);
            } catch (\Exception $e) {
              $this->logger->error(
                'An exception occurred while trying to generate embeddings for a :entity_type with the ID of :entity_id on the :field_name field, with a delta of :field_delta. The bundle of this entity is :bundle. The error was :error',
                [
                  ':entity_type' => $entity->getEntityTypeId(),
                  ':entity_id' => $entity->id(),
                  ':field_name' => $field->getName(),
                  ':field_delta' => $delta,
                  ':bundle' => $entity->bundle(),
                  ':error' => $e->getMessage(),
                ]
              );
            }
          }
        }
      }
    } catch (EntityStorageException|\Exception $e) {
      $this->logger->error('Error processing queue item. Queued entity type was :type and has an ID of :id.',
        [
          ':type' => $data['entity_type'],
          ':id' => $data['entity_id']
        ]
      );
    }
  }

  /**
   * Generates a unique id for the record in Pinecone.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being upserted.
   * @param string $field_name
   *   The field name of the vector value we are storing.
   * @param int $delta
   *   The delta on the field where this value appeared.
   *
   * @return string
   *   The identifier of this record.
   */
  protected function generateUniqueId(EntityInterface $entity, string $field_name, int $delta): string {
    return 'entity:' . $entity->id() . ':' . $entity->getEntityTypeId() . ':' . $entity->bundle() . ':' . $field_name . ':' . $delta;
  }

  /**
   * A list of string/text field types.
   *
   * @return string[]
   */
  protected function getFieldTypes(): array {
    return [
      'string',
      'string_long',
      'text',
      'text_long',
      'text_with_summary'
    ];
  }

  /**
   * Remove a list of words from a string.
   *
   * @param string $word
   *   The word to remove.
   * @param string $text
   *   The input text.
   *
   * @return string
   *   The input text with stop words removed.
   */
  protected function removeStopWord(string $word, string $text): string {
    return preg_replace("/\b$word\b/i", '', trim($text));
  }
}
