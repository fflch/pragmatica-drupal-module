<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Selection entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_selection",
 *   label = @Translation("Marcação"),
 *   label_plural = @Translation("Marcações"),
 *   base_table = "pragmatica_selection",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
 *   },
 *   handlers = {
 *   "list_builder" = "Drupal\pragmatica\ListBuilder\PragmaticaBaseListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pragmatica\Form\PragmaticaBaseForm",
 *       "edit" = "Drupal\pragmatica\Form\PragmaticaBaseForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *   },
 *   links = {
 *     "canonical" = "/admin/pragmatica/selection/{pragmatica_selection}",
 *     "add-form" = "/admin/pragmatica/selection/add",
 *     "edit-form" = "/admin/pragmatica/selection/{pragmatica_selection}/edit",
 *     "delete-form" = "/admin/pragmatica/selection/{pragmatica_selection}/delete",
 *     "collection" = "/admin/pragmatica/selection"
 *   }
 * )
 */
class Selection extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id',
      'name',
      'response_id',
      'label_id',
      'start_position',
      'end_position',
      'created',
      'changed',
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    $mapping = [
      'start_position' => 'startPosition',
      'end_position' => 'endPosition',
    ];

    return parent::addFieldsToXmlMapping($mapping, self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $parent['name'] = t('Trecho marcado');
    $header['response_id'] = t('Resposta');
    $header['label_id'] = t('Etiqueta');
    return $this->addItemsAfterKeyInArray($header, $parent, 'name');
  }


  public function buildListRow(PragmaticaBaseEntity $entity): array {
    $row = parent::buildListRow($entity);
    $row['response_id'] = $entity->get('response_id')->entity ? $entity->get('response_id')->entity->label() : '';
    $row['label_id'] = $entity->get('label_id')->entity ? $entity->get('label_id')->entity->label() : '';
    return $row;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
 
    $fields = [];

    $fields['name'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Trecho marcado'))
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

    $fields['response_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Resposta'))
      ->setDescription(t('Resposta à qual este trecho pertence.'))
      ->setSetting('target_type', 'pragmatica_response')
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
  

    $fields['label_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Etiqueta'))
      ->setDescription(t('Etiqueta utilizada para marcar este trecho.'))
      ->setSetting('target_type', 'pragmatica_label')
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
      

    $fields['start_position'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Posição inicial do texto'))
      ->setSetting('min', 0)
      ->setDescription(t('A posição inicial do texto selecionado, em relação ao texto completo.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 5,
      ]);

    $fields['end_position'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Posição final do texto'))
      ->setDescription(t('A posição final do texto selecionado, em relação ao texto completo.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 6,
      ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}
