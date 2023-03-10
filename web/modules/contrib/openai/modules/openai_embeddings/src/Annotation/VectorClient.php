<?php

namespace Drupal\openai_embeddings\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a VectorClient annotation object.
 *
 * Plugin Namespace: Plugin\VectorClient
 *
 * @see plugin_api
 *
 * @Annotation
 */
class VectorClient extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public Translation $label;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public Translation $description;

}
