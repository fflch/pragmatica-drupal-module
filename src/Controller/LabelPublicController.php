<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pragmatica\Entity\Label;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying labels publicly.
 */
class LabelPublicController extends ControllerBase {

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
   * Displays all labels.
   */
  public function list(): array {
    $storage = $this->entityTypeManager->getStorage('pragmatica_label');
    $query = $storage->getQuery();
    $entity_ids = $query->execute();

    $labels = $storage->loadMultiple($entity_ids);

    return [
      '#theme' => 'pragmatica_label_list',
      '#labels' => $labels,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica_styles',
        ],
      ],
    ];
  }

  /**
   * Displays a single label entity.
   */
  public function item(Label $pragmatica_label): array {
    $selection_storage = $this->entityTypeManager->getStorage('pragmatica_selection');
    $query = $selection_storage->getQuery();
    $query->condition('label_id', $pragmatica_label->id());

    $selection_ids = $query->execute();
    $selections = $selection_storage->loadMultiple($selection_ids);
    $processed_selections = [];
    foreach ($selections as $selection) {
        $processed_selections[] = [
          'name' => $selection->label(),
          'id' => $selection->id(),
          'response_id' => $selection->get('response_id')->entity->id()
        ];
    }

    $build['#theme'] = 'pragmatica_label_item';
    $build['#label'] = $pragmatica_label;
    $build['#selections'] = $processed_selections;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica_styles',
      ],
    ];

    return $build;
  }

  public function itemTitle(Label $pragmatica_label) {
    return $pragmatica_label->label();
  }
}
