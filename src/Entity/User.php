<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the User content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_user",
 *   label = @Translation("Usuário"),
 *   label_plural = @Translation("Usuários"),
 *   base_table = "pragmatica_user",
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
 *     "canonical" = "/admin/pragmatica/user/{pragmatica_user}",
 *     "add-form" = "/admin/pragmatica/user/add",
 *     "edit-form" = "/admin/pragmatica/user/{pragmatica_user}/edit",
 *     "delete-form" = "/admin/pragmatica/user/{pragmatica_user}/delete",
 *     "collection" = "/admin/pragmatica/user"
 *   }
 * )
 */
class User extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return ['id', 'guid', 'name', 'created', 'changed'];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    return self::addBaseFieldDefinitions([], self::getFieldsIds());
  }

}
