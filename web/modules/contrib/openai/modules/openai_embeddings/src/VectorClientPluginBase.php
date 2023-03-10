<?php

namespace Drupal\openai_embeddings;

use Drupal\Core\Plugin\PluginBase;
use Drupal\openai_embeddings\VectorClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class VectorClientPluginBase extends PluginBase implements VectorClientInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

}
