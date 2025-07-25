<?php

namespace Drupal\pragmatica\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Pragmática routes.
 */
class PragmaticaController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
