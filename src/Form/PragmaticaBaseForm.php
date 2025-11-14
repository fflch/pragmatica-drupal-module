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
    if ($this->getEntity()->isNew() && $form_state->getValue('guid') === NULL) {
      $form_state->setValue('guid', strtoupper(Drupal::service('uuid')->generate()));
    } else {
      if ($this->getEntity()->hasField('guid')) {
        $guid = $this->getEntity()->get('guid')->value;
        if (empty($guid)) {
          $form_state->setValue('guid', strtoupper(Drupal::service('uuid')->generate()));
        }
      }
    }

    parent::submitForm($form, $form_state);
  }
}
