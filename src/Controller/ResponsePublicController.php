<?php


namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\pragmatica\Entity\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying responses publicly.
 */
class ResponsePublicController extends ControllerBase
{

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager)
  {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays a single response entity.
   */
  public function item(Response $pragmatica_response): array {

    $processed_response = $pragmatica_response->getEntityForDisplay();
    $informant_controller = new InformantPublicController($this->entityTypeManager);

    $build['#theme'] = 'pragmatica_response_item';
    $build['#response'] = $processed_response;
    $build['#informant_responses'] = $informant_controller->item($pragmatica_response->get('informant_id')->entity)['#responses'] ?? [];
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

  public function itemTitle(Response $pragmatica_response)
  {
    return $pragmatica_response->label();
  }
}
