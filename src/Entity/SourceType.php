<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the SourceType content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_source_type",
 *   label = @Translation("Tipo de fonte"),
 *   label_plural = @Translation("Tipos de fontes"),
 *   base_table = "pragmatica_source_type",
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
 *      "canonical" = "/admin/pragmatica/source_type/{pragmatica_source_type}",
 *      "add-form" = "/admin/pragmatica/source_type/add",
 *      "edit-form" = "/admin/pragmatica/source_type/{pragmatica_source_type}/edit",
 *      "delete-form" = "/admin/pragmatica/source_type/{pragmatica_source_type}/delete",
 *      "collection" = "/admin/pragmatica/source_type"
 *   },
 * )
 */
class SourceType extends PragmaticaBaseEntity {

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

