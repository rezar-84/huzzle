<?php

namespace Drupal\openai_api\Commands;

use Drupal\openai_api\Controller\GenerationController;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ContentGenerationCommand extends DrushCommands {

  const IMAGE_RESOLUTION = [
    '256x256' => '256x256',
    '512x512' => '512x512',
    '1024x1024' => '1024x1024',
  ];

  const MODELS_OPTIONS = [
    'text-davinci-003' => 'text-davinci-003',
    'text-curie-001' => 'text-curie-001',
    'text-babbage-001' => 'text-babbage-001',
    'text-ada-001' => 'text-ada-001',
  ];

  const INPUT_SUBJECT_TYPE = [
    'free' => 'free',
    'vocabulary' => 'vocabulary',
  ];

  /**
   * Constructs a new object.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Generate content.
   *
   * @command openai:generate-content
   * @aliases oga
   *
   * @usage generate:content
   *
   */
  public function generateContentDrushCommand() {

    $datas = $this->getDrushArguments();

    if ($datas['confirm']) {
      $this->initOperations($datas['articles_prompts']);
      drush_backend_batch_process();
    }
    else {
      $output = new ConsoleOutput();
      $output->writeln('<comment>Command aborted</comment>');
    }
  }

  /**
   * Generate nodes with batch operations.
   *
   * @param array $datas
   *
   * @return void
   */
  public function generateContent(array $datas): void {
    $this->initOperations($datas);
  }

  /**
   * Initialize operations.
   *
   * @param array $datas
   *
   * @return void
   */
  protected function initOperations(array $datas): void {
    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    $nbrArticle = count($datas);

    for ($i = 0; $i < $nbrArticle; $i++) {
      for ($i_nbr = 0; $i_nbr < $datas[$i]['number_for_prompt']; $i_nbr++) {
        $data = [
          'iteration' => $i_nbr + 1,
          'nbr_article_generated' => $batchId,
          'subject' => $datas[$i]['subject'],
          'model' => $datas[$i]['model'],
          'content_type' => $datas[$i]['content_type'],
          'title' => $datas[$i]['title'],
          'body' => $datas[$i]['body'],
          'filter_format' => $datas[$i]['filter_format'],
          'save_html' => $datas[$i]['save_html'],
          'max_token' => $datas[$i]['max_token'],
          'temperature' => $datas[$i]['temperature'],
          'image_prompt' => $datas[$i]['image_prompt'],
          'image_resolution' => $datas[$i]['image_resolution'],
        ];
        $operations[] = [
          '\Drupal\openai_api\GenerationService::generate',
          [
            $data,
            t('Generating @type with @subject', ['@type' => $datas[$i]['content_type'], '@subject' => $datas[$i]['subject']]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }

    $batch = [
      'title' => t('Generating @num node(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\openai_api\GenerationService::finished',
      'progress_message' => t('Generating @current node out of @total.'),
    ];
    batch_set($batch);
  }

  /**
   * Get Drush arguments with interactive command.
   *
   * @return array
   */
  protected function getDrushArguments(): array {
    $articles = [];

    // Interactive drush command.
    $nbrArticles = $this->io()
      ->ask(('How many nodes?'), 1, function ($value) {
        if (!is_numeric($value)) {
          throw new \InvalidArgumentException('The value is not a number');
        }
        return $value;
      });
    for ($i = 0; $i < $nbrArticles; $i++) {

      $inputSubjectType = $this->io()
        ->choice('Do you want to choose a subject in you vocabulary or a free input ?', self::INPUT_SUBJECT_TYPE);

      if ($inputSubjectType === 'free') {
        $articles[$i]['subject'] = $this->io()
          ->ask('Your subject for the article type ' . ($i + 1) . ' ?', 'The story of Henry');
      }
      else {
        $openAiController = new GenerationController(\Drupal::service('openai.client'), \Drupal::service('config.factory'));
        $subjects = $openAiController->getSubjectsVocabularyTerms();
        if ($subjects) {
          $articles[$i]['subject'] = $this->io()
            ->choice('Your subject for the article type ' . ($i + 1) . ' ?', $subjects);
        } else {
          $this->io()->write('There is no subjects in your vocabulary', TRUE);
          $articles[$i]['subject'] = $this->io()
            ->ask('Your subject for the article type ' . ($i + 1) . ' ?', 'The story of Henry');
        }
      }

      $articles[$i]['model'] = $this->io()
        ->choice('What model do you want to use ?', self::MODELS_OPTIONS, 3);
      $articles[$i]['max_token'] = $this->io()
        ->ask('How many token (50 <> 2000) ?', 200, function ($value) {
          if (!is_numeric($value) || $value > 2000 || $value < 1) {
            throw new \InvalidArgumentException('The value is not a number');
          }
          return $value;
        });
      $articles[$i]['temperature'] = $this->io()
        ->ask('What temperature (0 <> 0.9) ?', 0.5, function ($value) {
          if (!is_float($value) || ($value > 0.9)) {
            throw new \InvalidArgumentException('The value is not a float between 0 and 0.9');
          }
          return $value;
        });

      // Article image prompts.
      $isWantImage = $this->io()
        ->confirm('Do you want to generate a media for this node?', FALSE);
      if ($isWantImage) {
        $articles[$i]['image_prompt'] = $this->io()
          ->ask('Image description ?', "A cat with glasses");
        $articles[$i]['image_resolution'] = $this->io()
          ->choice('Image resolution ?', self::IMAGE_RESOLUTION, 0);
      }
      else {
        $articles[$i]['image_prompt'] = "";
        $articles[$i]['image_resolution'] = "";
      }

      $articles[$i]['number_for_prompt'] = $this->io()
        ->ask('How many node(s) for this subject ?', 1, function ($value) {
          if (!is_numeric($value)) {
            throw new \InvalidArgumentException('The value is not a number');
          }
          return $value;
        });
    }
    $confirm = $this->io()->confirm('Proceed with operations ?');

    return [
      'articles_prompts' => $articles,
      'confirm' => $confirm,
    ];
  }

}
