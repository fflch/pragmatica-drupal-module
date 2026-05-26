<?php

namespace Drupal\pragmatica\Importer;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CSVSeedImportForm extends FormBase implements TrustedCallbackInterface {

  protected $entityTypeManager;

  public static function trustedCallbacks(): array {
    return ['preRenderCsvFileInput'];
  }

  const MODULE_DATA_PATH = __DIR__ . '/../../data/seed/';

  const SEED_TYPES = [
    'pragmatica_age_interval' => [
      'label' => 'Intervalos de idade',
      'file' => 'age_intervals.csv',
    ],
    'pragmatica_gender' => [
      'label' => 'Gêneros',
      'file' => 'genders.csv',
    ],
    'pragmatica_language' => [
      'label' => 'Línguas',
      'file' => 'languages.csv',
    ],
    'pragmatica_situation' => [
      'label' => 'Situações',
      'file' => 'situations.csv',
    ],
    'pragmatica_label_type' => [
      'label' => 'Tipos de etiqueta',
      'file' => 'label_types.csv',
    ],
    'pragmatica_label' => [
      'label' => 'Etiquetas',
      'file' => 'labels.csv',
    ],
  ];

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getFormId() {
    return 'pragmatica_csv_seed_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['types'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Importar'),
        $this->t('Vocabulário controlado'),
        $this->t('Apagar existentes'),
        $this->t('Arquivo CSV (opcional)'),
      ],
      '#caption' => $this->t(
        'Marque os vocabulários controlados que deseja importar. Se nenhum arquivo for enviado, o arquivo padrão do módulo será usado.'
      ),
    ];

    foreach (self::SEED_TYPES as $entity_type => $info) {
      $form['types'][$entity_type]['import'] = [
        '#type' => 'checkbox',
        '#default_value' => FALSE,
      ];
      $form['types'][$entity_type]['label'] = [
        '#markup' => $info['label'],
      ];
      $form['types'][$entity_type]['reset'] = [
        '#type' => 'checkbox',
        '#default_value' => FALSE,
      ];
      $form['types'][$entity_type]['csv_file'] = [
        '#type' => 'file',
        '#csv_entity_type' => $entity_type,
        '#pre_render' => [[static::class, 'preRenderCsvFileInput']],
        '#upload_validators' => [
          'file_validate_extensions' => ['csv'],
        ],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Importar selecionados'),
    ];

    return $form;
  }

  /**
   * Pre-render callback: gives each CSV file input a unique name.
   */
  public static function preRenderCsvFileInput(array $element): array {
    $entity_type = $element['#csv_entity_type'];
    $element['#attributes']['name'] = 'csv_file[' . $entity_type . ']';
    // Keep the element type intact so the theme renders it as a file input.
    $element['#attributes']['type'] = 'file';
    return $element;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types_values = $form_state->getValue('types', []);
    $csv_files = \Drupal::request()->files->get('csv_file', []);
    $importer = new CSVImporter($this->entityTypeManager);

    foreach (self::SEED_TYPES as $entity_type => $info) {
      $row = $types_values[$entity_type] ?? [];
      if (empty($row['import'])) {
        continue;
      }

      $reset = !empty($row['reset']);

      // Resolve CSV path: prefer uploaded file, fall back to bundled default.
      $csv_path = NULL;
      $uploaded = is_array($csv_files) ? ($csv_files[$entity_type] ?? NULL) : NULL;
      if ($uploaded instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $uploaded->isValid()) {
        $csv_path = $uploaded->getPathname();
      }
      if (!$csv_path) {
        $csv_path = self::MODULE_DATA_PATH . $info['file'];
      }

      if (!file_exists($csv_path)) {
        $this->messenger()->addError($this->t(
          '@label: arquivo CSV não encontrado (@path).',
          ['@label' => $info['label'], '@path' => $csv_path]
        ));
        continue;
      }

      try {
        $result = $importer->importSeedData($entity_type, $csv_path, $reset);

        $parts = [];
        if ($result['deleted'] > 0) {
          $parts[] = $this->t('@n apagado(s)', ['@n' => $result['deleted']]);
        }
        $parts[] = $this->t('@n criado(s)', ['@n' => $result['created']]);
        $parts[] = $this->t('@n atualizado(s)', ['@n' => $result['updated']]);

        $this->messenger()->addStatus($this->t(
          '@label: @summary.',
          ['@label' => $info['label'], '@summary' => implode(', ', $parts)]
        ));

        foreach ($result['errors'] as $err) {
          $this->messenger()->addWarning($this->t('@label: @err', [
            '@label' => $info['label'],
            '@err' => $err,
          ]));
        }
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t(
          '@label: erro na importação — @msg',
          ['@label' => $info['label'], '@msg' => $e->getMessage()]
        ));
      }
    }
  }

}
