<?php

require_once 'paymentui.civix.php';

/**
 * Implementation of hook_civicrm_tokens
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_tokens
 */
function paymentui_civicrm_tokens(&$tokens) {
  $tokens['partialPayment'] = array(
    'partialPayment.table' => 'Table of Partial Payment Information',
  );
}

/**
 * Implementation of hook_civicrm_tokenValues
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_tokenValues
 */
function paymentui_civicrm_tokenValues(&$values, $cids, $job = NULL, $tokens = array(), $context = NULL) {
  if (!empty($tokens['partialPayment'])) {
    foreach ($cids as $contactID) {
      $table = '
         <table class="partialPayment" align="left" border="1" cellpadding="4" cellspacing="1" style="border-collapse: collapse; text-align: left">
           <thead><tr>
             <th>Event Name</th>
             <th>Contact Name</th>
             <th>Total Amount</th>
             <th>Paid</th>
             <th>Balance</th>
           </tr></thead>
           <tbody>';
      $participantInfo = CRM_Paymentui_BAO_Paymentui::getParticipantInfo($contactID);
      foreach ($participantInfo as $row) {
        $table .= "
         <tr class=" . $row['rowClass'] . ">
           <td>" . $row['event_name'] . "</td>
           <td>" . $row['contact_name'] . "</td>
           <td>" . $row['total_amount'] . "</td>
           <td>" . $row['paid'] . "</td>
           <td>" . $row['balance'] . "</td>
         </tr>
       ";
      }
      $table .= "</tbody></table>";
      $partialPaymentTokens = array(
        'partialPayment.table' => $table,
      );
      $values[$contactID] = empty($values[$contactID]) ? $partialPaymentTokens : $values[$contactID] + $partialPaymentTokens;
    }
  }
}

/**
 * Function to process partial payments
 * @param $paymentParams - Payment Processor parameters
 * @param $participantInfo - participantID as key and contributionID, ContactID, PayLater, Partial Payment Amount
 * @return participantInfo array with 'Success' flag
 */
function paymentui_civicrm_process_partial_payments($paymentParams, $participantInfo) {
  //Iterate through participant info
  $processingFeeForPayment = 0;
  foreach ($participantInfo as $pId => $pInfo) {
    if (!$pInfo['contribution_id'] || !$pId) {
      $participantInfo[$pId]['success'] = 0;
      continue;
    }

    if ($pInfo['partial_payment_pay']) {
      // Update contribution and participant status for pending from pay later registrations
      if ($pInfo['payLater']) {
        // Using DAO instead of API because API does not allow changing the status from 'Pending from pay later' to 'Partially Paid'
        $contributionStatuses  = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
        $updateContribution    = new CRM_Contribute_DAO_Contribution();
        $contributionParams    = array(
          'id'                     => $pInfo['contribution_id'],
          'contact_id'             => $pInfo['cid'],
          'contribution_status_id' => array_search('Partially paid', $contributionStatuses),
        );

        $updateContribution->copyValues($contributionParams);
        $updateContribution->save();

        //Update participant Status from 'Pending from Pay Later' to 'Partially Paid'
        $pendingPayLater   = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_ParticipantStatusType', 'Pending from pay later', 'id', 'name');
        $partiallyPaid     = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_ParticipantStatusType', 'Partially paid', 'id', 'name');
        $participantStatus = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_Participant', $pId, 'status_id', 'id');

        if ($participantStatus == $pendingPayLater) {
          CRM_Event_BAO_Participant::updateParticipantStatus($pId, $pendingPayLater, $partiallyPaid, TRUE);
        }
      }
      //Making sure that payment params has the correct amount for partial payment
      $paymentParams['total_amount'] = $pInfo['partial_payment_pay'];
      $paymentParams['payment_instrument_id'] = 1;

      //Add additional financial transactions for each partial payment
      $trxnRecord = CRM_Contribute_BAO_Contribution::recordAdditionalPayment($pInfo['contribution_id'], $paymentParams, 'owed', $pId);

      if ($trxnRecord->id) {
        $participantInfo[$pId]['success'] = 1;
      }
    }
    if (!empty($pInfo['latefees'])) {
      try {
        $lateFeeContrib = civicrm_api3('Contribution', 'create', array(
          'financial_type_id' => "Event Fee",
          'total_amount' => $pInfo['latefees'],
          'contact_id' => $pInfo['cid'],
          'contribution_status_id' => "Completed",
          'payment_instrument_id' => "Credit Card",
          'source' => "partial payment form late fee",
        ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Error::debug_log_message(ts('API Error %1', array(
          'domain' => 'bot.roundlake.paymentui',
        )));
      }
    }
    if (!empty($pInfo['partial_payment_pay'])) {
      // Processing Fee 4%
      $processingFee = 1;
      $fees = CRM_Paymentui_BAO_Paymentui::getFeesFromSettings();
      if (!empty($fees['processing_fee'])) {
        $processingFee = $fees['processing_fee'] / 100;
      }
      $processingFeeForPayment = $processingFeeForPayment + round($pInfo['partial_payment_pay'] * $processingFee, 2);
    }
  }
  $loggedInUser = CRM_Core_Session::singleton()->getLoggedInContactID();
  try {
    $lateFeeContrib = civicrm_api3('Contribution', 'create', array(
      'financial_type_id' => "Event Fee",
      'total_amount' => $processingFeeForPayment,
      'contact_id' => $loggedInUser,
      'contribution_status_id' => "Completed",
      'payment_instrument_id' => "Credit Card",
      'source' => "partial payment form credit card fee",
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    $error = $e->getMessage();
    CRM_Core_Error::debug_log_message(ts('API Error %1', array(
      'domain' => 'bot.roundlake.paymentui',
    )));
  }
  return $participantInfo;
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function paymentui_civicrm_config(&$config) {
  _paymentui_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function paymentui_civicrm_xmlMenu(&$files) {
  _paymentui_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function paymentui_civicrm_install() {
  return _paymentui_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function paymentui_civicrm_uninstall() {
  return _paymentui_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function paymentui_civicrm_enable() {
  return _paymentui_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function paymentui_civicrm_disable() {
  return _paymentui_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function paymentui_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _paymentui_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function paymentui_civicrm_managed(&$entities) {
  return _paymentui_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function paymentui_civicrm_caseTypes(&$caseTypes) {
  _paymentui_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function paymentui_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _paymentui_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
