<?php

namespace Drupal\pragmatica\Entity;

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Selection entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_selection",
 *   label = @Translation("Seleção"),
 *   label_plural = @Translation("Seleções"),
 *   base_table = "pragmatica_selection",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
 *   },
 *   handlers = {
 *   "list_builder" = "Drupal\pragmatica\ListBuilder\PragmaticaBaseListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pragmatica\Form\SelectionForm",
 *       "edit" = "Drupal\pragmatica\Form\SelectionForm",
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
      'guid',
      'type_id',
      'name',
      'description',
      'source_id',
      'start_position',
      'end_position',
      'begin',
      'end',
      'from_sync_point',
      'to_sync_point',
      'created',
      'creating_user_id',
      'changed',
      'modifying_user_id'
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    $mapping = [
      'start_position' => 'startPosition',
      'end_position' => 'endPosition',
      'begin' => 'begin',
      'end' => 'end'
    ];

    return parent::addFieldsToXmlMapping($mapping, self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['type_id'] = t('Tipo');
    $header['source_id'] = t('Fonte');
    return $this->addItemsAfterKeyInArray($header, $parent, 'name');
  }


  public function buildListRow(PragmaticaBaseEntity $entity): array {
    $row = parent::buildListRow($entity);
    $row['type_id'] = $entity->get('type_id')->entity ? $entity->get('type_id')->entity->label() : '';
    $row['source_id'] = $entity->get('source_id')->entity ? $entity->get('source_id')->entity->label() : '';
    return $row;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['type_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tipo'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'pragmatica_selection_type')
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ]);

    $fields['source_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Fonte'))
      ->setSetting('target_type', 'pragmatica_source')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 4,
      ]);

    $fields['start_position'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Posição inicial do texto'))
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

    $fields['begin'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Início do áudio'))
      ->setDescription(t('O início do áudio selecionado, em milissegundos.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'step' => 'any',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 7,
      ]);

    $fields['end'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Fim do áudio'))
      ->setDescription(t('O fim do áudio selecionado, em milissegundos.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 8,
      ]);

    // Transform sync points in entity references to a SyncPoint entity.
    $fields['from_sync_point'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Ponto de sincronização inicial'))
      ->setSettings(['max_length' => 36])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 9,
      ]);

    $fields['to_sync_point'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Ponto de sincronização final'))
      ->setSettings(['max_length' => 36])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 9,
      ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}
