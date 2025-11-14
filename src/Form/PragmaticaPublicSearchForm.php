<?php
namespace Drupal\pragmatica\Form;


use Drupal;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


class PragmaticaPublicSearchForm extends FormBase {
  // TODO: Create method to get if any filter has been applied
  
  protected array $form_values = [];
  protected array $field_config = [];

  public function getFormId() {
    return 'pragmatica_public_search_form';
  }

  public function setFormValues(array $form_values) {
    $this->form_values = $form_values;
    return $this;
  }

  public function getFieldConfig() {
    return $this->field_config;
  }


  public function buildForm(array $form, FormStateInterface $form_state) {
    foreach ($this->setFieldsConfiguration() as $key => $field) {
      $name = $field['name'];
      $form[$name] = [
        '#name' => $name,
        '#type' => $field['type'],
        '#title' => $field['label'],
        '#options' => $field['options'],
        '#multiple' => $field['multiple'],
        '#value' => $field['value'] ?? null
      ];

      if ($field['type'] === 'number') {
        $form[$name]['#min'] = $field['min'] ?? 0;
        $form[$name]['#max'] = $field['max'] ?? 100;
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Buscar',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    return;
  }


  public function getParentFieldsConfiguration() {

    $config = [];

    $config['label'] = [
      'title' => 'Etiquetas',
    ];

    $config['informant'] = [
      'title' => 'Informante',
      'entity' => 'informant_id.entity',
    ];

    return $config;
  }

  private function setFieldsConfiguration($load_options = true) {
    // TODO: Implement load_options() param.
    $config = [];

    $config['situation_id'] = $this->addField('situation_id', 'Situação',  $this->getEntityOptions('situation'), true);

    $label_types = $this->getEntityOptions('label_type');

    foreach ($label_types as $label_type_id => $label_type_name) {
      $name = 'label_type_id_' . $label_type_id;
      $config[$name] = $this->addField(
        $name, 
        $label_type_name, 
        $this->getEntityOptions('label', ['type_id' => $label_type_id]),
        true,
        'label'
      );
    }

    $config['language_id'] = $this->addField('language_id', 'Idioma', $this->getEntityOptions('language'), true, 'informant');
    $config['city_residency_id'] = $this->addField('city_residency_id', 'Cidade', $this->getEntityOptions('city'), true, 'informant');
    $config['education_id'] = $this->addField('education_id', 'Escolaridade', $this->getEntityOptions('education'), true, 'informant');
    $config['gender_id'] = $this->addField('gender_id', 'Gênero', $this->getEntityOptions('gender'), true, 'informant');
    $config['profession_id'] = $this->addField('profession_id', 'Profissão', $this->getEntityOptions('profession'), true, 'informant');
    $config['min_age'] = $this->addNumberField('min_age', 'Idade mínima', 'informant', 'age', 0, 100, ['operator' => '>=']);
    $config['max_age'] = $this->addNumberField('max_age', 'Idade máxima', 'informant', 'age', 0, 100, ['operator' => '<=']);

    if (!empty($this->form_values)) {
      $config = $this->setValuesFromSubmittedForm($config, $this->form_values);
    }

    $this->field_config = $config;
    return $config;
  }

  private function setValuesFromSubmittedForm($config = [], $form_values = []) {
    foreach ($config as $key => $field) {
      $name = str_replace('[]', '', $field['name']);
      if (isset($form_values[$name])) {
        $config[$key]['value'] = $form_values[$name];
      }
    }

    return $config;
  }

  public function buildSearchQuery(QueryInterface $query) {
    $response_ids_by_label = [];
    $label_filter_applied = false;

    $config = $this->setFieldsConfiguration();

    $max_age = $config['max_age'];
    $min_age = $config['min_age'];

    if (!empty($max_age['value']) && !empty($min_age['value'])) {
      $this->applyGenericCondition($query, [
        'name' => 'age',
        'type' => 'number',
        'operator' => 'BETWEEN',
        'value' => [$min_age['value'], $max_age['value']],
        'parent' => 'informant'
      ]);
      unset($config['max_age']);
      unset($config['min_age']);
    }

    foreach ($config as $field_key => $field) {
      if (isset($field['parent']) && $field['parent'] === 'label') {
        if (empty($field['value'])) {
          continue;
        }

        $label_filter_applied = true;
        $response_ids_by_label[] = $this->getLabelResponseIds($field);
        continue;
      }

      $this->applyGenericCondition($query, $field);
    }

    if ($label_filter_applied && !empty($response_ids_by_label)) {
      $intersected_ids = array_shift($response_ids_by_label);
      foreach ($response_ids_by_label as $ids) {
        $intersected_ids = array_intersect($intersected_ids, $ids);
      }
      if (empty($intersected_ids)) {
        $intersected_ids = [0];
      }

      $query->condition('id', $intersected_ids, 'IN');
    }

    return $query;
  }

  private function applyGenericCondition(QueryInterface $query, $field) {
    $field_property = $field['entity_property'] ?? $field['name'];
    $parent = $field['parent'] ?? '';
    $value = $field['value'] ?? '';
    $operator = $field['operator'] ?? '';

    if (empty($field_property) || empty($value)) {
      return $query;
    }

    if (!empty($parent)) {
      $parents = $this->getParentFieldsConfiguration();
      if (isset($parents[$parent]['entity'])) {
        $field_property = $parents[$parent]['entity'] . '.' . $field_property;
      }
    }

    if (is_array($value)) {
      $value = array_map('intval', $value);
      $value = array_filter($value);
      $value = array_unique($value);
    }
    
    if (!empty($operator)) {
      $query->condition($field_property, $value, $operator);
    }
    elseif (!empty($field['multiple']) && is_array($value)) {
      $query->condition($field_property, $value, 'IN');
    } 
    else {
      $query->condition($field_property, $value);
    }

    return $query;
  }

  private function getLabelResponseIds($field) {
    $label_ids = $field['value'];
    if (empty($label_ids)) {
        return [];
    }

    $label_ids = array_map('intval', $label_ids);
    $label_ids = array_filter($label_ids);
    $label_ids = array_unique($label_ids);

    $connection = Drupal::database();
    $selection_ids = $connection->select('pragmatica_selection', 's')
                  ->fields('s', ['response_id'])
                  ->condition('label_id',  $label_ids, 'IN')
                  ->distinct()
                  ->execute()
                  ->fetchCol();
      
    return $selection_ids;
  }

  private function addField(
    string $name,
    string $label, 
    array $options = [],
    bool $multiple = false,
    string $parent = '',
    string $entity_property = '',
    string $type = 'select',
    array $extra = []
  ) {
    return [
      'name' => $this->setFieldName($name, $parent, $multiple),
      'type' => $type,
      'label' => $label,
      'multiple' => $multiple,
      'parent' => $parent,
      'options' => $options,
      'entity_property' => empty($entity_property) ? $name : $entity_property,
      'value' => $multiple ? [] : ''
    ] + $extra;
  }

  private function addNumberField(
    string $name,
    string $label, 
    string $parent = '',
    string $entity_property = '',
    int $min = 0,
    int $max = 100,
    array $extra = []
  ) {

    return $this->addField($name, $label, [], false, $parent, $entity_property, 'number', ['min' => $min, 'max' => $max]) + $extra;
  }

  private function setFieldName($name, $parent = '', $multiple = false) {
    $name = empty($parent) ? $name : $parent . '_' . $name;
    return $multiple ? $name . '[]' : $name;
  }



  public function getPrefixedEntityTypeId(string $entity_type_id) {
    return str_starts_with($entity_type_id, 'pragmatica_') ? $entity_type_id : 'pragmatica_' . $entity_type_id;
  }


  public function getEntityOptions(string $entity_type_id, $properties_filter = []) {
    $entity_type_id = $this->getPrefixedEntityTypeId($entity_type_id);
    $entity_storage = Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entities = $entity_storage->loadByProperties($properties_filter);
    $options = [];
    foreach ($entities as $entity) {
      $label = $entity->label();
      $name = $entity->hasField('name') ? $entity->get('name')->value : '';

      if (!empty($name) && $name != $label) {
          $label .= ' - ' . $name;
      }

      $options[$entity->id()] = $label;
    }
    return $options;
  }

}
