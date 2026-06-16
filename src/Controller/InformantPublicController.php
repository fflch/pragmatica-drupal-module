<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pragmatica\Entity\Informant;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying informants publicly.
 */
class InformantPublicController extends ControllerBase {

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
   * Displays a single informant entity.
   */
  public function item(Informant $pragmatica_informant): array {
    // response selections
    $response_storage = $this->entityTypeManager->getStorage('pragmatica_response');
    $query = $response_storage->getQuery();
    $query->condition('informant_id', $pragmatica_informant->get('id')->value);
    $response_ids = $query->execute();
    $responses = $response_storage->loadMultiple($response_ids);
    $processed_responses = [];

    foreach ($responses as $response) {
      /** @var \Drupal\pragmatica\Entity\Response $response */
      $processed_responses[] = $response->getEntityForDisplay();
    }

    $build['#theme'] = 'pragmatica_informant_item';
    $build['#informant'] = $pragmatica_informant->getEntityForDisplay();
    $build['#responses'] = $processed_responses;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica',
      ],
    ];

    $build['#cache'] = [
      'contexts' => ['url.query_args'],
    ];

    return $build;
  }

  public function itemTitle(Informant $pragmatica_informant) {
    return $pragmatica_informant->label();
  }
}
