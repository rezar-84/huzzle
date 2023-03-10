<?php

namespace Drupal\openai_api\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\openai_api\Commands\BatchMediaGenerationCommands;
use Drupal\openai_api\GenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure openai api settings for this site.
 */
class ImageGenerationConfigForm extends ConfigFormBase {

  const IMAGE_RESOLUTION = [
    '256x256' => '256x256',
    '512x512' => '512x512',
    '1024x1024' => '1024x1024',
  ];

  /**
   * @var int
   */
  protected int $number = 1;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   * Defining the GenerationService object.
   *
   * @var \Drupal\openai_api\GenerationService
   */
  protected GenerationService $generationService;

  /**
   * Defining the BatchMediaGenerationCommands object.
   *
   * @var \Drupal\openai_api\Commands\BatchMediaGenerationCommands
   */
  protected BatchMediaGenerationCommands $batchMediaGeneration;

  /**
   * Constructs the class for dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\openai_api\GenerationService $generationService
   *   The GenerationService object.
   * @param \Drupal\openai_api\Commands\BatchMediaGenerationCommands $batchMediaGeneration
   *   The GenerationService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager, GenerationService $generationService, BatchMediaGenerationCommands $batchMediaGeneration) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->generationService = $generationService;
    $this->batchMediaGeneration = $batchMediaGeneration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ContentGenerationConfigForm|ConfigFormBase|static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('openai_api.generation.service'),
      $container->get('openai_api.media_generation.commands')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_api_image_generation_config';
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

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['openai_api_generation_image.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('openai.settings');
    $config_link = Link::createFromRoute('OpenAI settings form', 'openai.api_settings');

    if ($config->get('api_key')) {
      $form['image_generate_container']['no_config'] = [
        '#type' => 'markup',
        '#markup' => 'Please fill openai api settings in ' . $config_link->toString()
            ->getGeneratedLink(),
      ];
    }
    else {

      $form['image_generate_container'] = [
        '#type' => 'container',
        '#prefix' => '<div id="medias-container">',
        '#suffix' => '</div>',
      ];

      for ($i = 1; $i <= $this->number; $i++) {
        $form['image_generate_container']['container_for_medias_fields_'.$i] = [
          '#type' => 'vertical_tabs',
          '#title' => 'Fields group for media '.$i,
          '#prefix' => '<div id="media-fields-group-'.$i.'">',
          '#suffix' => '</div>',
        ];

        $form['image_generate_container']['container_for_medias_fields_'.$i]['media_container_'.$i] = [
          '#type' => 'details',
          '#title' => $this->t('Media options'),
          '#group' => 'container_for_medias_fields_'.$i,
          '#weight' => 1,
        ];

        $form['image_generate_container']['container_for_medias_fields_'.$i]['media_container_'.$i]['accordion_'.$i] = [
          '#type' => 'details',
          '#title' => $this->t('Media options'),
        ];

        $form['image_generate_container']['container_for_medias_fields_'.$i]['media_container_'.$i]['accordion_'.$i]['image_prompt_'.$i] = [
          '#type' => 'textfield',
          '#title' => $this->t('Image description'),
          '#required' => TRUE,
          '#maxlength' => 255,
          '#description' => $this->t("The more detailed the description, the more likely you are to get the result that you or your end user want."),
        ];

        $form['image_generate_container']['container_for_medias_fields_'.$i]['media_container_'.$i]['accordion_'.$i]['image_resolution_'.$i] = [
          '#type' => 'select',
          '#title' => $this->t('Image resolution'),
          '#options' => self::IMAGE_RESOLUTION,
          '#description' => $this->t("The wanted image resolution"),
        ];

        $form['image_generate_container']['container_for_medias_fields_'.$i]['media_container_'.$i]['accordion_'.$i]['number_for_prompt_'.$i] = [
          '#type' => 'number',
          '#title' => $this->t('Number of media(s)'),
          '#min' => 1,
          '#max' => 100,
          '#description' => $this->t("Number of media(s) to generate with this description"),
        ];
      }

      $form['image_generate_container']['add_fields'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Add another field group'),
        '#submit' => ['::callback_add_field_group'],
        '#ajax'   => [
          'callback' => '::ajax_callback',
          'wrapper'  => 'medias-container',
        ],
      ];

      $form['image_generate_container']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Generate'),
        '#button_type' => 'primary',
      ];
    }

    // Disable caching on this form.
    $form_state->setCached(FALSE);

    return $form;
  }

  /**
   * Implements callback for Ajax event on color selection.
   *
   * @param array $form
   *   From render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of form.
   *
   * @return array
   *   Color selection section of the form.
   */
  public function ajax_callback(array $form, FormStateInterface $form_state): array {
    return $form['image_generate_container'];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function callback_add_field_group(array &$form, FormStateInterface $form_state): void {
    $this->number++;
    $form_state->setRebuild();
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $medias = [];

    for ($i = 0; $i < $this->number; $i++) {
      if ($form_state->getValue('image_prompt_'.$i+1) != "") {
        $medias[$i]['image_prompt'] = $form_state->getValue('image_prompt_'.$i+1);
        $medias[$i]['image_resolution'] = $form_state->getValue('image_resolution_'.$i+1);
        $medias[$i]['number_for_prompt'] = $form_state->getValue('number_for_prompt_'.$i+1);
      }
    }

    $this->batchMediaGeneration->generateMedias($medias);
  }

}
