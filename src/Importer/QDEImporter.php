<?php
namespace Drupal\pragmatica\Importer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\pragmatica\Entity\PragmaticaBaseEntity;
use Drupal\pragmatica\Entity\User;
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
  protected $entity_prefix = 'pragmatica_';
  protected $entities_guid_id_mapping = [];

  protected $guidKey = 'guid';

  public function __construct(
    string $xml_file_path,
    string $sources_folder_path,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->xml_file_path = $xml_file_path;
    $this->sources_folder_path = $sources_folder_path;
    $this->entity_manager = $entity_type_manager;
    $this->logger = $logger_factory->get($this->entity_prefix . 'import');
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

    if (isset($xml->Selections)) {
      $this->importSelections($xml->Selections);
    }

    $notImplemented = [
      'NotesRef',
      'Links',
      'Sets',
    ];

    foreach ($notImplemented as $element) {
      if (isset($xml->$element)) {
        $this->logger->info('Importing @element is not implemented', ['@element' => $element]);
      }
    }
  }

  /**
   * Import Users.
   */
  protected function importUsers(SimpleXMLElement $users_xml) {
    $storage = $this->entity_manager->getStorage($this->entity_prefix . 'user');
    foreach ($users_xml->User as $userXml) {
        $this->saveEntity($userXml, $storage);
    }
  }

  /**
   * Import Sources.
   */
  protected function importSources(SimpleXMLElement $sourcesXml) {
    $storage = $this->entity_manager->getStorage($this->entity_prefix . 'source');
  }

  /**
   * Import Codes.
   */
  protected function importCodes(SimpleXMLElement $codesXml) {
    $storage = $this->entity_manager->getStorage($this->entity_prefix . 'code');
  }

  /**
   * Import Selections.
   */
  protected function importSelections(SimpleXMLElement $selectionsXml) {
    $storage = $this->entity_manager->getStorage($this->entity_prefix . 'selection');

    foreach ($selectionsXml->PlainTextSelection as $selectionXml) {
      $this->importPlainTextSelection($selectionXml, $storage);
    }

    foreach ($selectionsXml->AudioSelection as $selectionXml) {
      $this->importAudioSelection($selectionXml, $storage);
    }

    foreach ($selectionsXml->TranscriptSelection as $selectionXml) {
      $this->importTranscriptSelection($selectionXml, $storage);
    }
  }

  protected function importPlainTextSelection(
    SimpleXMLElement $xml,
    EntityStorageInterface $storage
  ) {

  }
  protected function importAudioSelection(
    SimpleXMLElement $xml,
    EntityStorageInterface $storage
  ) {

  }
  protected function importTranscriptSelection(
    SimpleXMLElement $xml,
    EntityStorageInterface $storage
  ) {

  }
  function addEntityGuidToMapping(string $entity_type, string $guid, $id) {
    if (!isset($this->entities_guid_id_mapping[$entity_type])) {
      $this->entities_guid_id_mapping[$entity_type] = [];
    }
    $this->entities_guid_id_mapping[$entity_type][$guid] = $id;
  }

  function getEntityIdByGuid(string $entity_type, string $guid) {
    return $this->entities_guid_id_mapping[$entity_type][$guid] ?? NULL;
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  function saveEntity(
    SimpleXMLElement $xml_element,
    EntityStorageInterface $storage
  ) {

    $datetime_fields = ['created', 'changed'];

    $guid = (string) $xml_element[$this->guidKey];
    if (empty($guid)) {
      throw new Exception('XML element missing required "guid" attribute.');
    }

    $original_class = $storage->getEntityType()->getOriginalClass();
    $entity_type = (new ReflectionClass($original_class))->newInstanceWithoutConstructor();

    if (!$entity_type instanceof PragmaticaBaseEntity) {
      throw new Exception("Importer does not support entities of type: " . $storage->getEntityTypeId());
    }

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
        $entity->set($field, $value);
      } elseif (isset($xml_element->$xml_key)) {
          $value = (string) $xml_element->$xml_key;
          $entity->set($field, $value);
      }
    }

    if (!$entity->save()) {
      throw new Exception('Failed to save entity: ' . $entity->label());
    } else {
      $this->addEntityGuidToMapping($storage->getEntityTypeId(), $guid, $entity->id());
      $this->logger->info('Saved entity: @name (@guid)', ['@name' => $entity->label(), '@guid' => $guid]);
    }

    return $entity;
  }
}
