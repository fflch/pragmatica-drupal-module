<?php

namespace Drupal\pragmatica\Importer;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Handles CSV import for seed and research data.
 */
class CSVImporter {

  protected $entityTypeManager;

  // Tags that are structural and should not be treated as label codes.
  const SKIP_TAGS = ['T', 'TV', 'V', 'nTV', 'nr', 'meta'];

  const LANGS = [
    '1' => 'ALE',
    '2' => 'ARG',
    '3' => 'BRA',
    '4' => 'CHI',
    '5' => 'ESP',
    '6' => 'ITA',
    '7' => 'JAP',
    '8' => 'POR',
    '9' => 'RUS',
    '10' => 'SUE',
    '11' => 'URU',
    '12' => 'USA',
    '13' => 'CHN'
  ];

  const GENDERS = [
    '1' => 'F',
    '2' => 'M',
    '3' => 'O'
  ];

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Read a CSV file and return rows as associative arrays.
   * Handles UTF-16LE with BOM and UTF-8. Supports comma and tab delimiters.
   */
  public function readCsv(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === FALSE) {
      throw new \RuntimeException("Cannot read file: $path");
    }

    // Detect UTF-16LE BOM (FF FE)
    if (strlen($raw) >= 2 && ord($raw[0]) === 0xFF && ord($raw[1]) === 0xFE) {
      $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
    }
    // Detect UTF-16BE BOM (FE FF)
    elseif (strlen($raw) >= 2 && ord($raw[0]) === 0xFE && ord($raw[1]) === 0xFF) {
      $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
    }
    // Strip UTF-8 BOM if present
    elseif (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
      $raw = substr($raw, 3);
    }

    // Normalize line endings and use PHP's csv parser
    $raw = str_replace("\r\n", "\n", $raw);
    $raw = str_replace("\r", "\n", $raw);

    // Detect delimiter from the first line
    $first_newline = strpos($raw, "\n");
    $first_line = $first_newline !== FALSE ? substr($raw, 0, $first_newline) : $raw;
    $tab_count = substr_count($first_line, "\t");
    $comma_count = substr_count($first_line, ',');
    $delimiter = $tab_count > $comma_count ? "\t" : ',';

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $raw);
    rewind($handle);

    $headers = NULL;
    $rows = [];
    while (($values = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
      if ($headers === NULL) {
        $headers = $values;
        continue;
      }
      if (count($values) >= count($headers)) {
        $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
      }
    }
    fclose($handle);

    return $rows;
  }

  /**
   * Read a simple CSV file (comma-separated, with headers).
   * Uses fgetcsv() so quoted cells containing embedded newlines are handled correctly.
   */
  public function readSimpleCsv(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === FALSE) {
      throw new \RuntimeException("Cannot read file: $path");
    }
    // Strip UTF-8 BOM
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
      $raw = substr($raw, 3);
    }
    $raw = str_replace("\r\n", "\n", $raw);
    $raw = str_replace("\r", "\n", $raw);

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $raw);
    rewind($handle);

    $headers = NULL;
    $rows = [];
    while (($values = fgetcsv($handle, 0, ',')) !== FALSE) {
      if ($headers === NULL) {
        $headers = $values;
        continue;
      }
      if (count($values) >= count($headers)) {
        $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
      }
    }
    fclose($handle);

    return $rows;
  }

  /**
   * Import seed data from a CSV file.
   *
   * @param string $entity_type  e.g. 'pragmatica_age_interval'
   * @param string $csv_file     Absolute path to CSV file
   * @param bool   $reset        If true, delete all existing entities first
   * @param string $unique_field Field to upsert by (default: 'code', fallback 'name')
   */
  public function importSeedData(string $entity_type, string $csv_file, bool $reset = FALSE, string $unique_field = 'code'): array {
    $rows = $this->readSimpleCsv($csv_file);
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $result = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];

    if ($reset) {
      $existing = $storage->loadMultiple();
      $storage->delete($existing);
      $result['deleted'] = count($existing);
    }

    // Build a reverse map (friendly label → machine field name) so users can
    // use the same column headers they see in the entity's form or list view.
    $label_map = $this->buildFieldLabelMap($entity_type);

    foreach ($rows as $row) {
      try {
        // Remap user-friendly headers (e.g. "Código", "Nome", "Tipo") to machine
        // field names before any further processing.
        $row = $this->remapRowByLabels($row, $label_map);

        // Determine unique field: try code → name → short_name (first non-empty column)
        $lookup_field = NULL;
        $lookup_value = NULL;
        foreach (['code', 'name', 'short_name'] as $try_field) {
          if (isset($row[$try_field]) && $row[$try_field] !== '') {
            $lookup_field = $try_field;
            $lookup_value = $row[$try_field];
            break;
          }
        }

        if ($lookup_value === NULL) {
          continue;
        }

        // Check for existing entity
        $existing = $storage->loadByProperties([$lookup_field => $lookup_value]);
        $entity = $existing ? reset($existing) : NULL;

        // Handle foreign keys and color for labels
        $fields = $row;
        if ($entity_type === 'pragmatica_label') {
          if (isset($row['type_name']) && $row['type_name'] !== '') {
            // Try matching label type by name, then by code
            $type_id = $this->getEntityIdByField('pragmatica_label_type', 'name', $row['type_name'])
                    ?? $this->getEntityIdByField('pragmatica_label_type', 'code', $row['type_name']);
            if ($type_id) {
              $fields['type_id'] = $type_id;
            }
            unset($fields['type_name']);
          }
          elseif (isset($row['type_code']) && $row['type_code'] !== '') {
            $type_id = $this->getEntityIdByField('pragmatica_label_type', 'code', $row['type_code'])
                    ?? $this->getEntityIdByField('pragmatica_label_type', 'name', $row['type_code']);
            if ($type_id) {
              $fields['type_id'] = $type_id;
            }
            unset($fields['type_code']);
          }
          // Auto-generate color if not provided
          if (empty($fields['color'])) {
            $fields['color'] = $this->generateLabelColor($row['code'] ?? $row['name'] ?? '');
          }
        }

        if ($entity) {
          foreach ($fields as $field => $value) {
            if ($entity->hasField($field)) {
              $entity->set($field, $value);
            }
          }
          $entity->save();
          $result['updated']++;
        }
        else {
          // Only set fields that exist on the entity type.
          $temp = $storage->create([]);
          $safe_fields = array_filter($fields, fn($v, $k) => $temp->hasField($k), ARRAY_FILTER_USE_BOTH);
          $entity = $storage->create($safe_fields);
          $entity->save();
          $result['created']++;
        }
      }
      catch (\Exception $e) {
        $result['errors'][] = $e->getMessage();
      }
    }

    return $result;
  }

  /**
   * Import research data from a tab-separated CSV file.
   *
   * @param string $csv_file        Absolute path
   * @param array  $column_mapping  Maps CSV column name → internal key
   * @param bool   $reset           Delete all research data first
   */
  public function importResearchData(string $csv_file, array $column_mapping, bool $reset = FALSE): array {
    $rows = $this->readCsv($csv_file);
    $result = ['informants' => 0, 'responses' => 0, 'selections' => 0, 'errors' => []];

    if ($reset) {
      $this->deleteAllResearchData();
    }

    foreach ($rows as $row) {
      try {
        $informant_id = $this->importInformantRow($row, $column_mapping);
        if (!$informant_id) continue;

        $response_col = $column_mapping['response'] ?? 'pedido';
        $tagged_text = $this->findColumnValue($row, $response_col) ?? '';
        if (trim($tagged_text) === '') continue;

        $parsed = $this->parseTaggedText($tagged_text);

        $situation_col = $column_mapping['situation'] ?? 'Situacao';
        $situation_short_name = $this->findColumnValue($row, $situation_col) ?? '';
        $situation_id = $this->getEntityIdByField('pragmatica_situation', 'short_name', $situation_short_name);

        $response_storage = $this->entityTypeManager->getStorage('pragmatica_response');
        $response = $response_storage->create([
          'name' => $parsed['plain_text'],
          'informant_id' => $informant_id,
          'situation_id' => $situation_id,
        ]);
        $response->save();
        $response_id = $response->id();
        $result['responses']++;

        foreach ($parsed['selections'] as $sel) {
          $label_id = $this->getOrCreateLabel($sel['code']);
          if (!$label_id) continue;
          $selection_storage = $this->entityTypeManager->getStorage('pragmatica_selection');
          $selection = $selection_storage->create([
            'name' => $sel['text'],
            'response_id' => $response_id,
            'label_id' => $label_id,
            'start_position' => $sel['start'],
            'end_position' => $sel['end'],
          ]);
          $selection->save();
          $result['selections']++;
        }

        $result['informants']++;
      }
      catch (\Exception $e) {
        $result['errors'][] = $e->getMessage();
      }
    }

    return $result;
  }

  /**
   * Find a column value by exact or prefix match.
   * Handles CSV headers that have annotation legends after the column name.
   */
  protected function findColumnValue(array $row, string $column_key): ?string {
    // Exact match first
    if (array_key_exists($column_key, $row)) {
      return $row[$column_key] !== '' ? $row[$column_key] : NULL;
    }
    // Prefix match (e.g. 'Lingua' matches 'Lingua ALE = 1 / ...')
    foreach ($row as $key => $value) {
      if (strncasecmp($key, $column_key, strlen($column_key)) === 0) {
        return $value !== '' ? $value : NULL;
      }
    }
    return NULL;
  }

  /**
   * Import or update a single informant row, returning its entity ID.
   */
  protected function importInformantRow(array $row, array $column_mapping): ?int {
    $id_col = $column_mapping['id'] ?? 'ID';
    $informant_code = $this->findColumnValue($row, $id_col);
    if (!$informant_code) return NULL;

    $lang_col = $column_mapping['language'] ?? 'Lingua';
    $age_col = $column_mapping['age_interval'] ?? 'Idade';
    $gender_col = $column_mapping['gender'] ?? 'Genero';
    $residence_col = $column_mapping['residence'] ?? 'Residencia';
    $education_col = $column_mapping['education'] ?? 'Escolaridade';
    $profession_col = $column_mapping['profession'] ?? 'Profissao';

    $language_code = $this->findColumnValue($row, $lang_col);
    $language_id = NULL;
    if ($language_code) {
      // Try by numeric code directly (e.g., '1' → Alemão)
      $language_id = $this->getEntityIdByField('pragmatica_language', 'code', $language_code);
      if (!$language_id) {
        // Try by short_name (e.g., 'ALE')
        $language_id = $this->getEntityIdByField('pragmatica_language', 'short_name', $language_code);
      }
      if (!$language_id) {
        $lang_name = self::LANGS[$language_code] ?? $language_code;
        $language_id = $this->getEntityIdByField('pragmatica_language', 'name', $lang_name)
                    ?? $this->getEntityIdByField('pragmatica_language', 'short_name', $lang_name)
                    ?? $this->getEntityIdByField('pragmatica_language', 'code', $lang_name);
      }
    }

    $age_code = $this->findColumnValue($row, $age_col);
    $age_interval_id = $age_code ? $this->getEntityIdByField('pragmatica_age_interval', 'code', $age_code) : NULL;

    $gender_code = $this->findColumnValue($row, $gender_col);
    $gender_id = NULL;
    if ($gender_code) {
      // Try by numeric code directly (e.g., '1' → Feminino)
      $gender_id = $this->getEntityIdByField('pragmatica_gender', 'code', $gender_code);

      if (!$gender_id) {
        // Fallback: hardcoded numeric→letter map for backward compatibility
        $gender_name = self::GENDERS[$gender_code] ?? $gender_code;
        $gender_id = $this->getEntityIdByField('pragmatica_gender', 'name', $gender_name)
                  ?? $this->getEntityIdByField('pragmatica_gender', 'code', $gender_name);
      }
    }

    $residence_name = $this->findColumnValue($row, $residence_col);
    $city_residency_id = $residence_name ? $this->getOrCreateEntityByName('pragmatica_city', $residence_name) : NULL;

    $education_name = $this->findColumnValue($row, $education_col);
    $education_id = $education_name ? $this->getOrCreateEntityByName('pragmatica_education', $education_name) : NULL;

    $profession_name = $this->findColumnValue($row, $profession_col);
    $profession_id = $profession_name ? $this->getOrCreateEntityByName('pragmatica_profession', $profession_name) : NULL;

    $storage = $this->entityTypeManager->getStorage('pragmatica_informant');
    $existing = $storage->loadByProperties(['code' => $informant_code]);
    $entity = $existing ? reset($existing) : NULL;

    $fields = [
      'code' => $informant_code,
      'language_id' => $language_id,
      'age_interval_id' => $age_interval_id,
      'gender_id' => $gender_id,
      'city_residency_id' => $city_residency_id,
      'education_id' => $education_id,
      'profession_id' => $profession_id,
    ];

    if ($entity) {
      foreach ($fields as $field => $value) {
        if ($value !== NULL && $entity->hasField($field)) {
          $entity->set($field, $value);
        }
      }
      $entity->save();
    }
    else {
      $entity = $storage->create(array_filter($fields, fn($v) => $v !== NULL));
      $entity->save();
    }

    return (int) $entity->id();
  }

  /**
   * Parse tagged response text like <T><ap2><pp1>text</pp1></ap2></T>.
   * Returns ['plain_text' => string, 'selections' => [...]]
   */
  public function parseTaggedText(string $tagged_text): array {
    $plain_text = '';
    $selections = [];
    $stack = []; // Each entry: ['code' => string, 'start' => int]
    $pos = 0;
    $len = strlen($tagged_text);

    $i = 0;
    while ($i < $len) {
      if ($tagged_text[$i] === '<') {
        $end = strpos($tagged_text, '>', $i);
        if ($end === FALSE) {
          $plain_text .= $tagged_text[$i];
          $i++;
          continue;
        }
        $tag_content = substr($tagged_text, $i + 1, $end - $i - 1);
        $i = $end + 1;

        if (substr($tag_content, 0, 1) === '/') {
          // Closing tag — strip leading '/'
          $code = substr($tag_content, 1);
          if (in_array($code, self::SKIP_TAGS)) continue;
          // Pop matching entry from stack
          for ($k = count($stack) - 1; $k >= 0; $k--) {
            if ($stack[$k]['code'] === $code) {
              $sel_start = $stack[$k]['start'];
              $sel_end = $pos;
              $text = substr($plain_text, $sel_start, $sel_end - $sel_start);
              $selections[] = [
                'code' => $code,
                'text' => $text,
                'start' => $sel_start,
                'end' => $sel_end,
              ];
              array_splice($stack, $k, 1);
              break;
            }
          }
        }
        else {
          // Opening tag
          $code = $tag_content;
          if (in_array($code, self::SKIP_TAGS)) continue;
          $stack[] = ['code' => $code, 'start' => $pos];
        }
      }
      else {
        $plain_text .= $tagged_text[$i];
        $pos++;
        $i++;
      }
    }

    return ['plain_text' => $plain_text, 'selections' => $selections];
  }

  /**
   * Get entity ID by a specific field value. Returns NULL if not found.
   */
  public function getEntityIdByField(string $entity_type, string $field, string $value): ?int {
    if (trim($value) === '') return NULL;
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $result = $storage->loadByProperties([$field => $value]);
      if ($result) {
        return (int) reset($result)->id();
      }
    }
    catch (\Exception $e) {
      // Entity type may not exist
    }
    return NULL;
  }

  protected function buildFieldLabelMap(string $entity_type): array {
    try {
      $entity_type_def = $this->entityTypeManager->getDefinition($entity_type);
      $entity_class = $entity_type_def->getClass();
      if (!method_exists($entity_class, 'baseFieldDefinitions')) {
        return [];
      }
      $field_definitions = $entity_class::baseFieldDefinitions($entity_type_def);
      $map = [];
      foreach ($field_definitions as $field_id => $field_def) {
        $label = (string) $field_def->getLabel();
        if ($label === '') {
          continue;
        }
        $normalized = mb_strtolower(trim($label));
        if (isset($map[$normalized])) {
          continue;
        }

        if ($field_def->getType() === 'entity_reference'
            && $field_id !== 'id'
            && substr($field_id, -3) === '_id') {
          $map[$normalized] = substr($field_id, 0, -3) . '_name';
        }
        else {
          $map[$normalized] = $field_id;
        }
      }
      return $map;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Return a copy of $row with any key that matches a known friendly label
   * replaced by the corresponding machine field name.  Keys that already use
   * machine names pass through unchanged.
   */
  protected function remapRowByLabels(array $row, array $label_map): array {
    if (empty($label_map)) {
      return $row;
    }
    $remapped = [];
    foreach ($row as $key => $value) {
      $normalized = mb_strtolower(trim($key));
      $remapped[$label_map[$normalized] ?? $key] = $value;
    }
    return $remapped;
  }

  /**
   * Get or create an entity by its name field.
   */
  public function getOrCreateEntityByName(string $entity_type, string $name): ?int {
    if (trim($name) === '') return NULL;
    $id = $this->getEntityIdByField($entity_type, 'name', $name);
    if ($id) return $id;
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $entity = $storage->create(['name' => $name]);
      $entity->save();
      return (int) $entity->id();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Get or create a label by its code.
   */
  protected function getOrCreateLabel(string $code): ?int {
    if (trim($code) === '') return NULL;
    $clean_code = trim($code);
    $id = $this->getEntityIdByField('pragmatica_label', 'code', $clean_code);
    if ($id) return $id;
    try {
      $label_info = [
        'code' => $clean_code,
        'name' => $clean_code,
        'color' => $this->generateLabelColor($clean_code),
      ];

      $label_type_id = $this->getLabelTypeIdForCode($clean_code);
      if ($label_type_id) {
        $label_info['type_id'] = $label_type_id;
      }

      $storage = $this->entityTypeManager->getStorage('pragmatica_label');
      $entity = $storage->create($label_info);
      $entity->save();
      return (int) $entity->id();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }


  /**
   * Determine label type ID based on code prefix.
   */
  protected function getLabelTypeIdForCode(string $code): ?int {
    $code = ltrim($code, '+');
    preg_match('/^([a-z]+)/i', strtolower($code), $matches);
    $prefix = $matches[1] ?? '';

    return $this->getEntityIdByField('pragmatica_label_type', 'code', $prefix)
          ?? $this->getEntityIdByField('pragmatica_label_type', 'name', $prefix);
  }

  /**
   * Generate a visually distinct color for a label based on its type prefix.
   * Returns a hex color string (e.g. '#f5b87a') suitable as a text background.
   */
  protected function generateLabelColor(string $code): string {
    if (trim($code) === '') {
      return '#cccccc';
    }
    $code = ltrim($code, '+');

    // Extract alphabetic prefix (e.g. 'ap' from 'ap2', 'mms' from 'mms1as')
    preg_match('/^([a-z]+)/i', strtolower($code), $matches);
    $prefix = $matches[1] ?? '';

    // Format: [hue, saturation]
    $palette = [
      'ap'  => [30,  72],  // golden-orange
      'mms' => [90,  60],  // yellow-green
      'md'  => [150, 58],  // emerald-green
      'pp'  => [210, 65],  // cornflower-blue
      'ml'  => [270, 62],  // violet
      'as'  => [330, 68],  // rose-pink
    ];

    if (isset($palette[$prefix])) {
      [$base_hue, $saturation] = $palette[$prefix];
    }
    else {
      $base_hue  = abs(crc32($prefix)) % 360;
      $saturation = 55;
    }

    $h1 = abs(crc32($code));               // drives hue shift
    $h2 = abs(crc32(strrev($code)));       // drives saturation shift
    $h3 = abs(crc32('_' . $code));         // drives lightness

    // ±15° hue shift — keeps a 30° safety gap to the neighbouring palette group
    $hue_offset = ($h1 % 31) - 15;
    // ±25% saturation shift — wide spread from muted to vivid
    $sat_offset = ($h2 % 51) - 25;
    // 40–85% lightness — very wide range (dark gold vs pale gold are clearly different)
    $lightness  = 40 + ($h3 % 46);

    $hue = (($base_hue + $hue_offset) + 360) % 360;
    $sat = max(30, min(92, $saturation + $sat_offset));

    return $this->hslToHex($hue, $sat, $lightness);
  }

  /**
   * Convert HSL values to a hex color string.
   */
  protected function hslToHex(int $h, int $s, int $l): string {
    $h = $h / 360;
    $s = $s / 100;
    $l = $l / 100;

    if ($s == 0) {
      $v = (int) round($l * 255);
      return sprintf('#%02x%02x%02x', $v, $v, $v);
    }

    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;

    $hue2rgb = function (float $p, float $q, float $t): float {
      if ($t < 0) $t += 1;
      if ($t > 1) $t -= 1;
      if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
      if ($t < 1 / 2) return $q;
      if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
      return $p;
    };

    $r = (int) round($hue2rgb($p, $q, $h + 1 / 3) * 255);
    $g = (int) round($hue2rgb($p, $q, $h) * 255);
    $b = (int) round($hue2rgb($p, $q, $h - 1 / 3) * 255);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
  }

  /**
   * Delete all research data (selections, responses, informants).
   */
  protected function deleteAllResearchData(): void {
    foreach (['pragmatica_selection', 'pragmatica_response', 'pragmatica_informant'] as $entity_type) {
      try {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
        if ($ids) {
          // do it in batches to avoid memory issues
          $batch_size = 50;
          $chunks = array_chunk($ids, $batch_size);
          foreach ($chunks as $chunk) {
            $entities = $storage->loadMultiple($chunk);
            $storage->delete($entities);
          }
        }
      }
      catch (\Exception $e) {
        // ignore
      }
    }
  }

}
