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

    $processed_informant = [
      'code' => $pragmatica_informant->get('code')->value,
      'name' => $pragmatica_informant->get('code')->value,
      'age' => $pragmatica_informant->get('age')->value,
      'created' => $pragmatica_informant->get('created')->value,
    ];

    $informant_foreign_fields =  array_diff(Informant::getFieldsIds(), array_keys($processed_informant));
    unset($informant_foreign_fields['id']);


    foreach($informant_foreign_fields as $foreign_field) {
      $processed_informant[substr($foreign_field, 0, -3)] =
        $pragmatica_informant->get($foreign_field)->entity ? $pragmatica_informant->get($foreign_field)->entity->label() : '';
    }


    // response selections
    $response_storage = $this->entityTypeManager->getStorage('pragmatica_response');
    $query = $response_storage->getQuery();
    $query->condition('informant_id', $pragmatica_informant->get('id')->value);
    $response_ids = $query->execute();
    $responses = $response_storage->loadMultiple($response_ids);
    $processed_responses = [];

    foreach ($responses as $response) {
      $processed_responses[] = [
        'name' => $response->label(),
        'situation' =>  $response->get('situation_id')->entity->label()
        ];


    }


    $build['#theme'] = 'pragmatica_informant_item';
    $build['#informant'] = $processed_informant;
    $build['#responses'] = $processed_responses;
    $build['#attached'] = [
      'library' => [
        'pragmatica/pragmatica_styles',
      ],
    ];

    return $build;
  }

  public function itemTitle(Informant $pragmatica_informant) {
    return $pragmatica_informant->label();
  }
}
