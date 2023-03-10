<?php

namespace Drupal\openai_api;

use Drupal\openai_api\Controller\GenerationController;

/**
 * Defines functionality related to openai content generation.
 */
class GenerationService {

  /**
   * Generates a node.
   *
   * @param array $data
   *   An array of required data.
   * @param mixed $context
   *   The context at the instant.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function generate(array $data, $operation_details, &$context): void {
    $openAiController = new GenerationController(\Drupal::service('openai.client'), \Drupal::service('config.factory'));
    $context['results'][] = $data['nbr_article_generated'];
    $context['message'] = t('Generating @type node @subject #@iteration.',
      [
        '@type' => $data['content_type'],
        '@iteration' => $data['iteration'],
        '@subject' => $data['subject'],
      ]
    );

    $body = $openAiController->getTextCompletionResponseBodyData($data['model'], $data['subject'], $data['max_token'], $data['temperature'], $data['save_html']);

    // Get generated image image url.
    $img = (new GenerationService)->generate_image($data);

    $openAiController->generateContent($data, $body, $data['content_type'], $data['title'], $data['body'], $data['filter_format'], $data['save_html'], $img);
  }

  /**
   * @param $data
   *
   * @return string|null
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function generate_image($data): ?string {
    $img = NULL;
    if ($data['image_prompt'] !== "") {
      $openAiController = new GenerationController(\Drupal::service('openai.client'), \Drupal::service('config.factory'));
      $img = $openAiController->getImageUrlResponseBodyData($data['image_prompt'], $data['image_resolution']);
    }

    return $img;
  }

  /**
   * Gives a message after generating an article.
   *
   * @param mixed $success
   *   A flag indicating whether an error had occurred.
   */
  public static function finished($success, $results, $operations): void {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = (t('@count node(s) generated.', ['@count' => count($results)]));
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addStatus($message);
  }


  /**
   * Generates a media.
   *
   * @param array $data
   *   An array of required data.
   * @param mixed $context
   *   The context at the instant.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function generate_media(array $data, $operation_details, &$context): void {
    $openAiController = new GenerationController(\Drupal::service('openai.client'), \Drupal::service('config.factory'));
    $context['results'][] = $data['nbr_media_generated'];
    $context['message'] = t('Generating @image_prompt n°@iteration.',
      [
        '@iteration' => $data['iteration'],
        '@image_prompt' => $data['image_prompt'],
      ]
    );

    // Get generated image image url.
    $img = (new GenerationService)->generate_image($data);

    $openAiController->generate_media_image($data, $img);
  }

  /**
   * Gives a message after generating a media.
   *
   * @param mixed $success
   *   A flag indicating whether an error had occurred.
   */
  public static function generate_medias_finished($success, $results, $operations): void {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = (t('@count Media(s) generated.', ['@count' => count($results)]));
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addStatus($message);
  }

}
