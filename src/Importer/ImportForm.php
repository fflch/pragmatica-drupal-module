<?php

namespace Drupal\pragmatica\Importer;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Exception;
use ZipArchive;

class ImportForm extends FormBase {

  public function getFormId() {
    return 'pragmatica_import_form';
  }

  public function getAcceptedExtensions() {
    return ['qdpx', 'zip', 'qde', 'xml'];
  }

  public function getInputFileName() {
    return 'project_file';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form[$this->getInputFileName()] = [
      '#type' => 'file',
      '#title' => $this->t('Arquivo de dados (.qde, .xml) ou o arquivo compactado (.qdpx, .zip)'),
      '#upload_validators' => [
        'file_validate_extensions' => [implode(' ', $this->getAcceptedExtensions())],
      ],
    ];

    $form['keep_file'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Manter arquivo após importação?'),
      '#default_value' => false,
      '#description' => $this->t('Se marcado, o arquivo enviado será mantido após a importação no sistema de arquivos do Drupal.'),
    ];

    $form['save_rich_text_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Salvar o arquivo das fonte em formato docx?'),
      '#default_value' => false,
      '#description' => $this->t('Se marcado, os arquivos docx serão salvos no sistema de arquivos do Drupal e associados às fontes.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Importar'),
    ];

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => [implode(' ', $this->getAcceptedExtensions())]];
    $keep_file = $form_state->getValue('keep_file') ?? false;
    $save_rich_text_files = $form_state->getValue('save_rich_text_files') ?? false;

    $files = file_save_upload(
      $this->getInputFileName(),
      $validators,
      $keep_file ? (QDEImporter::getPragmaticaDestinationFolder() . '/') : false,
      null,
      FileSystemInterface::EXISTS_REPLACE
    );

    if (empty($files) || empty($files[0])) {
      $this->messenger()->addError($this->t('Erro ao fazer upload do arquivo.'));
      return;
    }

    /** @var File $file */
    $file = reset($files);
    $file_path = $file->getFileUri();
    $real_path = Drupal::service('file_system')->realpath($file_path);

    if (!$real_path || !file_exists($real_path)) {
      $this->messenger()->addError($this->t('Arquivo não encontrado no caminho especificado.'));
      return;
    }

    $file_extension = pathinfo($real_path, PATHINFO_EXTENSION);
    if (in_array($file_extension, ['qdpx', 'zip'])) {
      $paths = $this->processZipFile($real_path);
      $xmlFilePath = $paths['xml_file_path'] ?? null;
      $sourcesFolderPath = $paths['sources_folder_path'] ?? null;
    } elseif (in_array($file_extension, ['qde', 'xml'])) {
      $xmlFilePath = $real_path;
      $sourcesFolderPath = null;
    } else {
      $this->messenger()->addError($this->t('Formato de arquivo não suportado. Use .qpdx, .zip, .qde ou .xml.'));
      return;
    }

     $importService = new QDEImporter(
       $xmlFilePath,
       $sourcesFolderPath,
       Drupal::service('entity_type.manager'),
       Drupal::service('logger.factory'),
       $save_rich_text_files
     );

    // @todo: use batch api to show progress (https://git.drupalcode.org/project/examples/-/blob/3.0.4/modules/batch_example/src/Form/BatchExampleForm.php)
    try {
      $importService->import();
      $this->messenger()->addStatus($this->t('Importação concluída com sucesso.'));
    } catch (Exception $e) {
      $this->messenger()->addError($this->t('Erro ao importar o projeto: @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * @throws Exception
   */
  protected function processZipFile($zipFilePath) {
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath) !== true) {
      throw new Exception('Não foi possível abrir o arquivo ZIP.');
    }

    $xmlFilePath = null;
    $sourcesFolderPath = null;

    /** @var FileSystemInterface $file_system */
    $file_system = Drupal::service('file_system');

    $tempDir = $file_system ->getTempDirectory(). '/' . 'source_import_' . uniqid();
    if (!$file_system->prepareDirectory($tempDir, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new Exception('Não foi possível criar o diretório temporário para extração.');
    }

    if (!$zip->extractTo($tempDir)) {
      throw new Exception('Erro ao extrair o arquivo ZIP.');
    }

    $zip->close();

    foreach (scandir($tempDir) as $name) {
      if ($name === '.' || $name === '..') {
        continue;
      }
      elseif (str_ends_with($name, '.xml') || str_ends_with($name, '.qde')) {
        $xmlFilePath = $tempDir . '/' . $name;
      }
      elseif (is_dir($tempDir . '/' . $name)) {
        $sourcesFolderPath = $tempDir . '/' . $name;
      }
    }

    if (!$xmlFilePath) {
      throw new Exception('Nenhum arquivo XML ou QDE encontrado no ZIP.');
    }

    return [
      'xml_file_path' => $xmlFilePath,
      'sources_folder_path' => $sourcesFolderPath,
    ];
  }

}
