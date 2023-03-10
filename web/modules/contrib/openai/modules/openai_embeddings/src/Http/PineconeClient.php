<?php

namespace Drupal\openai_embeddings\Http;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;

/**
 * A simple client interface for the Pinecone HTTP API.
 *
 * @see https://docs.pinecone.io/reference/query
 */
class PineconeClient {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Constructs a new Pinecone client.
   *
   * @param \Drupal\Core\Http\ClientFactory $factory
   *   The HTTP client factory service.
   */
  public function __construct(ClientFactory $factory, ConfigFactoryInterface $config_factory) {
    $config = $config_factory->get('openai_embeddings.pinecone_client');

    $this->client = $factory->fromOptions([
      'headers' => [
        'Content-Type' => 'application/json',
        'API-Key' => $config->get('api_key'),
      ],
      'base_uri' => $config->get('hostname')
    ]);
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
  public function upsert(array $vectors, string $namespace = '') {
    return $this->client->post(
      '/vectors/upsert',
      [
        'json' => [
          'vectors' => $vectors,
          'namespace' => $namespace,
        ]
      ]
    );
  }

  /**
   * Look up and returns vectors, by ID, from a single namespace.
   *
   * @param array $ids
   *   One or more IDs to fetch.
   * @param string $namespace
   *   The namespace to search in, if applicable.
   *
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function fetch(array $ids, string $namespace = '') {
    return $this->client->get(
      '/vectors/fetch',
      [
        'query' => [
          'ids' => $ids,
          'namespace' => $namespace
        ]
      ]
    );
  }

  /**
   * Delete records in Pinecone.
   *
   * @param array $ids
   *   One or more IDs to delete.
   * @param bool $deleteAll
   *   This indicates that all vectors in the index namespace
   *   should be deleted. Use with caution.
   * @param string $namespace
   *   The namespace to delete vectors from, if applicable.
   * @param array $filter
   *   If specified, the metadata filter here will be used to select
   *   the vectors to delete. This is mutually exclusive with
   *   specifying ids to delete in the ids param or using $deleteAll.
   *
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function delete(array $ids = [], bool $deleteAll = FALSE, string $namespace = '', array $filter = []) {
    $payload = [];

    // If filter is provided, deleteAll can not be true.
    // If there are no filters, pass what the developer passed.
    if (empty($filter)) {
      $payload['deleteAll'] = $deleteAll;
    }

    $payload['namespace'] = $namespace;

    if (!empty($ids)) {
      $payload['ids'] = $ids;
    }

    if (!empty($filter)) {
      $payload['filter'] = $filter;
    }

    return $this->client->post(
      '/vectors/delete',
      [
        'json' => $payload
      ]
    );
  }

  /**
   * Returns statistics about the index's contents.
   */
  public function stats() {
    return $this->client->post(
      '/describe_index_stats',
    );
  }

}
