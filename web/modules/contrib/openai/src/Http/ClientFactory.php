<?php

namespace Drupal\openai\Http;

use Drupal\Core\Config\ConfigFactoryInterface;
use OpenAI\Client;

/**
 * Service for generating OpenAI clients.
 */
class ClientFactory {

  /**
   * The config settings object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new ClientFactory instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('openai.settings');
  }

  /**
   * Creates a new OpenAI client instance.
   *
   * @return \OpenAI\Client
   *   The client instance.
   */
  public function create(): Client {
    return \OpenAI::client($this->config->get('api_key'), $this->config->get('api_org'));
  }

}
