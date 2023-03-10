<?php

namespace Drupal\openai_api\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use OpenAI\Client;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for openai api routes.
 */
class GenerationController extends ControllerBase {

  const MODELS_OPTIONS = [
    'text-davinci-003',
    'text-curie-001',
    'text-babbage-001',
    'text-ada-001'
  ];

  /**
   * Drupal\profiler\Config\ConfigFactoryWrapper definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected Client $client;

  /**
   * Defining a constructor for dependencies.
   *
   * @param \OpenAI\Client
   *   The OpenAI client.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory The config
   *   factory.
   */
  public function __construct(Client $client, ConfigFactory $config_factory) {
    $this->client = $client;
    $this->configFactory = $config_factory;
  }

  /**
   * Creates a new service.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The ContainerInterface object.
   *
   * @return \Drupal\openai_api\Controller\GenerationController
   *   The GenerationController object.
   */
  public static function create(ContainerInterface $container): GenerationController {
    return new static(
      $container->get('openai.client'),
      $container->get('openai_api.settings')
    );
  }

  /**
   * Builds the response for text.
   *
   * @param string $model The api model.
   * @param string $text The text for generation.
   * @param int $max_token The maximum number of tokens.
   * @param float $temperature The temperature.
   * @param bool $save_html
   *   Whether the results should come back as HTML markup or not.
   *
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getTextCompletionResponseBodyData(
    $model,
    $text,
    $max_token,
    $temperature,
    bool $save_html = FALSE
  ): string {
    if ($save_html) {
      $text = $text . ' Return the response in HTML markup.';
    }

    $response = $this->client->completions()->create([
      'model' => $model,
      'prompt' => trim($text),
      'temperature' => (int) $temperature,
      'max_tokens' => (int) $max_token,
    ]);

    $result = $response->toArray();
    return trim($result["choices"][0]["text"]) ?? '';
  }

  /**
   * Builds the response for image.
   *
   * @param $prompt
   * @param $size
   *
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getImageUrlResponseBodyData(
    $prompt,
    $size,
  ): string {
    $imgCall = $this->client->getImageUrl(
      $prompt,
      $size,
    );

    if ($imgCall->getStatusCode() === 200) {
      $imgUrl = $this->client->getResponseBody($imgCall);
      $return = $imgUrl['data'][0]['url'];
    }
    else {
      $return = '';
    }

    return $return;
  }

  /**
   * Gets the Model Response Body Data.
   *
   * @return array
   *   An array containing the data.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getModelsResponseBodyData(): array {
    $modelsCall = $this->client->getModels();

    if ($modelsCall->getStatusCode() === 200) {
      $models = $this->client->getResponseBody($modelsCall);
      $return = $models['data'];
    }
    else {
      $return = [];
    }

    return $return;
  }

  /**
   * Gets the models.
   *
   * @return array
   *   Returns an array of models.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getModels(): array {
    $models_list = $this->getModelsResponseBodyData();
    $models = [];

    foreach ($models_list as $item) {
      if (in_array($item['id'], self::MODELS_OPTIONS)) {
        $models[$item['id']] = $item['id'];
      }
    }

    return $models;
  }

  /**
   * Generate a new node.
   *
   * @param array $data
   *   The array data of the node.
   * @param string $body
   *   The body of the node.
   * @param null|string $img
   *   The image url.
   *
   * @return int
   *   Returns an int indicating whether the node was saved or not.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateContent(array $data, string $body, string $content_type, string $title_field, string $body_field, string $filter_format, bool $save_html = FALSE, ?string $img = NULL): int {
    $config = $this->configFactory->get('openai_api.settings');

    // Give this a basic wrapper for CKEditor.
    if (!$save_html) {
      $body = '<p>' . $body . '</p>';
    }

    $body = [
      'value' => str_replace(array("\r\n","\r","\n","\\r","\\n","\\r\\n"),"",$body),
      'format' => $filter_format,
    ];

    $node = Node::create(['type' => $content_type]);
    $node->set($title_field, $data['subject']);
    $node->set($body_field, $body);

    // Set article img if prompt are provided in form.
    if ($img !== NULL) {
      $file = $this->generate_media_image($data, $img);
      if ($file->id()) {
        $node->set($config->get('field_image'), [
          'target_id' => $file->id(),
          'alt' => 'article-illustration',
          'title' => 'illustration',
        ]);
      }
    }

    $node->enforceIsNew();
    return $node->save();
  }

  /**
   * @param string|null $img
   * @param array $data
   *
   * @return \Drupal\file\Entity\File
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generate_media_image(array $data, ?string $img = NULL): File {
    /** @var \Drupal\file\Entity\File $file */
    $file = system_retrieve_file($img, 'public://', TRUE, FileSystemInterface::EXISTS_REPLACE);
    if ($file && $file->id()) {
      $this->createDerivativesImageStyle($file);
      $this->createMediaImage($file, $data);
    }

    return $file;
  }

  /**
   * @param $file
   *
   * @return void
   */
  public function createDerivativesImageStyle($file): void {
    $styles = ImageStyle::loadMultiple();

    if ($styles) {
      /** @var \Drupal\image\Entity\ImageStyle $style */
      foreach ($styles as $style) {
        $uri = $file->getFileUri();
        $destination = $style->buildUri($uri);
        if (!file_exists($destination)) {
          $style->createDerivative($uri, $destination);
        }
      }
    }
  }

  /**
   * @param \Drupal\file\Entity\File $file
   * @param array $data
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createMediaImage(File $file, array $data): void {
    $username = \Drupal::currentUser()->getAccountName();
    $timestamp = \Drupal::time()->getCurrentTime();
    $date = \Drupal::service('date.formatter')->format($timestamp, 'custom', 'Y-m-dTH:i:s');
    $mediaName = strtolower(str_replace(' ', '-', $data['image_prompt']));

    $image_media = Media::create([
      'name' => $mediaName.'.png',
      'bundle' => 'image',
      'uid' => 1,
      'langcode' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $data['image_prompt'],
        'title' => $data['image_prompt'],
      ],
      'field_author' => strtolower(str_replace(' ', '-', $username)),
      'field_date' => $date,
    ]);
    $image_media->save();
  }

  /**
   * Gets the OpenAI Subjects.
   *
   * @return array
   *   An array containing the options
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubjectsVocabularyTerms(): array {

    $config = $this->configFactory->get('openai_api.settings');
    $subjects = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadTree(
    // The taxonomy term vocabulary machine name.
      $config->get('vocabulary'),
      // The "tid" of parent using "0" to get all.
      0,
      // Get only 1st level.
      1,
      // Get full load of taxonomy term entity.
      TRUE
    );

    $options = [];
    /** @var \Drupal\taxonomy\Entity\Term $subject */
    foreach ($subjects as $subject) {
      $options[$subject->getName()] = $subject->getName();
    }

    return $options;
  }

}
