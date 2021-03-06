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
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
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


require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Test class for UFGroup API - civicrm_uf_*
 * @todo Split UFGroup and UFJoin tests
 *
 * @package   CiviCRM
 */
class api_v3_UFFieldTest extends CiviUnitTestCase {
  // ids from the uf_group_test.xml fixture
  protected $_ufGroupId = 11;
  protected $_ufFieldId;
  protected $_contactId = 69;
  protected $_apiversion = 3;
  protected $_params;
  protected $_entity = 'uf_field';
  public $_eNoticeCompliant = TRUE;

  protected function setUp() {
    parent::setUp();
    $this->quickCleanup(
      array(
        'civicrm_group',
        'civicrm_contact',
        'civicrm_uf_group',
        'civicrm_uf_field',
        'civicrm_uf_join',
        'civicrm_uf_match',
      )
    );

    $op = new PHPUnit_Extensions_Database_Operation_Insert;
    $op->execute(
      $this->_dbconn,
      new PHPUnit_Extensions_Database_DataSet_FlatXMLDataSet(dirname(__FILE__) . '/dataset/uf_group_test.xml')
    );
    $this->_sethtmlGlobals();

    $this->callAPISuccess('uf_field', 'getfields', array('cache_clear' => 1));

    $this->_params = array(
      'field_name' => 'phone',
      'field_type' => 'Contact',
      'visibility' => 'Public Pages and Listings',
      'weight' => 1,
      'label' => 'Test Phone',
      'is_searchable' => 1,
      'is_active' => 1,
      'location_type_id' => 1,
      'phone_type_id' => 1,
      'uf_group_id' => $this->_ufGroupId,
    );
  }

  function tearDown() {
    $this->quickCleanup(
      array(
        'civicrm_group',
        'civicrm_contact',
        'civicrm_uf_group',
        'civicrm_uf_join',
        'civicrm_uf_match',
      )
    );
  }

  /**
   * create / updating field
   */
  public function testCreateUFField() {
    $params = $this->_params; // copy
    $ufField = $this->callAPIAndDocument('uf_field', 'create', $params, __FUNCTION__, __FILE__);
    unset($params['uf_group_id']);
    $this->_ufFieldId = $ufField['id'];
    foreach ($params as $key => $value) {
      $this->assertEquals($ufField['values'][$ufField['id']][$key], $params[$key]);
    }
  }

  public function testCreateUFFieldWithBadFieldName() {
    $params = $this->_params; // copy
    $params['field_name'] = 'custom_98789'; // invalid field
    $this->callAPIFailure('uf_field', 'create', $params);
  }

  function testCreateUFFieldWithWrongParams() {
    $this->callAPIFailure('uf_field', 'create', array('field_name' => 'test field'));
    $this->callAPIFailure('uf_field', 'create', array('label' => 'name-less field'));
  }
  /**
   * Create a field with 'weight=1' and then a second with 'weight=1'. The second field
   * winds up with weight=1, and the first field gets bumped to 'weight=2'.
   */
  public function testCreateUFFieldWithDefaultAutoWeight() {
    $params1 = $this->_params; // copy
    $ufField1 = $this->callAPISuccess('uf_field', 'create', $params1);
    $this->assertEquals(1, $ufField1['values'][$ufField1['id']]['weight']);
    $this->assertDBQuery(1, 'SELECT weight FROM civicrm_uf_field WHERE id = %1', array(
      1 => array($ufField1['id'], 'Int'),
    ));

    $params2 = $this->_params; // copy
    $params2['location_type_id'] = 2; // needs to be a different field
    $ufField2 = $this->callAPISuccess('uf_field', 'create', $params2);
    $this->assertEquals(1, $ufField2['values'][$ufField2['id']]['weight']);
    $this->assertDBQuery(1, 'SELECT weight FROM civicrm_uf_field WHERE id = %1', array(
      1 => array($ufField2['id'], 'Int'),
    ));
    $this->assertDBQuery(2, 'SELECT weight FROM civicrm_uf_field WHERE id = %1', array(
      1 => array($ufField1['id'], 'Int'),
    ));
  }

  /**
   * deleting field
   */
  public function testDeleteUFField() {
    $ufField = $this->callAPISuccess('uf_field', 'create', $this->_params);
    $params = array(
      'field_id' => $ufField['id'],
    );
    $result = $this->callAPIAndDocument('uf_field', 'delete', $params, __FUNCTION__, __FILE__);
  }

  public function testGetUFFieldSuccess() {
    $this->callAPISuccess($this->_entity, 'create', $this->_params);
    $result = $this->callAPIAndDocument($this->_entity, 'get', array(), __FUNCTION__, __FILE__);
    $this->getAndCheck($this->_params, $result['id'], $this->_entity);
  }

  /**
   * create / updating field
   */
  public function testReplaceUFFields() {
    $baseFields = array();
    $baseFields[] = array(
      'field_name' => 'first_name',
      'field_type' => 'Contact',
      'visibility' => 'Public Pages and Listings',
      'weight' => 3,
      'label' => 'Test First Name',
      'is_searchable' => 1,
      'is_active' => 1,
    );
    $baseFields[] = array(
      'field_name' => 'country',
      'field_type' => 'Contact',
      'visibility' => 'Public Pages and Listings',
      'weight' => 2,
      'label' => 'Test Country',
      'is_searchable' => 1,
      'is_active' => 1,
      'location_type_id' => 1,
    );
    $baseFields[] = array(
      'field_name' => 'phone',
      'field_type' => 'Contact',
      'visibility' => 'Public Pages and Listings',
      'weight' => 1,
      'label' => 'Test Phone',
      'is_searchable' => 1,
      'is_active' => 1,
      'location_type_id' => 1,
      'phone_type_id' => 1,
    );

    $params = array(
      'uf_group_id' => $this->_ufGroupId,
      'option.autoweight' => FALSE,
      'values' => $baseFields,
    );

    $result = $this->callAPIAndDocument('uf_field', 'replace', $params, __FUNCTION__, __FILE__);
    $inputsByName = CRM_Utils_Array::index(array('field_name'), $params['values']);
    $this->assertEquals(count($params['values']), count($result['values']));
    foreach ($result['values'] as $outUfField) {
      $this->assertTrue(is_string($outUfField['field_name']));
      $inUfField = $inputsByName[$outUfField['field_name']];
      foreach ($inUfField as $key => $inValue) {
        $this->assertEquals($inValue, $outUfField[$key],
          sprintf("field_name=[%s] key=[%s] expected=[%s] actual=[%s]",
            $outUfField['field_name'],
            $key,
            $inValue,
            $outUfField[$key]
          )
        );
      }
    }
  }
}
