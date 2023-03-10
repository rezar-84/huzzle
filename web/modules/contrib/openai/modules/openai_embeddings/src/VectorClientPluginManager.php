<?php

namespace Drupal\openai_embeddings;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a VectorClient plugin manager.
 *
 * @see \Drupal\openai_embeddings\Annotation\VectorClient
 * @see plugin_api
 */
class VectorClientPluginManager extends DefaultPluginManager {

  /**
   * Constructs a VectorClientPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/openai_embeddings/vector_client', $namespaces, $module_handler, 'Drupal\openai_embeddings\VectorClientInterface', 'Drupal\openai_embeddings\Annotation\VectorClient');
    $this->alterInfo('vector_client_plugin_info');
    $this->setCacheBackend($cache_backend, 'vector_clients');
  }

}
