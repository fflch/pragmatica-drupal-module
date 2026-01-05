<?php

namespace Drupal\pragmatica\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\pragmatica\Form\PragmaticaPublicSearchForm;

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
   * @todo: Paginate results.
   */
  public function search(Request $request) {
    $query_params = array_merge($request->query->all(), $request->request->all());
    $results = [];

    $form = new PragmaticaPublicSearchForm();
    $form->setFormValues($query_params);
    $response_storage = $this->entityTypeManager->getStorage('pragmatica_response');
    $query = $response_storage->getQuery();
    $query = $form->buildSearchQuery($query);

    $per_page = 24;
    $page = (int) ($request->query->get('page', 0));

    $count_query = clone $query;
    $total = (int) $count_query->count()->execute();

    $pager = $this->buildPager($total, $per_page, $page, 5);
    $page = $pager['current'];

    if ($total > 0) {
      $offset = $page * $per_page;
      $query->range($offset, $per_page);
      $response_ids = $query->execute();

      if (!empty($response_ids)) {
        /** @var \Drupal\pragmatica\Entity\Response[] $responses */
        $responses = $response_storage->loadMultiple($response_ids);
        $results['responses'] = [];
        foreach ($responses as $response) {
          $results['responses'][] =  $response->getEntityForDisplay();
        }
      }
    }

    return [
      '#theme' => 'pragmatica_search_results',
      '#query' => '',
      '#results' => $results,
      '#filters' => $form->getFieldConfig(),
      '#pager' => $pager,
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica'
        ],
      ],
    ];
  }

  /**
   * Build pager metadata and visible page window.
   *
   * @param int $total Total number of items.
   * @param int $per_page Items per page.
   * @param int $current_page Current page index (0-based).
   * @param int $max_visible  Maximum number of visible page links in the window.
   *
   * @return array
   *   Pager metadata with keys: current, total, per_page, total_pages, pages (array of ['index','label','is_current']).
   */
  private function buildPager(int $total, int $per_page, int $current_page = 0, int $max_visible = 5) : array {
    $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;

    if ($current_page < 0) {
      $current_page = 0;
    }
    if ($total_pages > 0 && $current_page >= $total_pages) {
      $current_page = $total_pages - 1;
    }

    if ($total_pages <= $max_visible) {
      $start = 0;
      $end = $total_pages - 1;
    }
    else {
      $half = (int) floor($max_visible / 2);
      $start = $current_page - $half;
      if ($start < 0) {
        $start = 0;
      }
      $end = $start + $max_visible - 1;
      if ($end > $total_pages - 1) {
        $end = $total_pages - 1;
        $start = $end - $max_visible + 1;
      }
    }

    $visible_pages = $total_pages > 0 ? range($start, $end) : [];
    $pages = [];
    foreach ($visible_pages as $i) {
      $pages[] = [
        'index' => $i,
        'label' => $i + 1,
        'is_current' => $i === $current_page,
      ];
    }

    return [
      'current' => $current_page,
      'total' => $total,
      'per_page' => $per_page,
      'total_pages' => $total_pages,
      'pages' => $pages,
    ];
  }
}
