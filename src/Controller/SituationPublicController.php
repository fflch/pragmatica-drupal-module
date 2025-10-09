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


    $build['#theme'] = 'pragmatica_situation_item';
    $build['#situation'] = $pragmatica_situation;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica_styles',
      ],
    ];

    return $build;
  }

  public function itemTitle(Situation $pragmatica_situation) {
    return $pragmatica_situation->situation();
  }
}
