<?php

namespace Drupal\pragmatica\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Exception;

/**
 * Defines common fields and methods for Pragmatica entities.
 * - `id`: unique identifier for the element, an auto-incremented integer.
 * - `guid`: globally unique identifier (GUID) in the format XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX.
 * - `name`: name of the element (or title).
 * - `description`: description of the element.
 * - `creating_user`: user who created the element.
 * - `modifying_user`: user who last modified the element.
 * - `created`: date and time of creation of the element in unix format.
 * - `changed`: date and time of last modification of the element in unix format.
 */
abstract class PragmaticaBaseEntity extends ContentEntityBase {
  use EntityChangedTrait;

  /**
   * Returns the ordered list of field IDs used in the entity.
   */
  public abstract static function getFieldsIds(): array;

  public abstract static function getFieldsToXmlMapping(): array;

  public static function addFieldsToXmlMapping(
    $instanceMapping = [],
    $instanceFieldsIds = []
  ): array {
    $mapping = [
      'guid' => 'guid',
      'name' => 'name',
      'description' => 'Description',
      'creating_user_id' => 'creatingUser',
      'modifying_user_id' => 'modifyingUser',
      'created' => 'creationDateTime',
      'changed' => 'modifiedDateTime',
    ];

    $mapping = array_merge($mapping, $instanceMapping);

    foreach ($mapping as $field => $xml_key) {
      if (!in_array($field, $instanceFieldsIds)) {
        unset($mapping[$field]);
      }
    }

    return $mapping;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    return array_merge($fields, self::getBaseFieldDefinitions());
  }

  static function addBaseFieldDefinitions(
    $fields,
    $fields_ids
  ): array {
    $base_fields = self::getBaseFieldDefinitions();
    foreach ($base_fields as $field_id => $field_definition) {
      if (in_array($field_id, $fields_ids)) {
        $fields[$field_id] = $field_definition;
      }
    }

    self::reorderFields($fields, $fields_ids);
    return $fields;
  }

  /**
   * Reorders the fields based on the provided order.
   * @param array $fields An associative array of field definitions.
   * @param array $order An array of field IDs in the desired order.
   * @param array $display_context An array of display contexts (e.g., 'view', 'form').
   * @param bool $remove_fields_not_in_order Whether to remove fields not in the order array.
   *
   * @return array The reordered fields with weights set according to the order.
   * @throws Exception
   */
  public static function reorderFields(
    array $fields,
    array $order,
    array $display_context = ['view', 'form'],
    bool $remove_fields_not_in_order = true
  ): array {
    $reordered_fields = [];
    $order = array_values(array_filter($order));
    $lastIndex = count($order) - 1;

    foreach ($fields as $field_id => $field) {
      if (in_array($field_id, $order)) {
        $fieldIndex = array_search($field_id, $order);
        $reordered_fields[$fieldIndex] = $field;
      } elseif ($remove_fields_not_in_order) {
      //  unset($fields[$field_id]);
      } else {
        $lastIndex++;
        $reordered_fields[$lastIndex] = $field;
        $order[$lastIndex] = $field_id;
      }
    }

    foreach ($display_context as $context) {
      foreach ($reordered_fields as $weight => $field) {
        $display_options = $field->getDisplayOptions($context);
        if (!$display_options) { continue; }
        $display_options['weight'] = $weight;
        $field->setDisplayOptions($context, $display_options);
      }
    }

    if (count($fields) != count($order)) {
      $missing_fields = array_diff($order, array_keys($fields));
      if (!empty($missing_fields)) {
        throw new Exception(
            'The following fields are missing from the entity: ' .
            implode(', ', $missing_fields) . "\n" .
            'Order fields: ' . implode(', ', $order) . "\n" .
            'Fields: ' . implode(', ', array_keys($fields)) . "\n"
        );
      }
    }

    return array_combine($order, $reordered_fields);
  }

  public function addItemsAfterKeyInArray(
    array $item,
    array $target_array,
    string $after_key = ''
  ): array {
    $ordered_item = [];

    foreach ($target_array as $key => $value) {
      $ordered_item[$key] = $value;
      if ($key === $after_key) {
        foreach ($item as $item_key => $item_value) {
          if (!isset($ordered_item[$item_key])) {
            $ordered_item[$item_key] = $item_value;
          }
        }
      }
    }

    if ($after_key && !array_key_exists($after_key, $ordered_item)) {
      foreach ($item as $item_key => $item_value) {
        if (!isset($ordered_item[$item_key])) {
          $ordered_item[$item_key] = $item_value;
        }
      }
    }

    return $ordered_item;
  }

  public function getListHeaders(): array {
    return [
      'id' => t('ID'),
      'name' => t('Nome'),
      'changed' => t('Modificado em'),
    ];
  }

  public function buildListRow(PragmaticaBaseEntity $entity): array {
    return [
      'id' => $entity->id(),
      'name' => $entity->hasField('name') ? $entity->get('name')->value : '',
      'changed' => $entity->getDisplayDateTimeFormatted('changed', $entity),
    ];
  }

  function getDisplayDateTimeFormatted($field_name, PragmaticaBaseEntity $entity): string {
    if (!$entity->hasField($field_name)) {
      return '';
    }

    $datetime = $entity->get($field_name)->value;

    if ($datetime) {
      return Drupal::service('date.formatter')->format($datetime, 'short');
    }

    return '';
  }

  public function getDisplayUser(string $field_name, PragmaticaBaseEntity $entity) {
    if (!$entity->hasField($field_name)) {
      return '';
    }

    $user = $entity->get($field_name)->entity;
    if ($user) {
      return $user->label();
    }
    return '';
  }

  public static function getBaseFieldDefinitions() {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel('ID')
      ->setDescription("Identificador")
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['guid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('GUID'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 36)
      ->setDescription(t('Código único global (GUID) de identificação.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nome'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 1,
      ]);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Descrição'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 2,
      ]);

    $fields['creating_user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Criado por'))
      ->setSetting('target_type', 'pragmatica_user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ]);

    $fields['modifying_user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Alterado por'))
      ->setSetting('target_type', 'pragmatica_user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 4,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Criado em'))
      ->setDescription(t('Data e hora da criação'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 5,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Modificado em'))
      ->setDescription(t('Data e hora da última modificação'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 6,
      ]);

    return $fields;
  }
}

