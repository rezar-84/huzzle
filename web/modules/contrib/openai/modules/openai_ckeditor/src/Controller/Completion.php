<?php

namespace Drupal\openai_ckeditor\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for CKEditor integration routes.
 */
class Completion implements ContainerInjectionInterface {

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected $client;

  /**
   * The Completion controller constructor.
   *
   * @param \OpenAI\Client $client
   *   The openai.client service.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('openai.client')
    );
  }

  /**
   * Builds the response.
   */
  public function generate(Request $request): JsonResponse {
    $data = json_decode($request->getContent());

    $response = $this->client->completions()->create(
      [
        'model' => 'text-davinci-003',
        'prompt' => trim($data->prompt),
        'temperature' => 0.4,
        'max_tokens' => 256,
      ]
    );

    $response = $response->toArray();

    // @todo: could we have a setting to 'save' prompt responses like the log analyzer does?

    return new JsonResponse(
      [
        'text' => trim($response["choices"][0]["text"]),
      ],
    );
  }

}
