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
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

require_once 'CiviTest/CiviSeleniumTestCase.php';
class WebTest_Contribute_AddBatchesTest extends CiviSeleniumTestCase {

  protected function setUp() {
    parent::setUp();
  }

  function testBatchAddContribution() {
    $this->webtestLogin();
    $itemCount = 5;
    // create contact
    $contact = array();

    //Open Live Contribution Page
    $this->openCiviPage("batch", "reset=1");
    $this->click("xpath=//div[@class='crm-submit-buttons']/a");
    $this->waitForElementPresent("_qf_Batch_next");
    $this->type("item_count", $itemCount);
    $this->type("total", 500);
    $this->click("_qf_Batch_next");
    $this->waitForPageToLoad($this->getTimeoutMsec());
    // Add Contact Details
    $data = array();
    for ($i=1; $i<=$itemCount; $i++ ) {
      $data[$i] = array(
        'first_name' => 'Ma'.substr(sha1(rand()), 0, 7),
        'last_name' => 'An'.substr(sha1(rand()), 0, 7),
        'financial_type' => 'Donation',
        'amount' => 100,

      );
      $this->_fillData($data[$i], $i, "Contribution");
    }

    $this->click("_qf_Entry_cancel");
    $this->waitForPageToLoad($this->getTimeoutMsec());
    $this->_verifyData($data, "Contribution");
  }

  function testBatchAddMembership() {
    $this->webtestLogin();
    $itemCount = 5;
    // create contact
    $contact = array();
    $batchTitle = 'Batch-'.substr(sha1(rand()), 0, 7);

    //Open Live Contribution Page
    $this->openCiviPage("batch", "reset=1");
    $this->click("xpath=//div[@class='crm-submit-buttons']/a");
    $this->waitForElementPresent("_qf_Batch_next");
    $this->click("title");
    $this->type("title", $batchTitle);
    $this->select("type_id", "Membership");
    $this->type("item_count", $itemCount);
    $this->type("total", 500);
    $this->click("_qf_Batch_next");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    // Add Contact Details
    $data = array();
    for ($i=1; $i<=$itemCount; $i++ ) {
      $data[$i] = array(
        'first_name' => 'Ma'.substr(sha1(rand()), 0, 7),
        'last_name' => 'An'.substr(sha1(rand()), 0, 7),
        'membership_type' => 'Default Organization',
        'amount' => 100,

        'financial_type' => 'Member Dues',
      );
      $this->_fillData($data[$i], $i, "Membership");
    }
    $this->click("_qf_Entry_cancel");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    $this->_verifyData($data, "Membership");
  }

  function _fillData($data, $row, $type) {
    $email = $data['first_name'] . '@example.com';
    $this->webtestNewDialogContact($data['first_name'], $data['last_name'], $email, 4, "primary_profiles_{$row}", "primary_{$row}");

    if ($type == "Contribution") {
      $this->select("field_{$row}_financial_type", $data['financial_type']);
      $this->type("field_{$row}_total_amount", $data['amount']);
      $this->webtestFillDateTime("field_{$row}_receive_date", "+1 week");
      $this->type("field_{$row}_contribution_source", substr(sha1(rand()), 0, 10));
      $this->select("field_{$row}_payment_instrument", "Check");
      $this->type("field_{$row}_check_number", rand());
      $this->click("field[{$row}][send_receipt]");
      $this->click("field_{$row}_invoice_id");
      $this->type("field_{$row}_invoice_id", substr(sha1(rand()), 0, 10));

    } elseif ($type == "Membership") {
      $this->select("field[{$row}][membership_type][0]", $data['membership_type']);
      $this->webtestFillDate("field_{$row}_join_date", "now");
      $this->webtestFillDate("field_{$row}_membership_start_date", "now");
      $this->webtestFillDate("field_{$row}_membership_end_date", "+1 month");
      $this->type("field_{$row}_membership_source", substr(sha1(rand()), 0, 10));
      $this->click("field[{$row}][send_receipt]");
      $this->select("field_{$row}_financial_type", $data['financial_type']);

      $this->webtestFillDateTime("field_{$row}_receive_date", "+1 week");
      $this->select("field_{$row}_payment_instrument", "Check");
      $this->type("field_{$row}_check_number", rand());
      $this->select("field_{$row}_contribution_status_id", "Completed");
    }
  }

  function _checkResult($data, $type) {
    if ($type == "Contribution") {
      $this->openCiviPage("contribute/search", "reset=1", "contribution_date_low");
      $this->type("sort_name", "{$data['first_name']} {$data['last_name']}");
      $this->clickLink("_qf_Search_refresh", "xpath=//div[@id='contributionSearch']//table//tbody/tr[1]/td[11]/span/a[text()='View']");
      $this->clickLink("xpath=//div[@id='contributionSearch']//table//tbody/tr[1]/td[11]/span/a[text()='View']", "_qf_ContributionView_cancel-bottom");
      $expected = array(
        'From'                => "{$data['first_name']} {$data['last_name']}",
        'Financial Type'      => $data['financial_type'],
        'Total Amount'        => $data['amount'],
        'Contribution Status' => 'Completed',
      );

      $this->webtestVerifyTabularData($expected);
    }
    elseif ($type == "Membership") {
      $this->openCiviPage("member/search", "reset=1", "member_join_date_low");

      // select contact
      $this->type("sort_name", "{$data['first_name']} {$data['last_name']}");
      $this->clickLink("_qf_Search_refresh", "xpath=//div[@id='memberSearch']//table//tbody/tr[1]/td[11]/span/a[text()='View']");
      $this->click("xpath=//div[@id='memberSearch']//table/tbody/tr[1]/td[11]/span/a[text()='View']");
      $this->waitForElementPresent("_qf_MembershipView_cancel-bottom");
      $expected = array(
        2 => 'General',
        4 => 'New'
      );
      foreach ($expected as $label => $value) {
        $this->verifyText("xpath=id('MembershipView')/div[2]/div/table[1]/tbody/tr[$label]/td[2]", preg_quote($value));
      }
      //View Contribution
      $this->waitForElementPresent("xpath=//div[@class='crm-block crm-content-block crm-membership-view-form-block']/table[2]/tbody/tr[1]/td[8]/span/a[text()='View']");
      $this->click("xpath=//div[@class='crm-block crm-content-block crm-membership-view-form-block']/table[2]/tbody/tr[1]/td[8]/span/a[text()='View']");
      $this->waitForElementPresent("_qf_ContributionView_cancel-bottom");
      $expected = array(
        'From'                => "{$data['first_name']} {$data['last_name']}",
        'Financial Type'      => $data['financial_type'],
        'Total Amount'        => $data['amount'],
        'Contribution Status' => 'Completed',
      );

      $this->webtestVerifyTabularData($expected);
    }
  }

  function _verifyData($data, $type) {
    $this->waitForElementPresent("xpath=//div[@id='crm-batch-selector_wrapper']//table//tbody/tr[1]/td[7]/span/a[text()='Enter records']");
    $this->clickLink("xpath=//div[@id='crm-batch-selector_wrapper']//table//tbody/tr[1]/td[7]/span/a[text()='Enter records']", "_qf_Entry_upload");
    $this->click("_qf_Entry_upload");
    $this->waitForPageToLoad($this->getTimeoutMsec());
    foreach ($data as $value) {
      $this->_checkResult($value, $type);
    }
  }

  function webtestNewDialogContact($fname = 'Anthony', $lname = 'Anderson', $email = 'anthony@anderson.biz', $type = 4, $row) {
    // 4 - Individual profile
    // 5 - Organization profile
    // 6 - Household profile
    $this->select("{$row}", "value={$type}");
    // create new contact using dialog
    //   $this->waitForElementPresent('#first_name');
    $this->waitForElementPresent('_qf_Edit_next');

    switch ($type) {
      case 4:
        $this->type('first_name', $fname);
        $this->type('last_name', $lname);
        break;

      case 5:
        $this->type('organization_name', $fname);
        break;

      case 6:
        $this->type('household_name', $fname);
        break;
    }

    $this->type('email-Primary', $email);
    $this->click('_qf_Edit_next');

    // Is new contact created?
    $this->assertTrue($this->isTextPresent("{$lname}, {$fname} has been created."), "Status message didn't show up after saving!");
  }
}
