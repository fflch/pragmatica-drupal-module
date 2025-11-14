<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Profession content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_profession",
 *   label = @Translation("Profissão"),
 *   label_plural = @Translation("Profissões"),
 *   base_table = "pragmatica_profession",
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
 *     "canonical" = "/admin/pragmatica/profession/{pragmatica_profession}",
 *     "add-form" = "/admin/pragmatica/profession/add",
 *     "edit-form" = "/admin/pragmatica/profession/{pragmatica_profession}/edit",
 *     "delete-form" = "/admin/pragmatica/profession/{pragmatica_profession}/delete",
 *     "collection" = "/admin/pragmatica/profession"
 *   }
 * )
 */
class Profession extends PragmaticaBaseEntity {

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
