<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Country content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_country",
 *   label = @Translation("País"),
 *   label_plural = @Translation("Países"),
 *   base_table = "pragmatica_country",
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
 *     "canonical" = "/admin/pragmatica/country/{pragmatica_country}",
 *     "add-form" = "/admin/pragmatica/country/add",
 *     "edit-form" = "/admin/pragmatica/country/{pragmatica_country}/edit",
 *     "delete-form" = "/admin/pragmatica/country/{pragmatica_country}/delete",
 *     "collection" = "/admin/pragmatica/country"
 *   }
 * )
 */
class Country extends PragmaticaBaseEntity {

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
