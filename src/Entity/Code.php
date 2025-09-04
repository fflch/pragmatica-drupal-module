<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;

/**
 * Defines the Code entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_code",
 *   label = @Translation("Código"),
 *   label_plural = @Translation("Códigos"),
 *   base_table = "pragmatica_code",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\pragmatica\ListBuilder\PragmaticaBaseListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pragmatica\Form\CodeForm",
 *       "edit" = "Drupal\pragmatica\Form\CodeForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     }
 *   },
 *   links = {
 *    "canonical" = "/admin/pragmatica/code/{pragmatica_code}",
 *    "add-form" = "/admin/pragmatica/code/add",
 *    "edit-form" = "/admin/pragmatica/code/{pragmatica_code}/edit",
 *    "delete-form" = "/admin/pragmatica/code/{pragmatica_code}/delete",
 *    "collection" = "/admin/pragmatica/code",
 *   },
 *   admin_permission = "pragmatica",
 * )
 */
class Code extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id',
      'guid',
      'name',
      'parent_id',
      'is_codeble',
      'color',
      'description',
      'created',
      'creating_user_id',
      'changed',
      'modifying_user_id'
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    $mapping = [
      'is_codeble' => 'isCodeble',
      'color' => 'color',
    ];

    return parent::addFieldsToXmlMapping($mapping, self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['color'] = t('Cor');
    $header['parent_id'] = t('Código superior');
    $header['is_codeble'] = t('Pode ser usado?');
    return $this->addItemsAfterKeyInArray($header, $parent, 'name');
  }


  public function buildListRow(PragmaticaBaseEntity $entity): array {
    $row = parent::buildListRow($entity);
    $parent = $entity->get('parent_id')->entity;
    $row['parent_id'] = $parent ? $parent->label() : '';
    $row['color'] = $this->getColorHTML($entity->get('color')->value);
    $row['is_codeble'] = $entity->get('is_codeble')->value ? 'Sim' : 'Não';
    return $row;
  }

  private function getColorHTML($color = '') {
    return Markup::create('<div style="width: 20px; height: 20px; background-color: ' . htmlspecialchars($color) . '; border: 1px solid #ccc;"></div>');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['is_codeble'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Pode ser usado como código?'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => -3,
      ]);

    $fields['color'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cor'))
      ->setSetting('max_length', 7)
      ->setDescription(t('Cor associada ao código, no formato hexadecimal (ex: #FF5733).'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -2,
      ]);

    $fields['parent_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Código superior'))
      ->setDescription(t('Seleciona um código pai para este código.'))
      ->setSetting('target_type', 'pragmatica_code')
      ->setDisplayOptions('form', [
        'type' => 'options_select', // OU: entity_reference_autocomplete
        'weight' => -6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -6,
      ]);

      return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}
