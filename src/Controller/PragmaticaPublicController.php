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
      '#filter_groups' => $form->getGroupedFieldConfig(),
      '#active_filters' => $form->getActiveFiltersDisplay(),
      '#pager' => $pager,
      '#autopins' => $form->getSelectedLabelIds(),
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica'
        ],
      ],
    ];
  }

}
