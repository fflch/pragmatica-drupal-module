<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Gender content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_gender",
 *   label = @Translation("Gênero"),
 *   label_plural = @Translation("Gêneros"),
 *   base_table = "pragmatica_gender",
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
 *     "canonical" = "/admin/pragmatica/gender/{pragmatica_gender}",
 *     "add-form" = "/admin/pragmatica/gender/add",
 *     "edit-form" = "/admin/pragmatica/gender/{pragmatica_gender}/edit",
 *     "delete-form" = "/admin/pragmatica/gender/{pragmatica_gender}/delete",
 *     "collection" = "/admin/pragmatica/gender"
 *   }
 * )
 */
class Gender extends PragmaticaBaseEntity {

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
