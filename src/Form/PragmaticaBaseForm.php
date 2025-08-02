<?php
namespace Drupal\pragmatica\Form;

use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base form for Pragmatica type entities.
 */
class PragmaticaBaseForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->getEntity()->isNew()) {
      $form_state->setValue('guid', Drupal::service('uuid')->generate());
    } else {
      if (!$this->getEntity()->hasField('guid') || !$this->getEntity()->get('guid')->value) {
        $form_state->setValue('guid', $this->getEntity()->get('guid')->value);
      }
    }
    parent::submitForm($form, $form_state);
  }
}
