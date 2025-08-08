<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pragmatica\Entity\Coding;
use Drupal\pragmatica\Entity\Code;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying codes publicly.
 */
class CodePublicController extends ControllerBase {

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
   * Displays all codes.
   */
  public function list(): array {
    $storage = $this->entityTypeManager->getStorage('pragmatica_code');
    $query = $storage->getQuery();
    $entity_ids = $query->execute();

    $codes = $storage->loadMultiple($entity_ids);

    return [
      '#theme' => 'pragmatica_code_list',
      '#codes' => $codes,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica_styles',
        ],
      ],
    ];
  }

  /**
   * Displays a single code entity.
   */
  public function item(Code $pragmatica_code): array {
    $coding_storage = $this->entityTypeManager->getStorage('pragmatica_coding');
    $query = $coding_storage->getQuery();
    $query->condition('code_id', $pragmatica_code->id());

    $coding_ids = $query->execute();
    $codings = $coding_storage->loadMultiple($coding_ids);
    $processed_selections = [];
    foreach ($codings as $coding) {
      $selection = $coding->get('selection_id')->entity;
      if ($selection) {
        $processed_selections[] = [
          'name' => $selection->label(),
          'id' => $selection->id(),
          'source_id' => $selection->get('source_id')->entity->id(),
          'source_name' => $selection->get('source_id')->entity->label(),
          'coding_id' => $coding->id()
        ];
      }
    }

    $build['#theme'] = 'pragmatica_code_item';
    $build['#code'] = $pragmatica_code;
    $build['#selections'] = $processed_selections;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica_styles',
      ],
    ];

    return $build;
  }

  public function itemTitle(Code $pragmatica_code) {
    return $pragmatica_code->label();
  }
}
