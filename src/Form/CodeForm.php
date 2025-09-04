<?php

namespace Drupal\pragmatica\Form;

namespace Drupal\pragmatica\Form;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pragmatica\Entity\Code;

class CodeForm extends PragmaticaBaseForm {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var Code $code */
    $code = $this->getEntity();
    $parent_field = 'parent_id';
    $parent_target = $form_state->getValue([$parent_field, 0, 'target_id']);

    if (!$code->isNew() && $parent_target) {
      $parent_id = (int) $parent_target;
      $entity_id = (int) $code->id();


      if ($parent_id === $entity_id) {
        $form_state->setErrorByName($parent_field, $this->t('O pai não pode ser o próprio código.'));
        return;
      }

      $ancestors = [$entity_id];
      $parent = Drupal::entityTypeManager()->getStorage('pragmatica_code')->load($parent_id);
      while ($parent) {
        $parent_id = $parent->id();
        if (in_array($parent_id, $ancestors)) {
          $form_state->setErrorByName($parent_field, $this->t('Referência cíclica: o código não pode ser pai de si mesmo ou de um ancestral.'));
          return;
        }
        $ancestors[] = $parent_id;
        $parent = $parent->get($parent_field)->entity ?? NULL;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo Construir select mostrando a hierarquia de códigos
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var Code $entity */
    $entity = $this->getEntity();

    if (isset($form['color']['widget'][0]['value']['#type'])) {
      $form['color']['widget'][0]['value']['#type'] = 'color';
    }

    $options = $form['parent']['widget']['#options'] ?? [];
    if (!$entity->isNew() && isset($options[$entity->id()])) {
      unset($options[$entity->id()]);
      $form['parent']['widget']['#options'] = $options;
    }

    return $form;
  }

}
