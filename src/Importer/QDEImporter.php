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
  protected $original_codes_mapping = [];


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
   * Import a Code and its children recursively.
   * @throws \Exception
   * @todo: A code should be separated into multiple corresponding labels: AP1+AS5 => AP1 and AS5? 
   */
  protected function importCode(
    SimpleXMLElement $codeXml,
    EntityStorageInterface $storage,
    $parent_code_id = NULL
  ) {
    $codeXml[$this->code_key] = strtolower((string)$codeXml[$this->name_key]);
    $codeXml->addAttribute($this->code_key, (string)$codeXml[$this->name_key]);
    unset($codeXml[$this->name_key]);
    $saved_code = $this->saveXMLEntity($codeXml, $storage, [], $this->code_key);
    $saved_code_id = $saved_code->id();
    $this->original_codes_mapping[(string)$codeXml[$this->guid_key]] = $saved_code_id;

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

    $source_plain_text = $info_from_source_files['plain_text'] ?? null;

    if (empty($source_plain_text)) {
      $this->logger->warning('No plain text content available for source ID @id. Skipping selections import.', ['@id' => $source_id]);
      return;
    }

    $source_parsed = $this->parseSourcePlainText($source_plain_text);
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
      $responses = $contribution['responses'] ?? [];
      $informant_id = $this->saveInformant($informant_number, $informant_header, $prefix);
      $saved_responses = $this->saveResponses($responses, $informant_id, $source_id);
      $selections = $this->saveSelections($sourceXml, $source_plain_text, $source_id, $saved_responses);
    }
  }



  /**
   * Save selections from the source XML, linking codings to labels and responses.
   *
   * @param  SimpleXMLElement  $selectionsXml
   * @param  string  $source_plain_text
   * @param  int  $source_id
   * @param  array  $saved_responses
   *
   * @return array IDs of saved selections.
   */
  protected function saveSelections(
    SimpleXMLElement $selectionsXml, 
    string $source_plain_text, 
    int $source_id, 
    array $saved_responses
  ) {

    $storage = $this->entity_manager->getStorage($this->pragmatica_prefix . 'selection');
    $selections_saved = [];

    $selection_type = 'PlainTextSelection';

    if (!isset($selectionsXml->$selection_type)) {
      return $selections_saved;
    }

    $full_xml_selections = [];

    foreach ($selectionsXml->$selection_type as $selectionXml) {
      if (!isset($selectionXml->Coding)) {
        continue;
      }

      $selection_end = (int) $selectionXml['endPosition'] ?? null;
      $selection_start = (int) $selectionXml['startPosition'] ?? null;

      if ($selection_start === null || $selection_end === null) {
        $this->logger->error('Selection XML element missing required startPosition or endPosition attributes.');
        continue;
      }

      if ($selection_start === 0 && $selection_end >= strlen($source_plain_text)) {
        $this->logger->info('Selection spans entire source text for source ID @id. Skipping selection.', ['@id' => $source_id]);
        continue;
      }

      foreach ($selectionXml->Coding as $codingXml) {
        if (!$codingXml->CodeRef || !isset($codingXml->CodeRef['targetGUID'])) {
          $this->logger->error('Coding XML element missing required "CodeRef" child element or "targetGUID" attribute.');
          continue;
        }

        $full_xml_selections[] = [
          'code_guid' => (string) $codingXml->CodeRef['targetGUID'],
          'selection_start' => $selection_start,
          'selection_end' => $selection_end,
        ];
      }
    }

    foreach ($full_xml_selections as $xml_selection) {
      $code_guid = $xml_selection['code_guid'];
      $selection_start = $xml_selection['selection_start'];
      $selection_end = $xml_selection['selection_end'];

      $label_id = $this->original_codes_mapping[$code_guid] ?? null;
      if (!$label_id) {
        $this->logger->error('Label with GUID @guid not found. Skipping selection.', ['@guid' => $code_guid]);
        continue;
      }

      $matched_response_id = null;
      $matched_response_start = null;
      $matched_response_situation_number = null;

      foreach ($saved_responses as $response_info) {
        if ($response_info['start_position'] <= $selection_start && $response_info['end_position'] >= $selection_end) {
          $matched_response_id = $response_info['response_id'] ?? null;
          $matched_response_start = $response_info['start_position'] ?? null;
          $matched_response_situation_number = $response_info['situation_number'] ?? null;
          break;
        }
      }

      if (!$matched_response_id) {
        $this->logger->warning('No matching response found for selection "@selection" starting at position @start and ending at @end. Skipping selection.', [
          '@selection' => substr($source_plain_text, $selection_start, $selection_end - $selection_start),
          '@start' => $selection_start,
          '@end' => $selection_end,
        ]);
        continue;
      }

      // @todo: the selection is not being cut correctly, probably because extra/missing characters...
      /* @todo: verify error, maybe related:
        Failed to save selection for source ID 15: SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect string value: '\xC3' for column 
        `drupal_fflch`.`pragmatica_selection`.`name` at row 1: 
        INSERT INTO {pragmatica_selection} ("name", "response_id", "label_id", "start_position", "end_position", "created", "changed") 
        VALUES (:db_insert_placeholder_0, :db_insert_placeholder_1, :db_insert_placeholder_2, :db_insert_placeholder_3, :db_insert_placeholder_4, 
        :db_insert_placeholder_5, :db_insert_placeholder_6); 
        Array ( [:db_insert_placeholder_0] => e ofere� [:db_insert_placeholder_1] => 716 
        [:db_insert_placeholder_2] => 183 [:db_insert_placeholder_3] => 238 [:db_insert_placeholder_4] => 246 
        [:db_insert_placeholder_5] => 1758309383 [:db_insert_placeholder_6] => 1758309383 )
       */
      $selection_text = substr($source_plain_text, $selection_start, $selection_end - $selection_start);

      $response_selection_start = $selection_start - $matched_response_start;
      $response_selection_end = $selection_end - $matched_response_start;

      if (str_starts_with($selection_text, (string)$matched_response_situation_number)) {
        $selection_text = substr($selection_text, strlen((string)$matched_response_situation_number));
        $response_selection_start += strlen((string)$matched_response_situation_number);
      }

      $selection_entity_data = [
        'name' => trim($selection_text),
        'response_id' => $matched_response_id,
        'label_id' => $label_id,
        'start_position' => $response_selection_start,
        'end_position' => $response_selection_end
      ];

      try {
        $saved_selection = $this->saveXMLEntity(
            new SimpleXMLElement('<Selection/>'),
            $storage,
            $selection_entity_data,
            ['name', 'response_id', 'label_id']
          );
          $selections_saved[] = $saved_selection->id();
      } catch (Exception $e) {
        $this->logger->error('Failed to save selection for source ID @source: @message', [
          '@source' => $source_id,
          '@message' => $e->getMessage(),
        ]);
      }

    }

    return $selections_saved;
  }



  protected function saveResponses(array $responses, ?int $informant_id, int $source_id): array {
    $saved_response_ids = [];
    if (empty($responses)) {
      return $saved_response_ids;
    }

    foreach ($responses as $question_number => $response_info) {
      $situation_id = $this->upsertEntityByUniqueProperty(
        $this->pragmatica_prefix . 'situation',
        $this->code_key,
        [$this->code_key => (string)$question_number]
      );

      $response_data = [
        'informant_id' => $informant_id,
        'source_id' => $source_id,
        'situation_id' => $situation_id,
        'name' => $response_info['full_response'] ?? '',
      ];

      try {
        $saved_response = $this->saveXMLEntity(
            new SimpleXMLElement('<Response/>'),
            $this->entity_manager->getStorage($this->pragmatica_prefix . 'response'),
            $response_data,
            array_keys($response_data)
          );
      } catch (Exception $e) {
        $this->logger->error('Failed to save response for question @question_number: @message', [
          '@question_number' => $question_number,
          '@message' => $e->getMessage(),
        ]);
      }

      $response_info['response_id'] = $saved_response->id();
      $saved_response_ids[$question_number] = $response_info;
    }

    return $saved_response_ids;
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
      $responses = [];
      $informant_number = null;
      $parsing_header = false;
  
      foreach ($lines as $i => $line) {
        $raw_line = $line;
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
          $situation_number = (int)$matches[1];
          $response_text = trim($matches[2]);
          $start_position = strpos($plain_text, $raw_line);

          if (isset($responses[$situation_number])) {
            $contributions[] = [
              'error' => "Duplicate response for question number $situation_number in contribution",
            ];
            continue 2;
          }

          $end_position = $start_position + strlen($raw_line) - 1;

          while (isset($lines[$i + 1]) && !preg_match('/^(\d+)(.*)$/', trim($lines[$i + 1]))) {
            $i++;
            $new_line = $lines[$i];
            $end_position += strlen($new_line) + 1;
            $response_text .= ' ' . trim($new_line);
          }

          $responses[$situation_number] = [
            'full_response' => trim($response_text),
            'start_position' => $start_position,
            'end_position' => $end_position,
            'situation_number' => $situation_number,
          ];
          continue;
        }
      }

      if (empty($responses)) {
        $contributions[] = [
          'error' => "No responses found in contribution",
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
        'responses' => $responses,
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



  function addEntityKeyToMapping(string $entity_type, $key, $id) {
    $key = is_array($key) ? implode('|', $key) : $key;
    if (!isset($this->entities_unique_property_id_mapping[$entity_type])) {
      $this->entities_unique_property_id_mapping[$entity_type] = [];
    }
    $this->entities_unique_property_id_mapping[$entity_type][$key] = $id;
  }

  function getEntityIdByKey(string $entity_type, $key) {
    $key = is_array($key) ? implode('|', $key) : $key;
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
    $entity_composite_properties = []
  ) {

    $datetime_fields = ['created', 'changed'];
    $user_reference_fields = ['creating_user_id', 'modifying_user_id'];

    $entity_type = $this->getEntityTypeFromStorage($storage);
    $fields_to_xml_mapping = $entity_type->getFieldsToXmlMapping();

    $entity_composite_properties = empty($entity_composite_properties) ? $this->guid_key : $entity_composite_properties;
    $entity_unique_properties = is_array($entity_composite_properties) ? $entity_composite_properties : [$entity_composite_properties];

    $unique_properties = [];

    foreach ($entity_unique_properties as $unique_property) {

        $entity_unique_property_value = $this->getValueFromXmlElement($xml_element, $unique_property);

        if (empty($entity_unique_property_value) && isset($extra_fields[$unique_property])) {
            $entity_unique_property_value = $extra_fields[$unique_property];
        }

        if (!empty($entity_unique_property_value)) {
            $unique_properties[$unique_property] = $entity_unique_property_value;
        }
    }

    if (empty($unique_properties)) {
      throw new Exception('At least one unique property must be provided and present in the XML element or extra fields.');
    }

    $existing = $storage->loadByProperties($unique_properties);
    $entity = $existing ? reset($existing) : $storage->create();
    foreach ($unique_properties as $field => $value) {
      $entity->set($field, $value);
    }

    foreach ($fields_to_xml_mapping as $field => $xml_key) {
      if (isset($unique_properties[$field])) {
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
        $value = $this->getValueFromXmlElement($xml_element, $key);
        if (!empty($value)) {
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
      $this->addEntityKeyToMapping($storage->getEntityTypeId(), array_values($unique_properties), $entity->id());
    }

    return $entity;
  }


  private function getValueFromXmlElement(SimpleXMLElement $xml_element, string $key) {
    if (isset($xml_element[$key])) {
      return (string) $xml_element[$key];
    }
    elseif (isset($xml_element->$key)) {
      return (string) $xml_element->$key;
    }
    return null;
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