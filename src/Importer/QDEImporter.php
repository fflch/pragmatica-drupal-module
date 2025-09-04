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
  protected $entities_guid_id_mapping = [];

  protected $guidKey = 'guid';

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

    $notImplemented = [
      'NotesRef',
      'Links',
      'Sets',
    ];

    foreach ($notImplemented as $element) {
      if (isset($xml->$element)) {
        $this->logger->warning('Importing @element is not implemented', ['@element' => $element]);
      }
    }
  }

  /**
   * Import Users.
   */
  protected function importUsers(SimpleXMLElement $users_xml) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'user');
    foreach ($users_xml->User as $userXml) {
        $this->saveEntity($userXml, $storage);
    }
  }

  /**
   * Import Codes.
   *
   * @throws \Exception
   */
  protected function importCodes(SimpleXMLElement $codesXml) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'code');
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
    $saved_code = $this->saveEntity($codeXml, $storage, ['parent_id' => $parent_code_id]);
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
      'name',
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

          $extra_info = array_merge($info_from_source_files,
            ['type_id' => $source_type_id]
          );

          $saved_source = $this->saveEntity($sourceXml, $storage, $extra_info);

          $source_id = $saved_source->id();
          $this->importSelections($sourceXml, $source_id);
        }
      }
    }
  }

  /**
   * Import Selections.
   */
  protected function importSelections(
    SimpleXMLElement $selectionsXml,
    $source_id
  ) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'selection');

    $selection_type_mapping = [
      'PlainTextSelection' => 'Texto',
      'AudioSelection' => 'Áudio',
      'TranscriptionSelection' => 'Transcrição',
      'VideoSelection' => 'Vídeo',
      'PictureSelection' => 'Imagem',
      'DocumentSelection' => 'Documento (PDF)',
    ];

    $selection_type_mapping_ids = $this->getIdFromProperty(
      'name',
      $selection_type_mapping,
      $this->pragmatica_prefix . 'selection_type'
    );

    foreach ($selection_type_mapping_ids as $selection_type => $selection_type_id) {
      if (isset($selectionsXml->$selection_type)) {
        foreach ($selectionsXml->$selection_type as $selectionXml) {
          $this->importSelection($selectionXml, $storage, $source_id);
        }
      }
    }

  }

  protected function importSelection(
    SimpleXMLElement $xml,
    EntityStorageInterface $storage,
    $source_id
  ) {

    $saved_selection = $this->saveEntity($xml, $storage, ['source_id' => $source_id]);
    $selection_id = $saved_selection->id();

    foreach ($xml->Coding as $codingXml) {
      $this->importCoding($codingXml, $selection_id);
    }
  }

  /**
   * Import Coding.
   *
   */
  function importCoding(
    SimpleXMLElement $xml,
    $selection_id
  ) {
    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'coding');

    if (!$xml->CodeRef || !isset($xml->CodeRef['targetGUID'])) {
      $this->logger->error('Coding XML element missing required "CodeRef" child element or "targetGUID" attribute.');
      return;
    }

    $code_guid = (string) $xml->CodeRef['targetGUID'];
    $code_id = $this->getEntityIdByKey($this->pragmatica_prefix . 'code', $code_guid);
    if (!$code_id) {
      $this->logger->error('Code with GUID @guid not found.', ['@guid' => $code_guid]);
      return;
    }

    $coding_data = [
      'selection_id' => $selection_id,
      'code_id' => $code_id,
    ];

    $this->saveEntity($xml, $storage, $coding_data);
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
        $this->logger->info('Skipping rich text file import for @path', ['@path' => $file_path]);
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
    if (!isset($this->entities_guid_id_mapping[$entity_type])) {
      $this->entities_guid_id_mapping[$entity_type] = [];
    }
    $this->entities_guid_id_mapping[$entity_type][$key] = $id;
  }

  function getEntityIdByKey(string $entity_type, string $key) {
    return $this->entities_guid_id_mapping[$entity_type][$key] ?? NULL;
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
  function saveEntity(
    SimpleXMLElement $xml_element,
    EntityStorageInterface $storage,
    array $extra_fields = []
  ) {

    $datetime_fields = ['created', 'changed'];
    $user_reference_fields = ['creating_user_id', 'modifying_user_id'];

    $guid = (string) $xml_element[$this->guidKey];
    if (empty($guid)) {
      throw new Exception('XML element missing required "guid" attribute.');
    }

    $entity_type = $this->getEntityTypeFromStorage($storage);

    $fields_to_xml_mapping = $entity_type->getFieldsToXmlMapping();
    $existing = $storage->loadByProperties([$this->guidKey => $guid]);
    $entity = $existing ? reset($existing) : $storage->create();
    $entity->set($this->guidKey, $guid);

    foreach ($fields_to_xml_mapping as $field => $xml_key) {
      if (isset($xml_element[$xml_key])) {
        $value = (string) $xml_element[$xml_key];

        if (in_array($field, $datetime_fields)) {
          $value = strtotime($value);
        }
        elseif (in_array($field, $user_reference_fields)) {
          $value = $this->getEntityIdByKey($this->pragmatica_prefix . 'user', $value);
          if (!$value) {
            $this->logger->error('User with GUID @guid not found for field @field.', [
              '@guid' => $value,
              '@field' => $field,
            ]);
          }
        }

        $entity->set($field, $value);
      }
      elseif (isset($xml_element->$xml_key)) {
          $value = (string) $xml_element->$xml_key;
          $entity->set($field, $value);
      }
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
      $this->addEntityKeyToMapping($storage->getEntityTypeId(), $guid, $entity->id());
      // $this->logger->info('Saved entity: @name (@guid)', ['@name' => $entity->label(), '@guid' => $guid]);
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
  private function getEntityTypeFromStorage(EntityStorageInterface $storage
  ): PragmaticaBaseEntity {
    $original_class = $storage->getEntityType()->getOriginalClass();
    $entity_type = (new ReflectionClass($original_class))->newInstanceWithoutConstructor();

    if (!$entity_type instanceof PragmaticaBaseEntity) {
      throw new Exception(
        "Importer does not support entities of type: " . $storage->getEntityTypeId(
        )
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
