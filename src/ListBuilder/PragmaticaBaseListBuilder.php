<?php

namespace Drupal\pragmatica\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\pragmatica\Entity\PragmaticaBaseEntity;
use ReflectionClass;

class PragmaticaBaseListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $original_class = $this->entityType->getOriginalClass();
    $original_entity = (new ReflectionClass($original_class))->newInstanceWithoutConstructor();

    if (!$original_entity instanceof PragmaticaBaseEntity) {
      return parent::buildHeader();
    }

    $fields_ids = $original_entity::getFieldsIds();
    $headers = $original_entity->getListHeaders();

    foreach ($headers as $key => $header) {
      if (!in_array($key, $fields_ids)) {
        unset($headers[$key]);
      }
    }

    return $headers + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $default = parent::buildRow($entity);
    if (!$entity instanceof PragmaticaBaseEntity) {
      return $default;
    }

    $row = $entity->buildListRow($entity);
    $header = $this->buildHeader();
    $ordered_row = [];

    foreach ($header as $key => $value) {
      if (isset($row[$key])) {
        $ordered_row[$key] = $row[$key];
      }
    }

    $ordered_row['operations'] = $default['operations'];
    return $ordered_row;
  }
}
