<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2009.                                       |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

/**
 * This class delegates to the chosen DataSource to grab the data to be
 *  imported.
 */
class CRM_Contact_Import_Form_DataSource extends CRM_Core_Form {

  private $_dataSource;

  private $_dataSourceIsValid = FALSE;

  private $_dataSourceClassFile;

  private $_dataSourceClass;

  /**
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  public function preProcess() {

    //Test database user privilege to create table(Temporary) CRM-4725
    CRM_Core_Error::ignoreException();
    $daoTestPrivilege = new CRM_Core_DAO;
    $daoTestPrivilege->query("CREATE TEMPORARY TABLE import_job_permission_one(test int) ENGINE=InnoDB");
    $daoTestPrivilege->query("CREATE TEMPORARY TABLE import_job_permission_two(test int) ENGINE=InnoDB");
    $daoTestPrivilege->query("DROP TABLE IF EXISTS import_job_permission_one, import_job_permission_two");
    CRM_Core_Error::setCallback();

    if ($daoTestPrivilege->_lastError) {
      CRM_Core_Error::fatal(ts('Database Configuration Error: Insufficient permissions. Import requires that the CiviCRM database user has permission to create temporary tables. Contact your site administrator for assistance.'));
    }

    $results    = array();
    $config     = CRM_Core_Config::singleton();
    $handler    = opendir($config->uploadDir);
    $errorFiles = array('sqlImport.errors', 'sqlImport.conflicts', 'sqlImport.duplicates', 'sqlImport.mismatch');

    while ($file = readdir($handler)) {
      if ($file != '.' && $file != '..' &&
        in_array($file, $errorFiles) && !is_writable($config->uploadDir . $file)
      ) {
        $results[] = $file;
      }
    }
    closedir($handler);
    if (!empty($results)) {
      CRM_Core_Error::fatal(ts('<b>%1</b> file(s) in %2 directory are not writable. Listed file(s) might be used during the import to log the errors occurred during Import process. Contact your site administrator for assistance.', array(1 => implode(', ', $results), 2 => $config->uploadDir)));
    }

    $this->_dataSourceIsValid = FALSE;
    $this->_dataSource = CRM_Utils_Request::retrieve(
      'dataSource',
      'String',
      CRM_Core_DAO::$_nullObject,
      FALSE,
      NULL,
      'GET'
    );

    $this->_params = $this->controller->exportValues($this->_name);
    if (!$this->_dataSource) {
      //considering dataSource as base criteria instead of hidden_dataSource.
      $this->_dataSource = CRM_Utils_Array::value('dataSource',
        $_POST,
        CRM_Utils_Array::value('dataSource',
          $this->_params
        )
      );
      $this->assign('showOnlyDataSourceFormPane', FALSE);
    }
    else {
      $this->assign('showOnlyDataSourceFormPane', TRUE);
    }

    if (strpos($this->_dataSource, 'CRM_Import_DataSource_') === 0) {
      $this->_dataSourceIsValid = TRUE;
      $this->assign('showDataSourceFormPane', TRUE);
      $dataSourcePath = explode('_', $this->_dataSource);
      $templateFile = "CRM/Contact/Import/Form/" . $dataSourcePath[3] . ".tpl";
      $this->assign('dataSourceFormTemplateFile', $templateFile);
    }
  }

  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */

  public function buildQuickForm() {

    // If there's a dataSource in the query string, we need to load
    // the form from the chosen DataSource class
    if ($this->_dataSourceIsValid) {
      $this->_dataSourceClassFile = str_replace('_', '/', $this->_dataSource) . ".php";
      require_once $this->_dataSourceClassFile;
      $this->_dataSourceClass = new $this->_dataSource;
      $this->_dataSourceClass->buildQuickForm( $this );
    }

    // Get list of data sources and display them as options
    $dataSources = $this->_getDataSources();

    $this->assign('urlPath', "civicrm/import");
    $this->assign('urlPathVar', 'snippet=4');

    $this->add('select', 'dataSource', ts('Data Source'), $dataSources, TRUE,
      array('onchange' => 'buildDataSourceFormBlock(this.value);')
    );

    // duplicate handling options
    $duplicateOptions = array();
    $duplicateOptions[] = $this->createElement('radio',
      NULL, NULL, ts('Skip'), CRM_Import_Parser::DUPLICATE_SKIP
    );
    $duplicateOptions[] = $this->createElement('radio',
      NULL, NULL, ts('Update'), CRM_Import_Parser::DUPLICATE_UPDATE
    );
    $duplicateOptions[] = $this->createElement('radio',
      NULL, NULL, ts('Fill'), CRM_Import_Parser::DUPLICATE_FILL
    );
    $duplicateOptions[] = $this->createElement('radio',
      NULL, NULL, ts('No Duplicate Checking'), CRM_Import_Parser::DUPLICATE_NOCHECK
    );

    $this->addGroup($duplicateOptions, 'onDuplicate',
      ts('For Duplicate Contacts')
    );

    $mappingArray = CRM_Core_BAO_Mapping::getMappings(CRM_Core_OptionGroup::getValue('mapping_type',
        'Import Contact',
        'name'
      ));

    $this->assign('savedMapping', $mappingArray);
    $this->addElement('select', 'savedMapping', ts('Mapping Option'), array('' => ts('- select -')) + $mappingArray);


    $js = array('onClick' => "buildSubTypes();buildDedupeRules();");
    // contact types option
    $contactOptions = array();
    if (CRM_Contact_BAO_ContactType::isActive('Individual')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Individual'), CRM_Import_Parser::CONTACT_INDIVIDUAL, $js
      );
    }
    if (CRM_Contact_BAO_ContactType::isActive('Household')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Household'), CRM_Import_Parser::CONTACT_HOUSEHOLD, $js
      );
    }
    if (CRM_Contact_BAO_ContactType::isActive('Organization')) {
      $contactOptions[] = $this->createElement('radio',
        NULL, NULL, ts('Organization'), CRM_Import_Parser::CONTACT_ORGANIZATION, $js
      );
    }

    $this->addGroup($contactOptions, 'contactType',
      ts('Contact Type')
    );

    $this->addElement('select', 'subType', ts('Subtype'));
    $this->addElement('select', 'dedupe', ts('Dedupe Rule'));

    CRM_Core_Form_Date::buildAllowedDateFormats($this);

    $config = CRM_Core_Config::singleton();
    $geoCode = FALSE;
    if (!empty($config->geocodeMethod)) {
      $geoCode = TRUE;
      $this->addElement('checkbox', 'doGeocodeAddress', ts('Lookup mapping info during import?'));
    }
    $this->assign('geoCode', $geoCode);

    $this->addElement('text', 'fieldSeparator', ts('Import Field Separator'), array('size' => 2));

    $this->addButtons(array(
        array(
          'type' => 'upload',
          'name' => ts('Continue >>'),
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ),
      )
    );
  }

  function setDefaultValues() {
    $config = CRM_Core_Config::singleton();
    $defaults = array(
      'dataSource' => 'CRM_Import_DataSource_CSV',
      'onDuplicate' => CRM_Import_Parser::DUPLICATE_SKIP,
      'contactType' => CRM_Import_Parser::CONTACT_INDIVIDUAL,
      'fieldSeparator' => $config->fieldSeparator,
    );

    if ($loadeMapping = $this->get('loadedMapping')) {
      $this->assign('loadedMapping', $loadeMapping);
      $defaults['savedMapping'] = $loadeMapping;
    }

    return $defaults;
  }

  private function _getDataSources() {
    // Open the data source dir and scan it for class files
    $config        = CRM_Core_Config::singleton();
    $dataSourceDir = $config->importDataSourceDir;
    $dataSources   = array();
    if (!is_dir($dataSourceDir)) {
      CRM_Core_Error::fatal("Import DataSource directory $dataSourceDir does not exist");
    }
    if (!$dataSourceHandle = opendir($dataSourceDir)) {
      CRM_Core_Error::fatal("Unable to access DataSource directory $dataSourceDir");
    }

    while (($dataSourceFile = readdir($dataSourceHandle)) !== FALSE) {
      $fileType = filetype($dataSourceDir . $dataSourceFile);
      $matches = array();
      if (($fileType == 'file' || $fileType == 'link') &&
        preg_match('/^(.+)\.php$/', $dataSourceFile, $matches)
      ) {
        $dataSourceClass = "CRM_Import_DataSource_" . $matches[1];
        require_once $dataSourceDir . DIRECTORY_SEPARATOR . $dataSourceFile;
        $object = new $dataSourceClass;
        $info   = $object->getInfo();
        $dataSources[$dataSourceClass] = $info['title'];
      }
    }
    closedir($dataSourceHandle);
    return $dataSources;
  }

  /**
   * Call the DataSource's postProcess method to take over
   * and then setup some common data structures for the next step
   *
   * @return void
   * @access public
   */
  public function postProcess() {
    $this->controller->resetPage('MapField');

    if ($this->_dataSourceIsValid) {
      // Setup the params array
      $this->_params = $this->controller->exportValues($this->_name);

      $storeParams = array(
        'onDuplicate' => 'onDuplicate',
        'dedupe' => 'dedupe',
        'contactType' => 'contactType',
        'contactSubType' => 'subType',
        'dateFormats' => 'dateFormats',
        'savedMapping' => 'savedMapping',
      );

      foreach ($storeParams as $storeName => $storeValueName) {
        $$storeName = $this->exportValue($storeValueName);
        $this->set($storeName, $$storeName);
      }

      $this->set('dataSource', $this->_params['dataSource']);
      $this->set('skipColumnHeader', CRM_Utils_Array::value('skipColumnHeader', $this->_params));

      $session = CRM_Core_Session::singleton();
      $session->set('dateTypes', $dateFormats);

      // Get the PEAR::DB object
      $dao = new CRM_Core_DAO();
      $db = $dao->getDatabaseConnection();

      //hack to prevent multiple tables.
      $this->_params['import_table_name'] = $this->get('importTableName');
      if (!$this->_params['import_table_name']) {
        $this->_params['import_table_name'] = 'civicrm_import_job_' . md5(uniqid(rand(), TRUE));
      }

      $this->_dataSourceClass->postProcess( $this->_params, $db, $this );

      // We should have the data in the DB now, parse it
      $importTableName = $this->get('importTableName');
      $fieldNames      = $this->_prepareImportTable($db, $importTableName);
      $mapper          = array();

      $parser = new CRM_Contact_Import_Parser_Contact($mapper);
      $parser->setMaxLinesToProcess(100);
      $parser->run($importTableName,
        $mapper,
        CRM_Import_Parser::MODE_MAPFIELD,
        $contactType,
        $fieldNames['pk'],
        $fieldNames['status'],
        CRM_Import_Parser::DUPLICATE_SKIP,
        NULL, NULL, FALSE,
        CRM_Contact_Import_Parser::DEFAULT_TIMEOUT,
        $contactSubType,
        $dedupe
      );

      // add all the necessary variables to the form
      $parser->set($this);
    }
    else {
      CRM_Core_Error::fatal("Invalid DataSource on form post. This shouldn't happen!");
    }
  }

  /**
   * Add a PK and status column to the import table so we can track our progress
   * Returns the name of the primary key and status columns
   *
   * @return array
   * @access private
   */
  private function _prepareImportTable($db, $importTableName) {
    /* TODO: Add a check for an existing _status field;
         *  if it exists, create __status instead and return that
         */

    $statusFieldName = '_status';
    $primaryKeyName = '_id';

    $this->set('primaryKeyName', $primaryKeyName);
    $this->set('statusFieldName', $statusFieldName);

    /* Make sure the PK is always last! We rely on this later.
         * Should probably stop doing that at some point, but it
         * would require moving to associative arrays rather than
         * relying on numerical order of the fields. This could in
         * turn complicate matters for some DataSources, which
         * would also not be good. Decisions, decisions...
         */

    $alterQuery = "ALTER TABLE $importTableName
                       ADD COLUMN $statusFieldName VARCHAR(32)
                            DEFAULT 'NEW' NOT NULL,
                       ADD COLUMN ${statusFieldName}Msg TEXT,
                       ADD COLUMN $primaryKeyName INT PRIMARY KEY NOT NULL
                               AUTO_INCREMENT";
    $db->query($alterQuery);

    return array('status' => $statusFieldName, 'pk' => $primaryKeyName);
  }

  /**
   * Return a descriptive name for the page, used in wizard header
   *
   *
   * @return string
   * @access public
   */
  public function getTitle() {
    return ts('Choose Data Source');
  }
}

