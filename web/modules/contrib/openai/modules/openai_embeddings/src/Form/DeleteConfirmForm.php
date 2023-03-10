<?php

namespace Drupal\openai_embeddings\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\openai_embeddings\Http\PineconeClient;
use Drupal\Core\Database\Connection;

/**
 * Provides a confirm form to delete all Pinecone items.
 */
class DeleteConfirmForm extends ConfirmFormBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Pinecone client.
   *
   * @var \Drupal\openai_embeddings\Http\PineconeClient
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    $instance->client = $container->get('openai_embeddings.pinecone_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_embeddings_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all items in your Pinecone index?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will delete all items in your Pinecone instance.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('openai_embeddings.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $results = $this->database->query('SELECT entity_type, field_name FROM {openai_embeddings}');

    foreach ($results as $result) {
      $this->client->delete([], TRUE, $result->entity_type . ':' . $result->field_name);
    }

    $this->messenger()->addStatus($this->t('All items have been deleted in Pinecone.'));
    $form_state->setRedirect('openai_embeddings.stats');
  }

}
