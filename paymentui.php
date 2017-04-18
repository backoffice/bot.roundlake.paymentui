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
         <table class="partialPayment">
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
 * Implementation of hook_civicrm_custom
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_custom
 */
function paymentui_civicrm_custom($op, $groupID, $entityID, &$params) {
  // on back end we will send receipt with token when this field is populated for custom field
  // TODO create message template with ID
  // TODO create custom field
  $mailParams = array(
    'messageTemplateID' => $msgTemplate['id'],
    // contact id if contact tokens are to be replaced
    'contactId' => $objectRef->contact_id,
    // the From: header
    'from' => 'alice+from@aghstrategies.com',
    // the recipient’s email - mail is sent only if set
    'toEmail' => $newMem['email'],
    // the Cc: header
    'cc' => 'alice+cc@aghstrategies.com',
    // whether this is a test email (and hence should include the test banner)
    'isTest' => FALSE,
  );
  //https://github.com/civicrm/civicrm-core/blob/fa33a2c369572781bd7bb56479e0d194f77d8efc/CRM/Core/BAO/MessageTemplate.php#L310
  $sent = CRM_Core_BAO_MessageTemplate::sendTemplate($mailParams);
}

/**
 * Function to process partial payments
 * @param $paymentParams - Payment Processor parameters
 * @param $participantInfo - participantID as key and contributionID, ContactID, PayLater, Partial Payment Amount
 * @return participantInfo array with 'Success' flag
 */
function paymentui_civicrm_process_partial_payments($paymentParams, $participantInfo) {
  //Iterate through participant info
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
