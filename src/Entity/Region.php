<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Region content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_region",
 *   label = @Translation("Região"),
 *   label_plural = @Translation("Regiões"),
 *   base_table = "pragmatica_region",
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
 *     "canonical" = "/admin/pragmatica/region/{pragmatica_region}",
 *     "add-form" = "/admin/pragmatica/region/add",
 *     "edit-form" = "/admin/pragmatica/region/{pragmatica_region}/edit",
 *     "delete-form" = "/admin/pragmatica/region/{pragmatica_region}/delete",
 *     "collection" = "/admin/pragmatica/region"
 *   }
 * )
 */
class Region extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id', 
      'name',
      'country_id',
      'created',
      'changed'
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['country_id'] = t('País');
    return $this->addItemsAfterKeyInArray($header, $parent, 'id');
  }

  public function getCountryLabel(): string {
    if ($this->get('country_id')->entity) {
      return $this->get('country_id')->entity->label();
    }
    return '';
  }

  public function buildListRow(PragmaticaBaseEntity $entity): array {
    /** @var self $entity */
    $row = parent::buildListRow($entity);
    $row['country_id'] = $entity->getCountryLabel();
    return $row;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
      $fields['country_id'] = BaseFieldDefinition::create('entity_reference')
          ->setLabel(t('País'))
          ->setSetting('target_type', 'pragmatica_country')
          ->setRequired(FALSE)
          ->setDisplayOptions('form', [
            'type' => 'entity_reference_autocomplete',
            'weight' => 0,
          ])
          ->setDisplayOptions('view', [
            'label' => 'above',
            'type' => 'entity_reference_label',
            'weight' => 0,
          ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}
