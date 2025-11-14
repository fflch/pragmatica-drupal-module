<?php

namespace Drupal\pragmatica\Entity;

use Drupal;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;

/**
 * Defines the Response content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_response",
 *   label = @Translation("Resposta"),
 *   label_plural = @Translation("Respostas"),
 *   base_table = "pragmatica_response",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\pragmatica\ListBuilder\PragmaticaBaseListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pragmatica\Form\PragmaticaBaseForm",
 *       "edit" = "Drupal\pragmatica\Form\PragmaticaBaseForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     }
 *   },
 *   links = {
 *     "canonical" = "/admin/pragmatica/response/{pragmatica_response}",
 *     "add-form" = "/admin/pragmatica/response/add",
 *     "edit-form" = "/admin/pragmatica/response/{pragmatica_response}/edit",
 *     "delete-form" = "/admin/pragmatica/response/{pragmatica_response}/delete",
 *     "collection" = "/admin/pragmatica/response"
 *   }
 * )
 */
class Response extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id',
      'name',
      'situation_id',
      'informant_id',
      'source_id',
      'created',
      'changed',
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['situation_id'] = t('Situação');
    $header['informant_id'] = t('Informante');
    return $this->addItemsAfterKeyInArray($header, $parent, 'name');
  }

  public function buildListRow(PragmaticaBaseEntity $entity): array {
    /** @var self $entity */
    $row = parent::buildListRow($entity);
    $row['situation_id'] = $entity->get('situation_id')->entity ? $entity->get('situation_id')->entity->label() : '';
    $row['informant_id'] = $entity->get('informant_id')->entity ? $entity->get('informant_id')->entity->label() : '';
    return $row;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['name'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Resposta'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 0,
      ]);

    $fields['situation_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Situação'))
      ->setSetting('target_type', 'pragmatica_situation')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 5,
      ]);

    $fields['informant_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Informante'))
      ->setSetting('target_type', 'pragmatica_informant')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 6,
      ]);

    $fields['source_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Fonte'))
      ->setSetting('target_type', 'pragmatica_source')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 6,
      ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }

  public function getLabels() {
    $selection_storage = Drupal::service('entity_type.manager')->getStorage('pragmatica_selection');
    $query = $selection_storage->getQuery();
    $query->condition('response_id', $this->id());
    $selection_ids = $query->execute();

    /** @var \Drupal\pragmatica\Entity\Selection[] $selections */
    $selections = $selection_storage->loadMultiple($selection_ids);
    $processed_labels = [];

    foreach ($selections as $selection) {
      /** @var \Drupal\pragmatica\Entity\Label $selection_label_entity */
      $selection_label_entity = $selection->get('label_id')->entity;

      $label_display = $selection_label_entity->getEntityForDisplay($selection_label_entity);
      $label_display['start_position'] = $selection->get('start_position')->value;
      $label_display['end_position'] = $selection->get('end_position')->value;

      $processed_labels[] = $label_display;
    }

    return $processed_labels;
  }


  public function getEntityForDisplay(
    PragmaticaBaseEntity $base_entity = null,
    $label_prefix = '',
    $add_url = TRUE
  ) {

    if (!$base_entity) {
      $base_entity = $this;
    }

    $display = parent::getEntityForDisplay($base_entity, $label_prefix, $add_url);
    $display['informant'] = $base_entity->getRelatedEntityForDisplay('informant_id', $base_entity, true);
    $display['situation'] = $base_entity->getRelatedEntityForDisplay('situation_id', $base_entity, true);
    $display['labels'] = $base_entity->getLabels();

    return $display;
  }
}
