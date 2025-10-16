<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pragmatica\Entity\Situation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying situations publicly.
 */
class SituationPublicController extends ControllerBase {

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
   * Displays a single situation entity.
   */
  public function item(Situation $pragmatica_situation): array {
    $response_storage = $this->entityTypeManager->getStorage('pragmatica_response');
    $query = $response_storage->getQuery();
    $query->condition('situation_id', $pragmatica_situation->id());

    $response_ids = $query->execute();
    $responses = $response_storage->loadMultiple($response_ids);
    $processed_responses = [];

    foreach ($responses as $response) {
      $processed_responses[] = [
        'name' => $response->label(),
        'id' => $response->id(),
        'informant_id' => $response->get('informant_id')->entity->id()
      ];
    }

    $build['#theme'] = 'pragmatica_situation_item';
    $build['#situation'] = $pragmatica_situation;
    $build['#responses'] = $processed_responses;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica_styles',
      ],
    ];

    return $build;
  }

  public function itemTitle(Situation $pragmatica_situation) {
    return $pragmatica_situation->label();
  }
}
