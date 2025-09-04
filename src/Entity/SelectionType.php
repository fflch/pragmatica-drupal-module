<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the SelectionType content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_selection_type",
 *   label = @Translation("Tipo de fonte"),
 *   label_plural = @Translation("Tipos de fontes"),
 *   base_table = "pragmatica_selection_type",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
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
 *      "canonical" = "/admin/pragmatica/selection_type/{pragmatica_selection_type}",
 *      "add-form" = "/admin/pragmatica/selection_type/add",
 *      "edit-form" = "/admin/pragmatica/selection_type/{pragmatica_selection_type}/edit",
 *      "delete-form" = "/admin/pragmatica/selection_type/{pragmatica_selection_type}/delete",
 *      "collection" = "/admin/pragmatica/selection_type"
 *   },
 * )
 */
class SelectionType extends PragmaticaBaseEntity {
  public static function getFieldsIds(): array {
    return ['id', 'guid', 'name', 'description', 'created', 'changed'];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    return self::addBaseFieldDefinitions([], self::getFieldsIds());
  }
}

