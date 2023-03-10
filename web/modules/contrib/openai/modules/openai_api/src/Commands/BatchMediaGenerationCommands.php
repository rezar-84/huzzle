<?php

namespace Drupal\openai_api\Commands;

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
class BatchMediaGenerationCommands extends DrushCommands {

  const IMAGE_RESOLUTION = [
    '256x256' => '256x256',
    '512x512' => '512x512',
    '1024x1024' => '1024x1024',
  ];

  /**
   * Constructs a new object.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Generate media.
   *
   * @command openai:generate-media
   * @aliases ogm
   *
   * @usage generate:media
   *
   */
  public function generateMediaDrushCommand() {

    $datas = $this->getDrushArguments();

    if ($datas['confirm']) {
      $this->initOperations(
        $datas['medias_prompts'],
      );
      drush_backend_batch_process();
    }
    else {
      $output = new ConsoleOutput();
      $output->writeln('<comment>Command aborted</comment>');
    }
  }

  /**
   * Generate medias with batch operations.
   *
   * @param array $datas
   *
   * @return void
   */
  public function generateMedias(array $datas): void {
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
    $nbreMedia = count($datas);

    for ($i = 0; $i < $nbreMedia; $i++) {
      for ($i_nbr = 0; $i_nbr < $datas[$i]['number_for_prompt']; $i_nbr++) {
        $data = [
          'iteration' => $i_nbr + 1,
          'nbr_media_generated' => $batchId,
          'image_prompt' => $datas[$i]['image_prompt'],
          'image_resolution' => $datas[$i]['image_resolution'],
        ];
        $operations[] = [
          '\Drupal\openai_api\GenerationService::generate_media',
          [
            $data,
            t('Generating @media', ['@media' => $datas[$i]['image_prompt']]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }

    $batch = [
      'title' => t('Generating @num media(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\openai_api\GenerationService::generate_medias_finished',
      'progress_message' => t('Generating @current media out of @total.'),
    ];
    batch_set($batch);
  }

  /**
   * Get Drush arguments with interactive command.
   *
   * @return array
   */
  protected function getDrushArguments(): array {
    $medias = [];

    // Interactive drush command.
    $nbrMedias = $this->io()->ask(('How many media types ?'), 1, function ($value) {
      if (!is_numeric($value)) {
        throw new \InvalidArgumentException('The value is not a number');
      }
      return $value;
    });
    for ($i = 0; $i < $nbrMedias; $i++) {
      $medias[$i]['image_prompt'] = $this->io()
        ->ask('Image ' . ($i + 1) . ' description', "A cat with glasses");
      $medias[$i]['image_resolution'] = $this->io()
        ->choice('What resolution ?', self::IMAGE_RESOLUTION, 0);
      $medias[$i]['number_for_prompt'] = $this->io()
        ->ask('How many media for this description ?', 1, function ($value) {
          if (!is_numeric($value)) {
            throw new \InvalidArgumentException('The value is not a number');
          }
          return $value;
        });
    }
    $confirm = $this->io()->confirm('Proceed with operations ?');

    return [
      'medias_prompts' => $medias,
      'confirm' => $confirm,
    ];
  }

}
