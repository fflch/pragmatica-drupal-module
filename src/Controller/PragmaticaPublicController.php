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
   */
  public function search(Request $request) {
    $query_params = $request->request->all();
    $results = [];

    $form = new PragmaticaPublicSearchForm();
    $form->setFormValues($query_params);
    $response_storage = $this->entityTypeManager->getStorage('pragmatica_response');
    $query = $response_storage->getQuery();
    $query = $form->buildSearchQuery($query);
   
    $response_ids = $query->execute();

    // TODO: Only show labels related responses (this is already done in the buildSearchQuery, it should be keep and used it here).
    $tags = $form->getEntityOptions('label');
    $tags_display = [];
    foreach ($tags as $tag_id => $tag_label) {
      $tags_display[] = [
        'label' => strtoupper($tag_label),
        'color' => '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
        'tooltip' => "Ato de pragmatica: $tag_label",
        'url' => Url::fromRoute('pragmatica.label_public_item', ['pragmatica_label' => $tag_id])->toString(),
      ];
    }


    if (!empty($response_ids)) {
      $responses = $response_storage->loadMultiple($response_ids);
      $results['responses'] = [];
      foreach ($responses as $response) {
        shuffle($tags_display);
        $results['responses'][] = [
          'label' => $response->label(),
          'url' => Url::fromRoute('pragmatica.public_response_item', ['pragmatica_response' => $response->id()])->toString(),
          'informant' => [
            'label' => 'Informante: ' . $response->get('informant_id')->entity->label(),
            'url' => Url::fromRoute('pragmatica.public_informant_item', ['pragmatica_informant' => $response->get('informant_id')->entity->id()])->toString(),
            'tooltip' => $response->get('informant_id')->entity->getDisplay(),
          ],
          'situation' => [
            'label' => 'Situacão: ' . $response->get('situation_id')->entity->label(),
            'url' => Url::fromRoute('pragmatica.public_situation_item', ['pragmatica_situation' => $response->get('situation_id')->entity->id()])->toString(),
            'tooltip' => $response->get('situation_id')->entity->get('name')->value
          ],
          'tags' => array_slice($tags_display, 0, mt_rand(5, 8))
        ];
      }
    }

    $render_elements = [];

    $render_elements[] = [
      '#theme' => 'pragmatica_search_results',
      '#query' => '',
      '#results' => $results,
      '#filters' => $form->getFieldConfig(),
      '#attached' => [
        'library' => [
          'pragmatica/pragmatica'
        ],
      ],
    ];

    return $render_elements;
  }
}
