<?php
namespace Drupal\pragmatica\Importer;

use Drupal;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\pragmatica\Entity\PragmaticaBaseEntity;
use Exception;
use ReflectionClass;
use SimpleXMLElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class QDEImporter {

  protected $xml_file_path;
  protected $sources_folder_path;
  protected $entity_manager;
  protected $logger;
  protected $save_rich_text_files = false;
  protected $pragmatica_prefix = 'pragmatica_';
  protected $entities_unique_property_id_mapping = [];
  protected $guid_key = 'guid';
  protected $code_key = 'code';
  protected $name_key = 'name';


  public function __construct(
    string $xml_file_path,
    string $sources_folder_path,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    bool $save_rich_text_files = false
  ) {
    $this->xml_file_path = $xml_file_path;
    $this->sources_folder_path = $sources_folder_path;
    $this->entity_manager = $entity_type_manager;
    $this->logger = $logger_factory->get($this->pragmatica_prefix . 'import');
    $this->save_rich_text_files = $save_rich_text_files;

  }

  /**
   * Import a REFI-QDA project XML file and its sources.
   * @throws Exception
   */
  public function import() {
    if (!file_exists($this->xml_file_path)) {
      $this->logger->error('REFI-QDA XML file not found: @path', ['@path' => $this->xml_file_path]);
      throw new Exception('REFI-QDA XML file not found.');
    }

    try {
      $xml = simplexml_load_file($this->xml_file_path);
      if ($xml === false) {
        throw new Exception('Failed to parse REFI-QDA XML file.');
      }

      $this->logger->info('Importing REFI-QDA project from @path', ['@path' => $this->xml_file_path]);
      $project_info = $xml->attributes();
      $project_log_message = 'Project Info: ';
      foreach ($project_info as $key => $value) {
        $project_log_message .= "$key: $value; ";
      }
      $this->logger->info($project_log_message);

      $this->importProjectElements($xml);
      // @todo: import project metadata

    }
    catch (Exception $e) {
      $this->logger->error('Failed to import REFI-QDA project: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Import a project from the root XML element.
   *
   * @throws \Exception
   */
  protected function importProjectElements(SimpleXMLElement $xml) {
    if (isset($xml->Users)) {
      $this->importUsers($xml->Users);
    }

    if (isset($xml->CodeBook)) {
      $this->importCodes($xml->CodeBook);
    }

    if (isset($xml->Sources)) {
      $this->importSources($xml->Sources);
    }
  }

  /**
   * Import Users.
   */
  protected function importUsers(SimpleXMLElement $users_xml) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'user');
    foreach ($users_xml->User as $userXml) {
        $this->saveXMLEntity($userXml, $storage);
    }
  }

  /**
   * Import Codes.
   *
   * @throws \Exception
   */
  protected function importCodes(SimpleXMLElement $codesXml) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'label');
    foreach ($codesXml->Codes as $codesXml) {
      foreach ($codesXml->Code as $codeXml) {
        $this->importCode($codeXml, $storage);
      }
    }
  }

  /**
   * @throws \Exception
   */
  protected function importCode(
    SimpleXMLElement $codeXml,
    EntityStorageInterface $storage,
    $parent_code_id = NULL
  ) {

    $codeXml->addAttribute($this->code_key, (string)$codeXml[$this->name_key]);
    $saved_code = $this->saveXMLEntity($codeXml, $storage, [], $this->code_key);
    $saved_code_id = $saved_code->id();

    if (isset($codeXml->Code)) {
      foreach ($codeXml->Code as $childCodeXml) {
        $this->importCode($childCodeXml, $storage, $saved_code_id);
      }
    }
  }

  /**
   * Import Sources.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function importSources(SimpleXMLElement $sourcesXml) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'source');

    $source_type_mapping = [
      'TextSource' => 'Texto',
      'DocumentSource' => 'Documento (PDF)',
      'AudioSource' => 'Áudio',
      'VideoSource' => 'Vídeo',
      'PictureSource' => 'Imagem',
    ];

    $source_type_mapping_ids = $this->getIdFromProperty(
      $this->name_key,
      $source_type_mapping,
      $this->pragmatica_prefix . 'source_type'
    );

    foreach ($source_type_mapping_ids as $source_type => $source_type_id) {
      if (isset($sourcesXml->$source_type)) {
        foreach ($sourcesXml->$source_type as $sourceXml) {

          $info_from_source_files = $this->getSourceFilesInfo(
            $sourceXml,
            $storage
          );

          if (empty($info_from_source_files)) {
            $this->logger->warning('No valid source files found for source with GUID @guid. Skipping import.', [
              '@guid' => (string) $sourceXml['guid'],
            ]);
            continue;
          }

          $extra_info = array_merge($info_from_source_files,
            ['type_id' => $source_type_id]
          );

          $saved_source = $this->saveXMLEntity($sourceXml, $storage, $extra_info);

          $source_id = $saved_source->id();
          $this->parseAndImportSource($sourceXml, $source_id, $info_from_source_files);
        }
      }
    }
  }


  /**
   * Parse and import a Source.
   *
   * @param  SimpleXMLElement  $sourceXml
   * @param  int  $source_id
   * @param  array  $info_from_source_files
   *
   */
  protected function parseAndImportSource(
    SimpleXMLElement $sourceXml,
    $source_id,
    array $info_from_source_files
  ) {

    if (empty($info_from_source_files['plain_text'])) {
      $this->logger->warning('No plain text content available for source ID @id. Skipping selections import.', ['@id' => $source_id]);
      return;
    }

    $source_parsed = $this->parseSourcePlainText($info_from_source_files['plain_text']);
    $source_name = (string) $sourceXml[$this->name_key] ?? null;
    if (empty($source_name)) {
      $this->logger->warning('Source name is empty for source ID @id.', ['@id' => $source_id]);
    }

    $parsed_source_name = $this->parseSourceName($source_name);
    $prefix = $parsed_source_name['prefix'] ?? '';
    $initial_informant_number = $parsed_source_name['initial_informant_number'] ?? 0;
    $final_informant_number = $parsed_source_name['final_informant_number'] ?? 0;
    
    foreach ($source_parsed as $contribution) {
      if (!empty($contribution['error'])) {
        $this->logger->error('Error parsing contribution in source @name: @error', [
          '@name' => $source_name ?? $source_id,
          '@error' => $contribution['error'],
        ]);
        continue;
      }

      $informant_number = $contribution['informant_number'] ?? null;

      if ($informant_number < $initial_informant_number || $informant_number > $final_informant_number) {
        $this->logger->warning('Informant number @num is out of range (@initial - @final) for source @name. Skipping contribution.', [
          '@num' => $informant_number,
          '@initial' => $initial_informant_number,
          '@final' => $final_informant_number,
          '@name' => $source_name ?? $source_id,
        ]);
        continue;
      }

      $informant_header = $contribution['informant_header'] ?? [];
      $answers = $contribution['answers'] ?? [];
      $informant_id = $this->saveInformant($informant_number, $informant_header, $prefix);
      # @TODO: save answers and link selections to it (instead of linking to source)
    }
  }

  /**
   * Parse the source name to extract prefix and informant number range.
   * Pattern: {LANG}{initial_informant_number}_{LANG}{final_informant_number}[_extra_info]
   * Examples: IT1_IT5; ARG01_ARG05_Final
   *
   * @param  string  $source_name
   *
   * @return array
   */
  protected function parseSourceName($source_name) {
    $default_prefix = 'UNK';
    $result = [
      'prefix' => $default_prefix,
      'initial_informant_number' => null,
      'final_informant_number' => null,
    ];

    if (empty($source_name)) {
      return $result;
    }

    $parts = explode('_', $source_name);
    if (count($parts) < 2) {
      $this->logger->warning('Source name "@name" does not match expected pattern.', ['@name' => $source_name]);
      return $result;
    }

    $initial_part = $parts[0];
    $final_part = $parts[1];
    $pattern = '/^([A-Za-z]+)(\d+)$/';

    if (preg_match($pattern, $initial_part, $matches)) {
      $result['prefix'] = strtoupper($matches[1]);
      $result['initial_informant_number'] = (int)$matches[2];
    }

    if (preg_match($pattern, $final_part, $matches)) {
      $final_prefix = strtoupper($matches[1]);
      if ($result['prefix'] != $final_prefix) {
        $this->logger->warning('Source name "@name" has inconsistent prefixes: "@initial" and "@final"', [
          '@name' => $source_name,
          '@initial' => $result['prefix'],
          '@final' => $final_prefix,
          '@prefix' => $result['prefix'],
        ]);

        if ($result['prefix'] == $default_prefix) {
          $result['prefix'] = $final_prefix;
        }
      }

      $result['final_informant_number'] = (int)$matches[2];
    }

    if ($result['initial_informant_number'] === null || $result['final_informant_number'] === null) {
      $this->logger->warning('Source name "@name" does not contain valid informant numbers', ['@name' => $source_name]);
    }

    return $result;
  }

  protected function saveInformant($informant_number, array $informant_header, $prefix = 'UNK'): ?int {
    if (empty($informant_number)) {
      $this->logger->error('Informant number is required to save informant.');
      return null;
    }

    $informant_type = $this->pragmatica_prefix . 'informant';
    $informant_code = $prefix . str_pad($informant_number, 3, '0', STR_PAD_LEFT);

    $existing_informant = $this->getEntityIdByKey($informant_type, $this->code_key);
    if ($existing_informant) {
      $this->logger->error('Informant with code @code already exists. Skipping creation.', ['@code' => $informant_code]);
      return $existing_informant;
    }

    $informant_data = $this->convertInformantHeaderToEntityData($informant_header);
    $informant_data[$this->code_key] = $informant_code;

    try {
      return $this->upsertEntityByUniqueProperty($informant_type, $this->code_key, $informant_data);
    } catch (Exception $e) {
      $this->logger->error('Failed to save informant @code: @message', [
        '@code' => $informant_code,
        '@message' => $e->getMessage(),
      ]);
      return null;
    }
  }


  protected function convertInformantHeaderToEntityData(array $informant_header): array {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'informant');
    $entity_type = $this->getEntityTypeFromStorage($storage);
    $fields_to_xml_mapping = $entity_type->getFieldsToXmlMapping();

    $entity_data = [];
    foreach ($informant_header as $header => $value) {
      $found = false;
      foreach ($fields_to_xml_mapping as $field_name => $mapping) {
        $xml_tags = is_array($mapping) ? (is_array($mapping['xml']) ? $mapping['xml'] : [$mapping['xml']]) : [];

        if (!in_array(strtolower($header), $xml_tags)) {
          continue;
        }

        $found = true;

        if (empty($mapping['entity_type'])) {
          $entity_data[$field_name] = $value;
          continue 2;
        }

        $referenced_entity_type = $mapping['entity_type'];
        $referenced_unique_property = $mapping['unique_property'] ?? $this->name_key;

        $referenced_entity_id = $this->upsertEntityByUniqueProperty(
          $referenced_entity_type,
          $referenced_unique_property,
          [$referenced_unique_property => $value]
        );

        if ($referenced_entity_id) {
          $entity_data[$field_name] = $referenced_entity_id;
        } else {
          $this->logger->error('Failed to find or create referenced entity of type @type with @property = @value', [
            '@type' => $referenced_entity_type,
            '@property' => $referenced_unique_property,
            '@value' => $value,
          ]);
        }

        continue 2;
      }

      if (!$found) {
        $this->logger->warning('No mapping found for informant header "@header". Skipping.', ['@header' => $header]);
      }
    }

    return $entity_data;
  }

  /**
   * Parse the plain text content of a source into contributions.
   * a source can have multiple contributions, normally separated by a blank line
   *
   * @param  string  $plain_text
   *
   * @return array
   */  
  protected function parseSourcePlainText(string $plain_text): array {

    $individual_contributions = preg_split("/\n\s*\n(?=#)/", trim($plain_text));
  
    $contributions = [];

    foreach ($individual_contributions as $contribution_text) {
      $lines = preg_split("/\n/", trim($contribution_text));
      if (empty($lines)) {
        continue;
      }
  
      $header = [];
      $answers = [];
      $informant_number = null;
      $parsing_header = false;
  
      foreach ($lines as $i => $line) {
        $line = trim($line);

        if ($i === 0 && preg_match('/^#(\d+)$/', $line, $matches)) {
            $informant_number = (int)$matches[1];
            continue;
        } 
        elseif ($i === 0 && !preg_match('/^#(\d+)$/', $line, $matches)) {
          $contributions[] = [
            'error' => "First line must be informant number in format '#{number}', found: '$line'",
          ];
          continue 2;
        }
        elseif (preg_match('/^<([^>]+)>$/', $line, $matches) && !$parsing_header) {
            $parsing_header = true;
            continue;
        }
        elseif (preg_match('/^<\/([^>]+)>$/', $line, $matches) && $parsing_header) {
            $parsing_header = false;
            continue;
        }
        elseif (preg_match('/^<([^>]+)>(.*?)<\/\1>$/', $line, $matches) && $parsing_header) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            $header[$key] = $value;
            continue;
        }
        elseif (preg_match('/^(\d+)(.*)$/', $line, $matches)) {
          $parsing_header = false;
          $question_number = (int)$matches[1];
          $answer_text = trim($matches[2]);

          if (isset($answers[$question_number])) {
            $contributions[] = [
              'error' => "Duplicate answer for question number $question_number in contribution",
            ];
            continue 2;
          }

          $answers[$question_number] = $answer_text;

          while (isset($lines[$i + 1]) && !preg_match('/^(\d+)(.*)$/', trim($lines[$i + 1]))) {
            $i++;
            $answers[$question_number] .= ' ' . trim($lines[$i]);
          }

          $answers[$question_number] = trim($answers[$question_number]);
          continue;
        }
      }

      if (empty($answers)) {
        $contributions[] = [
          'error' => "No answers found in contribution",
        ];
        continue;
      }

      if ($informant_number === null) {
        $contributions[] = [
          'error' => "Informant number not found in contribution",
        ];
        continue;
      }
    
      $contributions[] = [
        'informant_number' => $informant_number,
        'informant_header' => $header,
        'answers' => $answers,
      ];
    }

    if (empty($contributions)) {
      $contributions[] = [
        'error' => "No valid contributions found in source plain text",
      ];
    }

    return $contributions;
  }

  /**
   * Get information from source files.
   *
   * @param  SimpleXMLElement  $source_xml
   * @param  \Drupal\Core\Entity\EntityStorageInterface  $storage
   *
   * @return array
   * @throws \ReflectionException
   */
  protected function getSourceFilesInfo(
    SimpleXMLElement $source_xml,
    EntityStorageInterface $storage
  ): array {
    $entity_type = $this->getEntityTypeFromStorage($storage);
    $fields_to_xml_mapping = $entity_type->getFieldsToXmlMapping();
    $internal_prefix = "internal://";
    $info = [];
    $plain_text_tag = 'plainTextPath';
    $rich_text_tag = 'richTextPath';
    $xml_fields = [$plain_text_tag, $rich_text_tag, 'path'];

    foreach ($xml_fields as $xml_key) {
      if (!isset($source_xml[$xml_key])) {
        continue;
      }

      $xml_value = (string) $source_xml[$xml_key];

      if (!str_contains($xml_value, $internal_prefix)) {
        $this->logger->error('Source file is not an internal file: @path', ['@path' => $xml_value]);
        continue;
      }

      $xml_value = str_replace($internal_prefix, '', $xml_value);
      $file_path = $this->sources_folder_path . '/' . $xml_value;

      if (!file_exists($file_path)) {
        $this->logger->error('Source file not found: @path', ['@path' => $file_path]);
        continue;
      }

      $target_field = array_search($xml_key, $fields_to_xml_mapping);

      if ($target_field === false) {
        $this->logger->error('Field mapping not found for XML key: @key', ['@key' => $xml_key]);
        continue;
      }

      if ($plain_text_tag === $xml_key) {
        $info[$target_field] = file_get_contents($file_path);
        continue;
      }

      if ($xml_key === $rich_text_tag && !$this->save_rich_text_files) {
        continue;
      }

      $destination_folder = $this->getPragmaticaDestinationFolder();

      $destination = $destination_folder . '/' . basename($file_path);
      $replace = FileSystemInterface::EXISTS_REPLACE;
      $file = file_save_data(file_get_contents($file_path), $destination, $replace);

      if ($file) {
        $info[$target_field] = ["target_id" => $file->id()];
        continue;
      }

      $this->logger->error('Failed to save file: @path', ['@path' => $file_path]);
    }

    return $info;
  }



  function addEntityKeyToMapping(string $entity_type, string $key, $id) {
    if (!isset($this->entities_unique_property_id_mapping[$entity_type])) {
      $this->entities_unique_property_id_mapping[$entity_type] = [];
    }
    $this->entities_unique_property_id_mapping[$entity_type][$key] = $id;
  }

  function getEntityIdByKey(string $entity_type, string $key) {
    return $this->entities_unique_property_id_mapping[$entity_type][$key] ?? NULL;
  }

  /**
   * Get or create an entity by a unique property.
   *
   * @param string $entity_type The entity type ID.
   * @param string $unique_property The unique property to check (e.g., 'guid', 'code', 'name').
   * @param array $entity_data The data to create the entity if it does not exist.
   * @param EntityStorageInterface $storage The storage handler for the entity type.
   *
   * @return int The ID of the existing or newly created entity.
   *
   * @throws \Exception
   */
  function upsertEntityByUniqueProperty(
    string $entity_type,
    string $unique_property,
    array $entity_data,
    bool $update_existing = false
  ) {
    unset($entity_data['id']);
    $storage = $this->entity_manager->getStorage($entity_type);

    $unique_property_value = $entity_data[$unique_property] ?? null;

    if (empty($unique_property_value)) {
      throw new Exception("Entity data must include the unique property: $unique_property");
    }

    $existing_id = $this->getEntityIdByKey($entity_type, $unique_property_value);
    if ($existing_id) {

      if ($update_existing) {
        $existing_entity = $storage->load($existing_id);
        if ($existing_entity) {
          foreach ($entity_data as $field => $value) {
            $existing_entity->set($field, $value);
          }

          if (!$existing_entity->save()) {
            throw new Exception('Failed to update entity: ' . $existing_entity->label());
          }

          return $existing_entity->id();
        }
      }

      return $existing_id;
    }

    $existing_entities = $storage->loadByProperties([$unique_property => $entity_data[$unique_property]]);
    if ($existing_entities) {
      $existing_entity = reset($existing_entities);
      $this->addEntityKeyToMapping($entity_type, $unique_property_value, $existing_entity->id());
      return $existing_entity->id();
    }

    $entity = $storage->create();
    foreach ($entity_data as $field => $value) {
      $entity->set($field, $value);
    }

    if (!$entity->save()) {
      throw new Exception('Failed to save entity: ' . $entity->label());
    }

    $this->addEntityKeyToMapping($entity_type, $unique_property_value, $entity->id());
    return $entity->id();
  }

  /**
   * Save an entity from an XML element.
   * This method is responsible for creating or updating an entity
   *
   * @param SimpleXMLElement $xml_element The XML element containing entity data.
   * @param EntityStorageInterface $storage The storage handler for the entity type.
   * @param array $extra_fields Additional fields to set on the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false The saved entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   *
   * @todo: Only update if the modified date is newer than the existing entity?
   */
  function saveXMLEntity(
    SimpleXMLElement $xml_element,
    EntityStorageInterface $storage,
    array $extra_fields = [],
    string $entity_unique_property = null
  ) {

    $datetime_fields = ['created', 'changed'];
    $user_reference_fields = ['creating_user_id', 'modifying_user_id'];

    $entity_type = $this->getEntityTypeFromStorage($storage);
    $fields_to_xml_mapping = $entity_type->getFieldsToXmlMapping();

    $entity_unique_property = $entity_unique_property ?? $this->guid_key;

    $entity_unique_property_value = (string) $xml_element[$fields_to_xml_mapping[$entity_unique_property]] ?? 
                      (string) $xml_element[$entity_unique_property] ?? 
                      $extra_fields[$entity_unique_property] ?? 
                      null; 
    

    if (empty($entity_unique_property_value)) {
      throw new Exception("Unique property '$entity_unique_property' value is missing in entity data.");
    }

    $existing = $storage->loadByProperties([$entity_unique_property => $entity_unique_property_value]);
    $entity = $existing ? reset($existing) : $storage->create();
    $entity->set($entity_unique_property, $entity_unique_property_value);

    foreach ($fields_to_xml_mapping as $field => $xml_key) {
      if ($field === $entity_unique_property) {
        continue;
      }

      $related_entity_type = null;
      $unique_property_related_entity = $this->name_key;

      if (is_array($xml_key)) {
        $xml_key = $xml_key['xml'] ?? null;
        $related_entity_type = $xml_key['entity_type'] ?? null;
        $unique_property_related_entity = $xml_key['unique_property'] ?? $unique_property_related_entity;
      }

      $xml_keys = is_array($xml_key) ? $xml_key : [$xml_key];
      $value = null;

      foreach ($xml_keys as $key) {
        if (isset($xml_element[$key])) {
          $value = (string) $xml_element[$key];
          break;
        }
        elseif (isset($xml_element->$key)) {
          $value = (string) $xml_element->$key;
          break;
        }
      }

      if (empty($value)) {
        continue;
      }

      if (in_array($field, $datetime_fields)) {
        $value = strtotime($value);
      }
      elseif (in_array($field, $user_reference_fields)) {
        $found_value = $this->getEntityIdByKey($this->pragmatica_prefix . 'user', $value);
        if (!$value) {
          $this->logger->error('User with GUID @guid not found for field @field.', [
            '@guid' => $value,
            '@field' => $field,
          ]);
        }
        $value = $found_value;
      }
      elseif (!empty($related_entity_type) && !empty($unique_property_related_entity)) {
        $found_value = $this->upsertEntityByUniqueProperty(
          $related_entity_type,
          $unique_property_related_entity,
          [$unique_property_related_entity => $value],
        );

        if (!$found_value) {
          $this->logger->error('Failed to find or create referenced entity of type @type with @property = @value for field @field.', [
            '@type' => $related_entity_type,
            '@property' => $unique_property_related_entity,
            '@value' => $value,
            '@field' => $field,
          ]);
          continue;
        }

        $value = $found_value;
      }

      $entity->set($field, $value);
    }

    foreach ($extra_fields as $field => $value) {
      if ($entity->hasField($field)) {
        $entity->set($field, $value);
      }
      else {
        $this->logger->warning('Entity @type does not have field @field', [
          '@type' => $storage->getEntityTypeId(),
          '@field' => $field,
        ]);
      }
    }

    if (!$entity->save()) {
      throw new Exception('Failed to save entity: ' . $entity->label());
    }
    else {
      $this->addEntityKeyToMapping($storage->getEntityTypeId(), $entity_unique_property_value, $entity->id());
    }

    return $entity;
  }


  /**
   * Get IDs from property values.
   *
   * @param string $property The property to be used for searching.
   * @param array $property_mapping The mapping of keys to property values.
   * @param string $entity_type_id The entity type ID to search in.
   *
   * @return array An array with property values replaced by their IDs.
   *
   */
  private function getIdFromProperty(
    string $property,
    array $property_mapping,
    string $entity_type_id
  ): array {

    $property_mapping_ids = [];
    $entity_storage = $this->entity_manager->getStorage($entity_type_id);

    foreach ($property_mapping as $entity_key => $property_value) {
      if ($this->getEntityIdByKey($entity_type_id, $entity_key)) {
        $property_mapping_ids[$entity_key] = $this->getEntityIdByKey($entity_type_id, $entity_key);
        continue;
      }

      $entity = $entity_storage->loadByProperties([$property => $property_value]);

      if (!$entity) {
        $this->logger->error(
          'Entity of type @type with property @property and value @value not found.',
          ['@type' => $entity_type_id, '@property' => $property, '@value' => $property_value]
        );
        continue;
      }

      $entity_id = reset($entity)->id();
      $property_mapping_ids[$entity_key] = $entity_id;
      $this->addEntityKeyToMapping($entity_type_id, $entity_key, $entity_id);
    }

    return $property_mapping_ids;
  }

  /**
   * @param  \Drupal\Core\Entity\EntityStorageInterface  $storage
   *
   * @return \Drupal\pragmatica\Entity\PragmaticaBaseEntity
   * @throws \ReflectionException
   * @throws \Exception
   */
  private function getEntityTypeFromStorage(EntityStorageInterface $storage): PragmaticaBaseEntity {
    $original_class = $storage->getEntityType()->getOriginalClass();
    $entity_type = (new ReflectionClass($original_class))->newInstanceWithoutConstructor();

    if (!$entity_type instanceof PragmaticaBaseEntity) {
      throw new Exception(
        "Importer does not support entities of type: " . $storage->getEntityTypeId()
      );
    }
    return $entity_type;
  }

  /**
   * Get the destination folder for Pragmatica files.
   * Creates the folder if it does not exist.
   *
   * @return string
   * // @todo: Configure private file path (https://www.drupal.org/docs/8/core/modules/file/overview#s-private-file-system-settings-in-drupal-8)
   */
  public static function getPragmaticaDestinationFolder(): string {
    $destination_folder = 'public://pragmatica';
    $folder_real_path = Drupal::service('file_system')->realpath(
      $destination_folder
    );
    if (!is_dir($folder_real_path)) {
      Drupal::service('file_system')->mkdir($folder_real_path);
    }
    return $destination_folder;
  }
}