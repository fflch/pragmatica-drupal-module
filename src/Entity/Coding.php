<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the SelectionCode content entity.
 * Relationship many-to-many between Selection and Code entities.
 *
 * @ContentEntityType(
 *   id = "pragmatica_coding",
 *   label = @Translation("Codificação"),
 *   label_plural = @Translation("Codificações"),
 *   base_table = "pragmatica_coding",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   handlers = {
 *    "list_builder" = "Drupal\pragmatica\ListBuilder\PragmaticaBaseListBuilder",
 *    "form" = {
 *      "add" = "Drupal\pragmatica\Form\PragmaticaBaseForm",
 *      "edit" = "Drupal\pragmatica\Form\PragmaticaBaseForm",
 *      "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *    }
 *   },
 *   links = {
 *      "canonical" = "/admin/pragmatica/coding/{pragmatica_coding}",
 *      "add-form" = "/admin/pragmatica/coding/add",
 *      "edit-form" = "/admin/pragmatica/coding/{pragmatica_coding}/edit",
 *      "delete-form" = "/admin/pragmatica/coding/{pragmatica_coding}/delete",
 *      "collection" = "/admin/pragmatica/coding"
 *   },
 * )
 */
class Coding extends PragmaticaBaseEntity {
  public static function getFieldsIds(): array {
    return [
      'id',
      'guid',
      'selection_id',
      'code_id',
      'created',
      'creating_user_id',
      'changed',
      'modifying_user_id'
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['selection_id'] = t('Seleção');
    $header['code_id'] = t('Código');
    return $this->addItemsAfterKeyInArray($header, $parent, 'id');
  }

  public function buildListRow(PragmaticaBaseEntity $entity): array {
    /** @var self $entity */
    $row = parent::buildListRow($entity);
    $row['selection_id'] = $entity->getSelectionLabel();
    $row['code_id'] = $entity->getCodeLabel();
    return $row;
  }

  public function getSelectionLabel(): string {
    if ($this->get('selection_id')->entity) {
      return $this->get('selection_id')->entity->label();
    }
    return '';
  }

  public function getCodeLabel(): string {
    if ($this->get('code_id')->entity) {
      return $this->get('code_id')->entity->label();
    }
    return '';
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields['selection_id'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Seleção'))
        ->setDescription(t('Seleção associada'))
        ->setSetting('target_type', 'pragmatica_selection')
        ->setRequired(TRUE)
        ->setDisplayOptions('form', [
          'type' => 'entity_reference_autocomplete',
          'weight' => 0,
        ])
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'entity_reference_label',
          'weight' => 0,
        ]);

    $fields['code_id'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Código'))
        ->setDescription(t('Código associado'))
        ->setSetting('target_type', 'pragmatica_code')
        ->setRequired(TRUE)
        ->setDisplayOptions('form', [
          'type' => 'entity_reference_autocomplete',
          'weight' => 1,
        ])
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'entity_reference_label',
          'weight' => 1,
        ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}

