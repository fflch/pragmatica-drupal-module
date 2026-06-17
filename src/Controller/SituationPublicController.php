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

  use PagerTrait;

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

    // Pagination for situation responses.
    $per_page = 24;
    $page = (int) \Drupal::request()->query->get('page', 0);

    $count_query = clone $query;
    $total = (int) $count_query->count()->execute();

    $pager = $this->buildPager($total, $per_page, $page, 5);
    $page = $pager['current'];

    $processed_responses = [];
    if ($total > 0) {
      $offset = $page * $per_page;
      $query->range($offset, $per_page);
      $response_ids = $query->execute();
      $responses = $response_storage->loadMultiple($response_ids);

      foreach ($responses as $response) {
        /** @var \Drupal\pragmatica\Entity\Response $response */
        $processed_responses[] = $response->getEntityForDisplay();
      }
    }
    else {
      $pager = $this->buildPager(0, $per_page, 0, 5);
    }

    $build['#theme'] = 'pragmatica_situation_item';
    $build['#situation'] = $pragmatica_situation->getEntityForDisplay();
    $build['#responses'] = $processed_responses;
    $build['#pager'] = $pager;
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

  public function itemTitle(Situation $pragmatica_situation) {
    return $pragmatica_situation->label();
  }
}
