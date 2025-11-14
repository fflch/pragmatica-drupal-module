<?php

namespace Drupal\pragmatica\Form;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pragmatica\Entity\Label;

class LabelForm extends PragmaticaBaseForm {

  /**
   * {@inheritdoc}
   *
   * @todo Construir select mostrando a hierarquia de códigos
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if (isset($form['color']['widget'][0]['value']['#type'])) {
      $form['color']['widget'][0]['value']['#type'] = 'color';
    }

    return $form;
  }

}
