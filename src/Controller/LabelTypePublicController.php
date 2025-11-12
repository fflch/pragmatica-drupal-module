<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pragmatica\Entity\LabelType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying label_type types publicly.
 */
class LabelTypePublicController extends ControllerBase {

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

  public function list(): array {
    // load label_types
    $label_type_storage = $this->entityTypeManager->getStorage('pragmatica_label_type');
    $label_type_query = $label_type_storage->getQuery();
    $label_type_ids = $label_type_query->execute();
    $label_types = $label_type_storage->loadMultiple($label_type_ids);

    $label_storage = $this->entityTypeManager->getStorage('pragmatica_label');
    $processed_label_types = [];

    foreach ($label_types as $label_type) {
      $current_processed_label_type = $label_type->processDataForDisplay($label_type);

      // load labels in relationship to label_types
      $label_query = $label_storage->getQuery();
      $label_query->condition('type_id', $label_type->id());
      $label_ids = $label_query->execute();
      $labels = $label_storage->loadMultiple($label_ids);

      foreach ($labels as $label) {
        $current_processed_label_type['labels'][] = $label->processDataForDisplay($label);

      }
      $processed_label_types[] = $current_processed_label_type;

    }

//
//    var_dump($processed_label_types);
//    exit;

    return [
      '#theme' => 'pragmatica_label_type_list',
      '#label_types' => $processed_label_types,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica',
        ],
      ],
    ];
  }


  /**
   * Displays a single label_type type entity.
   */



  public function item(LabelType $pragmatica_label_type): array {


    $build['#theme'] = 'pragmatica_label_type_item';
    $build['#label_type'] = $pragmatica_label_type;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica',
      ],
    ];

    return $build;
  }

  public function itemTitle(LabelType $pragmatica_label_type) {
    return $pragmatica_label_type->label_type();
  }
}
