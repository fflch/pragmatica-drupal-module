<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Situation content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_situation",
 *   label = @Translation("Situação"),
 *   label_plural = @Translation("Situações"),
 *   base_table = "pragmatica_situation",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "code"
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
 *     "canonical" = "/admin/pragmatica/situation/{pragmatica_situation}",
 *     "add-form" = "/admin/pragmatica/situation/add",
 *     "edit-form" = "/admin/pragmatica/situation/{pragmatica_situation}/edit",
 *     "delete-form" = "/admin/pragmatica/situation/{pragmatica_situation}/delete",
 *     "collection" = "/admin/pragmatica/situation"
 *   }
 * )
 */
class Situation extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id',
      'code',
      'name',
      'short_name',
      'created',
      'changed'
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields['name'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Nome'))
      ->setDescription(t('Situação apresentada na pesquisa.'))
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

    $fields['short_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nome abreviado'))
      ->setDescription(t('Situação resumida para facilitar exibição em listas.'))
      ->setSettings(['max_length' => 128])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 1,
      ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}
