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
   * Displays all labels.
   */
  public function list(): array {
    $label_type_storage = $this->entityTypeManager->getStorage('pragmatica_label_type');
    $label_type_query = $label_type_storage->getQuery();
    $label_type_ids = $label_type_query->execute();
    $label_types = $label_type_storage->loadMultiple($label_type_ids);

    $label_storage = $this->entityTypeManager->getStorage('pragmatica_label');
    $processed_label_types = [];

    foreach ($label_types as $label_type) {
      /** @var \Drupal\pragmatica\Entity\LabelType $label_type */
      $current_processed_label_type = $label_type->getEntityForDisplay(null, '', false);

      $label_query = $label_storage->getQuery();
      $label_query->condition('type_id', $label_type->id());
      $label_ids = $label_query->execute();
      $labels = $label_storage->loadMultiple($label_ids);

      foreach ($labels as $label) {
        /** @var \Drupal\pragmatica\Entity\Label $label */
        $current_processed_label_type['labels'][] = $label->getEntityForDisplay();
      }

      $processed_label_types[] = $current_processed_label_type;
    }

    return [
      '#theme' => 'pragmatica_label_list',
      '#label_types' => $processed_label_types,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica',
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
      $selection_ids = $query->execute();
      $selections = $selection_storage->loadMultiple($selection_ids);

      foreach ($selections as $selection) {
        /** @var \Drupal\pragmatica\Entity\Response $response */
        $response = $selection->get('response_id')->entity;
        $processed_responses[] = $response->getEntityForDisplay();
      }
    } else {
      $pager = $this->buildPager(0, $per_page, 0, 5);
    }

    $build['#theme'] = 'pragmatica_label_item';
    $build['#label'] = $pragmatica_label->getEntityForDisplay();
    $build['#responses'] = $processed_responses;
    $build['#pager'] = $pager;
    $build['#autopins'] = [(int) $pragmatica_label->id()];
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica',
      ],
    ];

    return $build;
  }

  public function itemTitle(Label $pragmatica_label) {
    return $pragmatica_label->label();
  }
}
