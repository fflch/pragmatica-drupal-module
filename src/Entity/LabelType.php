<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the LabelType content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_label_type",
 *   label = @Translation("Tipo de etiqueta"),
 *   label_plural = @Translation("Tipos de etiquetas"),
 *   base_table = "pragmatica_label_type",
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
 *      "canonical" = "/admin/pragmatica/label_type/{pragmatica_label_type}",
 *      "add-form" = "/admin/pragmatica/label_type/add",
 *      "edit-form" = "/admin/pragmatica/label_type/{pragmatica_label_type}/edit",
 *      "delete-form" = "/admin/pragmatica/label_type/{pragmatica_label_type}/delete",
 *      "collection" = "/admin/pragmatica/label_type"
 *   },
 * )
 */
class LabelType extends PragmaticaBaseEntity {
  public static function getFieldsIds(): array {
    return ['id', 'name', 'description', 'created', 'changed'];
  }

  public static function getFieldsToXmlMapping(): array {
    return parent::addFieldsToXmlMapping([], self::getFieldsIds());
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    return self::addBaseFieldDefinitions([], self::getFieldsIds());
  }
}

