<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\Markup;

/**
 * Defines the Label entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_label",
 *   label = @Translation("Etiqueta"),
 *   label_plural = @Translation("Etiquetas"),
 *   base_table = "pragmatica_label",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\pragmatica\ListBuilder\PragmaticaBaseListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pragmatica\Form\LabelForm",
 *       "edit" = "Drupal\pragmatica\Form\LabelForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     }
 *   },
 *   links = {
 *    "canonical" = "/admin/pragmatica/label/{pragmatica_label}",
 *    "add-form" = "/admin/pragmatica/label/add",
 *    "edit-form" = "/admin/pragmatica/label/{pragmatica_label}/edit",
 *    "delete-form" = "/admin/pragmatica/label/{pragmatica_label}/delete",
 *    "collection" = "/admin/pragmatica/label",
 *   },
 *   admin_permission = "pragmatica",
 * )
 */
class Label extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id',
      'type_id',
      'code',
      'name',
      'color',
      'description',
      'examples',
      'created',
      'changed',
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    $mapping = [
      'color' => 'color',
    ];

    return parent::addFieldsToXmlMapping($mapping, self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['type_id'] = t('Tipo');
    $ordered_headers = $this->addItemsAfterKeyInArray($header, $parent, 'id');
    $header = [];
    $header['color'] = t('Cor');
    return $this->addItemsAfterKeyInArray($header, $ordered_headers, 'name');
  }


  public function buildListRow(PragmaticaBaseEntity $entity): array {
    $row = parent::buildListRow($entity);
    $row['type_id'] = $entity->get('type_id')->entity ? $entity->get('type_id')->entity->label() : '';
    $row['color'] = $this->getColorHTML($entity->get('color')->value);
    return $row;
  }

  private function getColorHTML($color = '') {
    return Markup::create('<div style="width: 20px; height: 20px; background-color: ' . htmlspecialchars($color) . '; border: 1px solid #ccc;"></div>');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['type_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tipo'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'pragmatica_label_type')
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

    $fields['examples'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Exemplos'))
      ->setDescription(t('Exemplos de uso desta etiqueta.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 2,
      ]);

      return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
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

    $display['code'] = $base_entity->get('code')->value;

    if (empty($display['label'])) {
      $display['label'] = $display['code'];
    }

    $display['color'] = $base_entity->get('color')->value;
    return $display;
  }


  public static function getIgnoreFieldsForLabelValueDisplay(): array {
    return array_merge(parent::getIgnoreFieldsForLabelValueDisplay(), ['color']);
  }
}
