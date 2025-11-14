<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Language content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_language",
 *   label = @Translation("Língua"),
 *   label_plural = @Translation("Línguas"),
 *   base_table = "pragmatica_language",
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
 *     "canonical" = "/admin/pragmatica/language/{pragmatica_language}",
 *     "add-form" = "/admin/pragmatica/language/add",
 *     "edit-form" = "/admin/pragmatica/language/{pragmatica_language}/edit",
 *     "delete-form" = "/admin/pragmatica/language/{pragmatica_language}/delete",
 *     "collection" = "/admin/pragmatica/language"
 *   }
 * )
 */
class Language extends PragmaticaBaseEntity {

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
