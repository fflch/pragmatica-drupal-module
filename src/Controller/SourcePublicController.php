<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pragmatica\Entity\Coding;
use Drupal\pragmatica\Entity\Source;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying sources publicly.
 */
class SourcePublicController extends ControllerBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays all sources.
   */
  public function list(): array {
    $storage = $this->entityTypeManager->getStorage('pragmatica_source');
    $query = $storage->getQuery();
    $entity_ids = $query->execute();

    $sources = $storage->loadMultiple($entity_ids);

    return [
      '#theme' => 'pragmatica_source_list',
      '#sources' => $sources,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica_styles',
        ],
      ],
    ];
  }

  /**
   * Displays a single source entity.
   */
  public function item(Source $pragmatica_source): array {
    $coding_storage = $this->entityTypeManager()->getStorage('pragmatica_coding');
    $query = $coding_storage->getQuery();

    $selection_ids = $this->entityTypeManager()->getStorage('pragmatica_selection')
      ->getQuery()
      ->condition('source_id', $pragmatica_source->id())
      ->execute();

    $codings = [];
    if (!empty($selection_ids)) {
      $codings = $coding_storage->loadMultiple($query
        ->condition('selection_id', $selection_ids, 'IN')
        ->execute());
    }

    $processed_codings = [];
    foreach ($codings as $coding) {
      /** @var Coding $coding */
      $selection = $coding->get('selection_id')->entity;
      $code = $coding->get('code_id')->entity;
      if ($selection && $code) {
        $processed_codings[] = [
          'start' => $selection->get('start_position')->value,
          'end' => $selection->get('end_position')->value,
          'code' => $code->label(),
          'color' => $code->get('color')->value,
          'code_id' => $code->id(),
          'description' => $code->get('description')->value,
        ];
      }
    }

    $build['#theme'] = 'pragmatica_source_item';
    $build['#source'] = $pragmatica_source;
    $build['#codings'] = $processed_codings;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica_styles',
        'pragmatica/pragmatica_panel',
      ],
      'drupalSettings' => [
        'pragmatica' => [
          'sourcePlainText' => $pragmatica_source->get('plain_text')->value,
        ],
      ],
    ];

    return $build;
  }

  public function itemTitle(Source $pragmatica_source) {
    return $pragmatica_source->label();
  }
}
