<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\pragmatica\Entity\Coding;
use Drupal\pragmatica\Entity\Source;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class PragmaticaPublicController extends ControllerBase {

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
   * @param  \Symfony\Component\HttpFoundation\Request  $request
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @todo: Highlight search results in the UI.
   * @todo: Include selections as results.
   */
  public function search(Request $request) {
    $query_term = $request->query->get('q');
    $results = [];

    $code_storage = $this->entityTypeManager()->getStorage('pragmatica_code');
    $code_query = $code_storage->getQuery();
    $source_storage = $this->entityTypeManager()->getStorage('pragmatica_source');
    $source_query = $source_storage->getQuery();

    if (!empty($query_term)) {
      $query_term = trim($query_term);

      $source_or_condition = $source_query->orConditionGroup()
        ->condition('name', $query_term, 'CONTAINS')
        ->condition('description', $query_term, 'CONTAINS')
        ->condition('plain_text', $query_term, 'CONTAINS');

      $source_query->condition($source_or_condition);

      $source_ids = $source_query->execute();
      $source_results = $source_storage->loadMultiple($source_ids);

      $code_or_condition = $code_query->orConditionGroup()
        ->condition('name', $query_term, 'CONTAINS')
        ->condition('description', $query_term, 'CONTAINS');

      $code_query->condition($code_or_condition);

      $code_ids = $code_query->execute();
      $code_results = $code_storage->loadMultiple($code_ids);

      if (!empty($source_results)) {
        $results['sources'] = [];
        foreach ($source_results as $source) {
          $results['sources'][] = [
            'name' => $source->label(),
            'url' => Url::fromRoute('pragmatica.source_public_item', ['pragmatica_source' => $source->id()])->toString(),
          ];
        }
      }

      if (!empty($code_results)) {
        $results['codes'] = [];
        foreach ($code_results as $code) {
          $results['codes'][] = [
            'name' => $code->label(),
            'url' => Url::fromRoute('pragmatica.code_public_item', ['pragmatica_code' => $code->id()])->toString(),
          ];
        }
      }
    }

    return [
      '#theme' => 'pragmatica_search_results',
      '#query' => $query_term,
      '#results' => $results,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica_styles',
        ],
      ],
    ];
  }
}
