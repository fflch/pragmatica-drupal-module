<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\link\LinkItemInterface;

/**
 * Defines the Source content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_source",
 *   label = @Translation("Fonte"),
 *   label_plural = @Translation("Fontes"),
 *   base_table = "pragmatica_source",
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
 *     "canonical" = "/admin/pragmatica/source/{pragmatica_source}",
 *     "add-form" = "/admin/pragmatica/source/add",
 *     "edit-form" = "/admin/pragmatica/source/{pragmatica_source}/edit",
 *     "delete-form" = "/admin/pragmatica/source/{pragmatica_source}/delete",
 *     "collection" = "/admin/pragmatica/source"
 *   }
 * )
 */
class Source extends PragmaticaBaseEntity {

  use EntityChangedTrait;

  public static function getFieldsIds(): array {
    return [
      'id',
      'guid',
      'type_id',
      'name',
      'description',
      'plain_text',
      'rich_text_file',
      'media_file',
      'url',
      'created',
      'changed',
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    $mapping = [
      'plain_text' => 'plainTextPath',
      'rich_text_file' => 'richTextPath',
      'media_file' => 'path'
    ];

    return parent::addFieldsToXmlMapping($mapping, self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['type_id'] = t('Tipo');
    return $this->addItemsAfterKeyInArray($header, $parent, 'name');
  }

  public function buildListRow(PragmaticaBaseEntity $entity): array {
    $row = parent::buildListRow($entity);
    $row['type_id'] = $entity->get('type_id')->entity ? $entity->get('type_id')->entity->label() : '';
    return $row;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['type_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tipo'))
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setSetting('target_type', 'pragmatica_source_type')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -4,
      ]);

    $fields['plain_text'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Texto da fonte'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 3,
      ]);

    $fields['rich_text_file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Arquivo da fonte em formato rich text'))
      ->setDescription(t('Carregue um arquivo DOCX contendo o texto da fonte.'))
      ->setSetting('file_extensions', 'docx')
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 4,
      ]);

    $fields['media_file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Arquivo de mídia'))
      ->setDescription(t('Carregue um arquivo de mídia relacionado à fonte: áudio, vídeo, imagem ou documento.'))
      ->setSetting('file_extensions', 'mp3 mp4 pdf jpg jpeg png')
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 5,
      ]);

    $fields['url'] = BaseFieldDefinition::create('link')
      ->setLabel(t('URL'))
      ->setDescription(t('URL da fonte, se aplicável.'))
      ->setSetting('link_type', LinkItemInterface::LINK_EXTERNAL)
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'link_default',
        'weight' => 6,
      ]);

    return parent::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }

}

