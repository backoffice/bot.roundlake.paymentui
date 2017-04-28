<?php
/**
 *
 * @package BOT Roundlake Payment User Interface Helper functions
 * $Id$
 *
 */
class CRM_Paymentui_BAO_Paymentui extends CRM_Event_DAO_Participant {
  public static function getParticipantInfo($contactID) {
    $relatedContactIDs   = self::getRelatedContacts($contactID);
    $relatedContactIDs[] = $contactID;
    $relContactIDs       = implode(',', $relatedContactIDs);

    //Get participant info for the primary and related contacts
    $sql = "SELECT p.id, p.contact_id, e.title, c.display_name, p.event_id, pp.contribution_id FROM civicrm_participant p
      INNER JOIN civicrm_contact c ON ( p.contact_id =  c.id )
			INNER JOIN civicrm_event e ON ( p.event_id = e.id )
			INNER JOIN civicrm_participant_payment pp ON ( p.id = pp.participant_id )
			WHERE
			p.contact_id IN ($relContactIDs)
			AND (p.status_id = 15 OR p.status_id = 5) AND p.is_test = 0";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->N) {
      while ($dao->fetch()) {
        // Get the payment details of all the participants
        $paymentDetails = CRM_Contribute_BAO_Contribution::getPaymentInfo($dao->id, 'event', FALSE, TRUE);
        //Get display names of the participants and additional participants, if any
        $displayNames   = self::getDisplayNames($dao->id, $dao->display_name);
        $paymentSched   = self::getLateFees($dao->event_id, $paymentDetails['paid']);
        if ($paymentDetails['balance'] == 0) {
          $paymentSched['totalDue'] = 0;
        }
        //Create an array with all the participant and payment information
        $participantInfo[$dao->id]['pid']             = $dao->id;
        $participantInfo[$dao->id]['cid']             = $dao->contact_id;
        $participantInfo[$dao->id]['contribution_id'] = $dao->contribution_id;
        $participantInfo[$dao->id]['event_name']      = $dao->title;
        $participantInfo[$dao->id]['contact_name']    = $displayNames;
        $participantInfo[$dao->id]['total_amount']    = $paymentDetails['total'];
        $participantInfo[$dao->id]['paid']            = $paymentDetails['paid'];
        $participantInfo[$dao->id]['balance']         = $paymentDetails['balance'];
        $participantInfo[$dao->id]['latefees']        = $paymentSched['lateFee'];
        $participantInfo[$dao->id]['nextDueDate']     = $paymentSched['nextDueDate'];
        $participantInfo[$dao->id]['totalDue']        = $paymentSched['totalDue'];
        $participantInfo[$dao->id]['rowClass']        = 'row_' . $dao->id;
        $participantInfo[$dao->id]['payLater']        = $paymentDetails['payLater'];
      }
    }
    else {
      return FALSE;
    }
    return $participantInfo;
  }

  /**
   * [getLateFees description]
   * @param  [type] $eventId [description]
   */
  public static function getLateFees($eventId, $amountPaid) {
    $lateFee = 0;
    try {
      $lateFeeSchedule = civicrm_api3('CustomField', 'getSingle', array(
        'sequential' => 1,
        'return' => array("id"),
        'name' => "event_late_fees",
        'api.Event.getsingle' => array(
          'id' => $eventId,
        ),
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(ts('API Error %1', array(
        'domain' => 'bot.roundlake.paymentui',
      )));
    }

    if (!empty($lateFeeSchedule['api.Event.getsingle']["custom_{$lateFeeSchedule['id']}"])) {
      $feeAmount = 0;
      $fees = self::getFeesFromSettings();
      if (!empty($fees['late_fee'])) {
        $feeAmount = $fees['late_fee'];
      }
      //parse schedule expects string that looks like: "04/15/2017:100\r\n04/20/2017:100\r\n04/25/2017:100"
      $scheduleToParse = $lateFeeSchedule['api.Event.getsingle']["custom_{$lateFeeSchedule['id']}"];
      $arrayOfDates = explode("\r\n", $scheduleToParse);
      $totalAmountDue = 0;
      $lateFee = 0;
      $currentDate = time();
      $pastKeys = array();
      foreach ($arrayOfDates as $key => &$dates) {
        list($dateText, $amountDue) = explode(":", $dates);
        $dueDate = DateTime::createFromFormat('m/d/Y', $dateText);
        $dueDate = date_timestamp_get($dueDate);
        $dates = array(
          'dateText' => $dateText,
          'line' => $dates,
          'unixDate' => $dueDate,
          'amountDue' => $amountDue,
          'diff' => $dueDate - $currentDate,
        );
        if ($dueDate < $currentDate) {
          $pastKeys[] = $key;
          $totalAmountDue = $totalAmountDue + $amountDue;
          if ($totalAmountDue > $amountPaid) {
            $lateFee = $lateFee + $feeAmount;
          }
        }
      }
      $nextAmountDue = max($pastKeys) + 1;
      $nextDueDate = '';
      if (!empty($arrayOfDates[$nextAmountDue]['amountDue'])) {
        $totalAmountDue = $totalAmountDue + $arrayOfDates[$nextAmountDue]['amountDue'];
      }
      if (!empty($arrayOfDates[$nextAmountDue]['dateText'])) {
        $nextDueDate = $arrayOfDates[$nextAmountDue]['dateText'];
      }
    }
    $totalAmountDue = $totalAmountDue - $amountPaid;
    if ($totalAmountDue < 0) {
      $totalAmountDue = 0;
    }
    return array(
      'lateFee'       => $lateFee,
      'totalDue'      => $totalAmountDue,
      'nextDueDate'   => $nextDueDate,
    );
  }

  /**
   * Helper function to get formatted display names of the the participants
   * Purpose - to generate comma separated display names of primary and additional participants
   */
  public static function getDisplayNames($participantId, $display_name) {
    $displayName[] = $display_name;
    //Get additional participant names
    $additionalPIds = CRM_Event_BAO_Participant::getAdditionalParticipantIds($participantId);
    if ($additionalPIds) {
      foreach ($additionalPIds as $pid) {
        $cId           = CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Participant', $pid, 'contact_id', 'id');
        $displayName[] = CRM_Contact_BAO_Contact::displayName($cId);
      }
    }
    $displayNames = implode(', ', $displayName);
    return $displayNames;
  }

  /**
   * Helper function to get related contacts of tthe contact
   * Checks for Child, Spouse, Child/Ward relationship types
   */
  public static function getRelatedContacts($contactID) {
    try {
      $result = civicrm_api3('Relationship', 'get', array(
        'sequential' => 1,
        'relationship_type_id' => 1,
        'contact_id_b' => "user_contact_id",
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(ts('API Error %1', array(
        'domain' => 'bot.roundlake.paymentui',
        1 => $error,
      )));
    }
    if (!empty($result['values'])) {
      $relatedContactIDs = array();
      foreach ($result['values'] as $relatedContact => $value) {
        $relatedContactIDs[] = $value['contact_id_a'];
      }
      return $relatedContactIDs;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Creates a financial trxn record for the CC transaction of the total amount
   */
  public function createFinancialTrxn($payment) {
    //Set Payment processor to Auth CC
    //To be changed for switching to live processor
    $payment_processor_id = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessor', 'Credit Card', 'id', 'name');
    $fromAccountID        = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialAccount', 'Accounts Receivable', 'id', 'name');
    $CCAccountID          = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialAccount', 'Payment Processor Account', 'id', 'name');
    $paymentMethods       = CRM_Contribute_PseudoConstant::paymentInstrument();
    $CC_id                = array_search('Credit Card', $paymentMethods);
    $params = array(
      'to_financial_account_id'   => $CCAccountID,
      'from_financial_account_id' => $fromAccountID,
      'trxn_date'                 => date('Ymd'),
      'total_amount'              => $payment['amount'],
      'fee_amount'                => '',
      'net_amount'                => '',
      'currency'                  => $payment['currencyID'],
      'status_id'                 => 1,
      'trxn_id'                   => $payment['trxn_id'],
      'payment_processor'         => $payment_processor_id,
      'payment_instrument_id'     => $CC_id,
    );
    require_once 'CRM/Core/BAO/FinancialTrxn.php';

    $trxn = new CRM_Financial_DAO_FinancialTrxn();
    $trxn->copyValues($params);
    $fids = array();
    if (!CRM_Utils_Rule::currencyCode($trxn->currency)) {
      $config = CRM_Core_Config::singleton();
      $trxn->currency = $config->defaultCurrency;
    }

    $trxn->save();
    $entityFinancialTrxnParams = array(
      'entity_table'      => "civicrm_financial_trxn",
      'entity_id'         => $trxn->id,
      'financial_trxn_id' => $trxn->id,
      'amount'            => $params['total_amount'],
      'currency'          => $trxn->currency,
    );
    $entityTrxn = new CRM_Financial_DAO_EntityFinancialTrxn();
    $entityTrxn->copyValues($entityFinancialTrxnParams);
    $entityTrxn->save();
  }

  public static function getFeesFromSettings() {
    $fees = array();
    try {
      $existingSetting = civicrm_api3('Setting', 'get', array(
        'sequential' => 1,
        'return' => array("paymentui_processingfee", "paymentui_latefee"),
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(
        t('API Error: %1', array(1 => $error, 'domain' => 'bot.roundlake.paymentui'))
      );
    }
    if (!empty($existingSetting['values'][0]['paymentui_processingfee'])) {
      $fees['processing_fee'] = $existingSetting['values'][0]['paymentui_processingfee'];
    }
    if (!empty($existingSetting['values'][0]['paymentui_latefee'])) {
      $fees['late_fee'] = $existingSetting['values'][0]['paymentui_latefee'];
    }
    return $fees;
  }

  public static function buildEmailTable($participantInfo, $receipt = FALSE, $processingFee = 0) {
    $table = '<table class="partialPayment" border="1" cellpadding="4" cellspacing="1" style="border-collapse: collapse; text-align: left">
     <thead><tr>
       <th>Event Name</th>
       <th>Contact Name</th>
       <th>Cost of Program</th>
       <th>Paid to Date</th>
       <th>Total Balance Owed</th>
    ';
    if (!$receipt) {
      $table .= '<th>Next Payment Due Date</th><th>Next Payment Due Amount</th></tr></thead><tbody>';
      foreach ($participantInfo as $row) {
        $table .= "
         <tr class=" . $row['rowClass'] . ">
           <td>" . $row['event_name'] . "</td>
           <td>" . $row['contact_name'] . "</td>
           <td>" . $row['total_amount'] . "</td>
           <td>" . $row['paid'] . "</td>
           <td>" . $row['balance'] . "</td>
           <td>" . $row['nextDueDate'] . "</td>
           <td>" . $row['totalDue'] . "</td>
         </tr>
       ";
      }
      $table .= "</tbody></table>";
    }
    if ($receipt) {
      $lateFeeTotal = 0;
      $totalAmountPaid = 0;
      $table .= ' <th>Payment Made</th></tr></thead><tbody>';
      foreach ($participantInfo as $row) {
        $table .= "
         <tr class=" . $row['rowClass'] . ">
           <td>" . $row['event_name'] . "</td>
           <td>" . $row['contact_name'] . "</td>
           <td>" . $row['total_amount'] . "</td>
           <td>" . ($row['paid'] + $row['partial_payment_pay']) . "</td>
           <td>" . ($row['balance'] - $row['partial_payment_pay']) . "</td>
           <td>" . $row['partial_payment_pay'] . "</td>
         </tr>
       ";
        if (!empty($row['latefees'])) {
          $lateFeeTotal = $lateFeeTotal + $row['latefees'];
        }
        if (!empty($row['partial_payment_pay'])) {
          $totalAmountPaid = $totalAmountPaid + $row['partial_payment_pay'];
        }
      }
      $table .= "</tbody></table><br>";
      $table .= "<p><strong>Late Fees:</strong> $ " . floatval($lateFeeTotal) . " </p>";
      $table .= "<p><strong>Processing Fee:</strong> $ " . floatval($processingFee) . " </p>";
      $table .= "<p><strong>Total:</strong> $ " . (floatval($totalAmountPaid) + floatval($lateFeeTotal) + floatval($processingFee)) . " </p>";
    }
    return $table;
  }

  public static function buildSimpleEmailTable($participantInfo) {
    $table = '<table class="partialPayment" cellspacing="1" style="border-collapse: collapse; text-align: left">
     <thead align="left"><tr>
       <th>Event Name</th>
       <th>Contact Name</th>
       <th>Payment Due Date</th>
       <th>Payment Amount Due</th>
    </tr></thead><tbody>
    ';
    $amountOwed = 0;
    foreach ($participantInfo as $row) {
      $amountOwed = $amountOwed + $row['totalDue'];
      $table .= "
       <tr class=" . $row['rowClass'] . ">
         <td>" . $row['event_name'] . "</td>
         <td>" . $row['contact_name'] . "</td>
         <td>" . $row['nextDueDate'] . "</td>
         <td>" . $row['totalDue'] . "</td>
       </tr>
     ";
    }
    $table .= "</tbody></table><br><div style='float:right;'><strong>Total Amount Due: </strong> $" . $amountOwed . "</div>";
    return $table;
  }

}
