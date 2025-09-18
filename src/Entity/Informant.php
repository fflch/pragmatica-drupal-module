<?php

namespace Drupal\pragmatica\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Informant content entity.
 *
 * @ContentEntityType(
 *   id = "pragmatica_informant",
 *   label = @Translation("Informante"),
 *   label_plural = @Translation("Informantes"),
 *   base_table = "pragmatica_informant",
 *   admin_permission = "pragmatica",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "code"
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
 *     "canonical" = "/admin/pragmatica/informant/{pragmatica_informant}",
 *     "add-form" = "/admin/pragmatica/informant/add",
 *     "edit-form" = "/admin/pragmatica/informant/{pragmatica_informant}/edit",
 *     "delete-form" = "/admin/pragmatica/informant/{pragmatica_informant}/delete",
 *     "collection" = "/admin/pragmatica/informant"
 *   }
 * )
 */
class Informant extends PragmaticaBaseEntity {

  public static function getFieldsIds(): array {
    return [
      'id',
      'code',
      'age',
      'gender_id',
      'language_id',
      'education_id',
      'profession_id',
      'city_birth_id',
      'city_residency_id',
      'created',
      'changed',
    ];
  }

  public static function getFieldsToXmlMapping(): array {
    $mapping = [
      'age' => [
        'xml' => [
          'idade', 
          'età'
        ],
      ],
      'gender_id' => [
        'entity_type' => 'pragmatica_gender',
        'xml' => [
          'gênero', 
          'genere'
        ]
      ],
      'language_id' => [
        'entity_type' => 'pragmatica_language',
        'xml' => [
          'língua materna', 
          'lingua materna'
        ]
      ],
      'education_id' => [
        'entity_type' => 'pragmatica_education',
        'xml' => [
          'escolaridade', 
          'titolo di studio'
        ]
      ],
      'profession_id' => [
        'entity_type' => 'pragmatica_profession',
        'xml' => [
          'profissão', 
          'professione'
        ]
      ],
      'city_birth_id' => [
        'entity_type' => 'pragmatica_city',
        'xml' => [
          'cidade natal', 
          'città di nascita'
        ]
      ],
      'city_residency_id' => [
        'entity_type' => 'pragmatica_city',
        'xml' => [
          'cidade de residência', 
          'città di residenza'
        ]
      ]
    ];

    return parent::addFieldsToXmlMapping($mapping, self::getFieldsIds());
  }

  public function getListHeaders(): array {
    $parent = parent::getListHeaders();
    $header['age'] = t('Idade');
    $header['gender_id'] = t('Gênero');
    $header['language_id'] = t('Língua materna');
    $header['education_id'] = t('Escolaridade');
    $header['profession_id'] = t('Profissão');
    $header['city_birth_id'] = t('Cidade natal');
    $header['city_residency_id'] = t('Cidade de residência');
    return $this->addItemsAfterKeyInArray($header, $parent, 'code');
  }

  public function buildListRow(PragmaticaBaseEntity $entity): array {
    /** @var self $entity */
    $row = parent::buildListRow($entity);
    $row['age'] = $entity->get('age')->value;
    $row['gender_id'] = $entity->get('gender_id')->entity ? $entity->get('gender_id')->entity->label() : '';
    $row['language_id'] = $entity->get('language_id')->entity ? $entity->get('language_id')->entity->label() : '';
    $row['education_id'] = $entity->get('education_id')->entity ? $entity->get('education_id')->entity->label() : '';
    $row['profession_id'] = $entity->get('profession_id')->entity ? $entity->get('profession_id')->entity->label() : '';
    $row['city_birth_id'] = $entity->get('city_birth_id')->entity ? $entity->get('city_birth_id')->entity->label() : '';
    $row['city_residency_id'] = $entity->get('city_residency_id')->entity ? $entity->get('city_residency_id')->entity->label() : '';
    return $row;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields['age'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Idade'))
      ->setSetting('min', 0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 5,
      ]);

    $fields['gender_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Gênero'))
      ->setSetting('target_type', 'pragmatica_gender')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 6,
      ]);

    $fields['language_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Língua materna'))
      ->setSetting('target_type', 'pragmatica_language')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 7,
      ]);

    $fields['education_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Escolaridade'))
      ->setSetting('target_type', 'pragmatica_education')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 8,
      ]);

    $fields['profession_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Profissão'))
      ->setSetting('target_type', 'pragmatica_profession')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 9,
      ]);

    $fields['city_birth_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Cidade natal'))
      ->setSetting('target_type', 'pragmatica_city')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 10,
      ]);

    $fields['city_residency_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Cidade de residência'))
      ->setSetting('target_type', 'pragmatica_city')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 11,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 11,
      ]);

    return self::addBaseFieldDefinitions($fields, self::getFieldsIds());
  }
}
