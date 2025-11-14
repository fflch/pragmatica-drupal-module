<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Education content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_education",
 *   label = @Translation("Escolaridade"),
 *   label_plural = @Translation("Escolaridades"),
 *   base_table = "pragmatica_education",
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
 *     "canonical" = "/admin/pragmatica/education/{pragmatica_education}",
 *     "add-form" = "/admin/pragmatica/education/add",
 *     "edit-form" = "/admin/pragmatica/education/{pragmatica_education}/edit",
 *     "delete-form" = "/admin/pragmatica/education/{pragmatica_education}/delete",
 *     "collection" = "/admin/pragmatica/education"
 *   }
 * )
 */
class Education extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return ['id', 'name', 'created', 'changed'];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    return self::addBaseFieldDefinitions([], self::getFieldsIds());
  }

}
