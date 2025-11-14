<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class PragmaticaController extends ControllerBase {

  public function dashboard(): array {
    $build = [
      '#markup' => '
        <h2>Módulo Pragmática</h2>
        <p>Importa, gerencia e exibe dados de análises linguísticas do Grupo de Pesquisa - Pragmática (inter)linguística, intercultural e cross-cultural (GPP) (FFLCH-USP).</p>
       '
    ];

    $links = [];

    $entity_types = [];

    foreach ($entity_types as $entity_type => $label) {
      $url = Url::fromRoute("entity.{$entity_type}.collection");
      $links[] = Link::fromTextAndUrl($label, $url)->toRenderable();
    }

    $build['links'] = [
      '#theme' => 'item_list',
      '#items' => $links,
    ];

    return $build;
  }
}
