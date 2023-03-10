<?php

namespace Drupal\openai_api\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\openai_api\Commands\ContentGenerationCommand;
use Drupal\openai_api\Controller\GenerationController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use OpenAI\Client;

/**
 * Content generator.
 */
class ContentGenerationConfigForm extends ConfigFormBase {

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
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected Client $client;

  /**
   * Defining the BatchArticleGenerationCommands object.
   *
   * @var \Drupal\openai_api\Commands\ContentGenerationCommand
   */
  protected ContentGenerationCommand $contentGenerationCommand;

  /**
   * Constructs the class for dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \OpenAI\Client $client
   *   The client object.
   * @param \Drupal\openai_api\Commands\ContentGenerationCommand $command
   *   The BatchArticleGenerationCommands object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager, EntityFieldManagerInterface $entity_field_manager, Client $client, ContentGenerationCommand $command) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entity_field_manager;
    $this->client = $client;
    $this->contentGenerationCommand = $command;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ContentGenerationConfigForm|ConfigFormBase|static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('openai.client'),
      $container->get('openai_api.content_generation.commands')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_api_content_generation_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['openai_api_generation.settings'];
  }

  /**
   * {@inheritdoc}
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('openai.settings');
    $config_link = Link::createFromRoute('OpenAI settings form', 'openai.api_settings');
    $openAiController = new GenerationController(\Drupal::service('openai.client'), \Drupal::service('config.factory'));
    $subjects = $openAiController->getSubjectsVocabularyTerms();
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $filter_formats = $this->entityTypeManager->getStorage('filter_format')->loadMultiple();
    $filter_format_options = [];
    $field_options = [];
    $options = [];

    foreach ($content_types as $machine_name => $type) {
      $options[$machine_name] = $type->label();

      foreach ($this->entityFieldManager->getFieldDefinitions('node', $machine_name) as $field_name => $field_definition) {
        if (!empty($field_definition->getTargetBundle()) && (str_contains($field_definition->getType(), 'string') || str_contains($field_definition->getType(), 'text'))) {
          $field_options[$type->label()][$field_name] = $field_definition->getLabel();
        }
      }

      // Title property needs to be appended.
      $field_options[$type->label()]['title'] = $this->t('Title');
    }

    foreach ($filter_formats as $machine_name => $format) {
      $filter_format_options[$machine_name] = $format->label();
    }

    if (!$config->get('api_key')) {
      $form['no_config'] = [
        '#type' => 'markup',
        '#markup' => 'API key missing, please enter your OpenAI API key first to continue at ' . $config_link->toString()
            ->getGeneratedLink(),
      ];
    }
    else {

      $form['article_generate_container'] = [
        '#type' => 'container',
        '#prefix' => '<div id="articles-container">',
        '#suffix' => '</div>',
      ];

      for ($i = 1; $i <= $this->number; $i++) {
        $form['article_generate_container']['container_for_article_fields_'.$i] = [
          '#type' => 'vertical_tabs',
          '#title' => 'Field group for subject #'.$i,
          '#prefix' => '<div id="subject-fields-group-'.$i.'">',
          '#suffix' => '</div>',
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['subjects_container_'.$i] = [
          '#type' => 'details',
          '#title' => $this->t('Subjects'),
          '#required' => TRUE,
          '#group' => 'container_for_article_fields_'.$i,
          '#weight' => 1,
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['subjects_container_'.$i]['subject_checkbox_'.$i] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Check this box to input subject manually in a text field.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['subjects_container_'.$i]['subject_text_'.$i] = [
          '#type' => 'textfield',
          '#title' => $this->t('Subject.'),
          '#states' => [
            'visible' => [
              ':input[name="subject_checkbox_'.$i.'"]' => ['checked' => TRUE],
            ],
            'required' => [
              ':input[name="subject_checkbox_'.$i.'"]' => ['checked' => TRUE],
            ]
          ]
        ];

        if ($subjects) {
          $form['article_generate_container']['container_for_article_fields_'.$i]['subjects_container_'.$i]['subject_'.$i] = [
            '#type' => 'radios',
            '#options' => $subjects,
            '#required' => TRUE,
            '#default_value' => 1,
            '#title' => $this->t('Subject'),
            '#states' => [
              'visible' => [
                ':input[name="subject_checkbox_'.$i.'"]' => ['checked' => FALSE],
              ],
              'required' => [
                ':input[name="subject_checkbox_'.$i.'"]' => ['checked' => FALSE],
              ]
            ]
          ];
        } else {
          $form['article_generate_container']['container_for_article_fields_'.$i]['subjects_container_'.$i]['subject_checkbox_'.$i]['#required'] = TRUE;
        }

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i] = [
          '#type' => 'details',
          '#title' => $this->t('Options'),
          '#required' => TRUE,
          '#group' => 'container_for_article_fields_'.$i,
          '#weight' => 2,
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['content_type_'.$i] = [
          '#type' => 'select',
          '#options' => $options,
          '#required' => TRUE,
          '#default_value' => array_key_first($options),
          '#title' => $this->t('Content type'),
          '#description' => $this->t('Select the content type to generate.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['title_field_'.$i] = [
          '#type' => 'select',
          '#options' => $field_options,
          '#required' => TRUE,
          '#default_value' => array_key_first($field_options),
          '#title' => $this->t('Title field'),
          '#description' => $this->t('Select the title field.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['body_field_'.$i] = [
          '#type' => 'select',
          '#options' => $field_options,
          '#required' => TRUE,
          '#default_value' => array_key_first($field_options),
          '#title' => $this->t('Body'),
          '#description' => $this->t('Select the body field.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['filter_format_'.$i] = [
          '#type' => 'select',
          '#options' => $filter_format_options,
          '#required' => TRUE,
          '#default_value' => array_key_first($filter_format_options),
          '#title' => $this->t('Filter format'),
          '#description' => $this->t('Select the filter format to use on the body text.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['save_as_html_'.$i] = [
          '#type' => 'checkbox',
          '#default_value' => FALSE,
          '#title' => $this->t('Get response in HTML markup'),
          '#description' => $this->t('Informs OpenAI to return the response in HTML markup. For example, when asking for items in list format or with headings. Warning: depending on what you ask and how you ask it in conjunction with a format like Full HTML could introduce a security risk. Review everything you get back in source mode on the editor.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['model_'.$i] = [
          '#type' => 'select',
          '#options' => self::MODELS_OPTIONS,
          '#required' => TRUE,
          '#default_value' => 'text-davinci-003',
          '#title' => $this->t('Models'),
          '#description' => $this->t('
          A set of models that can understand and generate natural language<br>
          <b>text-davinci-003</b> : Most capable. Can do any task the other models can do.<br>
          <b>text-curie-001</b> : Very capable, but faster and lower cost than Davinci.<br>
          <b>text-babbage-001</b> : Capable of straightforward tasks, very fast, and lower cost.<br>
          <b>text-ada-001</b> : Capable of very simple tasks, usually the fastest model, and lower cost.
      '
          ),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['max_token_'.$i] = [
          '#type' => 'number',
          '#min' => 64,
          '#max' => 4096,
          '#step' => 1,
          '#title' => $this->t('Maximum length'),
          '#required' => TRUE,
          '#default_value' => 128,
          '#description' => $this->t('The maximum number of tokens to generate in the completion.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['temperature_'.$i] = [
          '#type' => 'number',
          '#min' => 0,
          '#max' => 2,
          '#step' => 0.1,
          '#title' => $this->t('Temperature'),
          '#required' => TRUE,
          '#default_value' => 0,
          '#description' => $this->t('Higher values means the model will take more risks. Try 1 for more creative applications, and 0 for ones with a well-defined answer.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['options_container_'.$i]['number_for_prompt_'.$i] = [
          '#type' => 'number',
          '#min' => 1,
          '#max' => 100,
          '#title' => $this->t('Number of node(s)'),
          '#required' => FALSE,
          '#default_value' => 1,
          '#description' => $this->t('Number of node(s) to generate with the above options.'),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['image_container_'.$i] = [
          '#type' => 'details',
          '#title' => $this->t('Media'),
          '#weight' => 3,
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['image_container_'.$i]['article_image_prompt_'.$i] = [
          '#type' => 'textfield',
          '#title' => $this->t('Image description'),
          '#maxlength' => 255,
          '#description' => $this->t("The more detailed the description, the more likely you are to get the result that you or your end user want."),
        ];

        $form['article_generate_container']['container_for_article_fields_'.$i]['image_container_'.$i]['article_image_resolution_'.$i] = [
          '#type' => 'select',
          '#title' => $this->t('Image resolution'),
          '#options' => self::IMAGE_RESOLUTION,
          '#description' => $this->t("The wanted image resolution"),
        ];
      }

      $form['article_generate_container']['add_fields'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Add another field group'),
        '#submit' => ['::callback_add_field_group'],
        '#ajax'   => [
          'callback' => '::ajax_callback',
          'wrapper'  => 'articles-container',
        ],
      ];

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
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
    return $form['article_generate_container'];
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
    $articles = [];

    for ($i = 0; $i < $this->number; $i++) {
      $subject = $form_state->getValue('subject_'.$i+1);
      if ($form_state->getValue('subject_checkbox_'.($i+1)) == 1) {
        $subject = $form_state->getValue('subject_text_'.$i+1);
      }

      $articles[$i]['subject'] = $subject;
      $articles[$i]['model'] = $form_state->getValue('model_'.$i+1);
      $articles[$i]['content_type'] = $form_state->getValue('content_type_'.$i+1);
      $articles[$i]['title'] = $form_state->getValue('title_field_'.$i+1);
      $articles[$i]['body'] = $form_state->getValue('body_field_'.$i+1);
      $articles[$i]['max_token'] = $form_state->getValue('max_token_'.$i+1);
      $articles[$i]['filter_format'] = $form_state->getValue('filter_format_'.$i+1);
      $articles[$i]['save_html'] = $form_state->getValue('save_as_html_'.$i+1);
      $articles[$i]['temperature'] = $form_state->getValue('temperature_'.$i+1);
      $articles[$i]['image_prompt'] = $form_state->getValue('article_image_prompt_'.$i+1);
      $articles[$i]['image_resolution'] = $form_state->getValue('article_image_resolution_'.$i+1);
      $articles[$i]['number_for_prompt'] = $form_state->getValue('number_for_prompt_'.$i+1);
    }

    $this->contentGenerationCommand->generateContent($articles);
  }

}
