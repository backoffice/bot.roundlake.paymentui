<?php

require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/Error.php';
/**
 * Form controller class
 */
class CRM_Paymentui_Form_Paymentui extends CRM_Core_Form {

public $_params;

  /**
   * Function to set variables up before form is built
   *
   * @return void
   * @access public
   */
  public function preProcess() {
	$this->_paymentProcessor = array('billing_mode' => 1);
	$locationTypes = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'location_type_id', array(), 'validate');
	$this->_bltID = array_search('Billing', $locationTypes);
	$this->set('bltID', $this->_bltID);
	$this->assign('bltID', $this->_bltID);
	$this->_fields = array();

	//Gets the live default payment processor, if not found displays an error
    try {
	  $ppResult = civicrm_api3('PaymentProcessor', 'get', array(
	    'sequential' => 1,
	    'return' => "id,name,payment_processor_type_id",
	    'is_default' => 1,
	    'is_active' => 1,
	    'is_test' => 0,
    	));
    }
	catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
	  CRM_Core_Error::fatal("Payment Processor is not set for processing the payment. Please contact the system administrator");
    }
	
	if ( !$ppResult['error'] ) {
		$this->payment_processor_id = $ppResult['id'];
	} else {
		//CRM_Core_Error::fatal("Payment Processor is not set for processing the payment. Please contact the system administrator");
		$message = ts ('Payment Processor is not set for processing the payment. Please contact the system administrator.');
		CRM_Utils_System::setUFMessage($message);		
		return;
	}
	$this->_paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment( $this->payment_processor_id, 'live' );

	CRM_Core_Payment_Form::setPaymentFieldsByProcessor($this, $this->_paymentProcessor);
	$this->assign_by_ref('paymentProcessor', $paymentProcessor);
	$this->assign('hidePayPalExpress', TRUE);
  }
	
  /**
   * Function to build the form
   *
   * @return void
   * @access public
   */	
  public function buildQuickForm() {
	//Get contact name of the logged in user
	$session     = CRM_Core_Session::singleton();
	$this->_contactId   = $session->get( 'userID' );
	
	if ( !$this->_contactId ) {
		$message = ts ('You are not authorized to view this page');
		CRM_Utils_System::setUFMessage($message);		
		return;
	}
	$this->assign('contactId', $this->_contactId);
	$displayName = CRM_Contact_BAO_Contact::displayName( $this->_contactId);
	$this->assign('displayName', $displayName );
	
	//Set column headers for the table 
	$columnHeaders = array('Event', 'Registrant', 'Cost', 'Paid to Date', '$$ remaining', 'Make Payment');
	$this->assign('columnHeaders', $columnHeaders );
	
	//Get event names for which logged in user and the related contacts are registered
	$this->_participantInfo = CRM_Paymentui_BAO_Paymentui::getParticipantInfo( $this->_contactId );
	$this->assign('participantInfo', $this->_participantInfo );
	$totalAmount = 0;
	if ( $this->_participantInfo ) {
		foreach ( $this->_participantInfo as $pid => $pInfo ) {
			$totalAmount += $pInfo['total_amount'];
			$element =& $this->add( 'text', "payment[$pid]", null, array( 'onblur' => 'calculateTotal();' ),	false);
	
	
		}
	}

	$element =& $this->add( 'text', "totalAmount", $totalAmount,	false);
	$this->assign('totalAmount', $totalAmount);

    //BOT commented out due to issues with CiviCRM 5.x and PHP 7
	//CRM_Contribute_Form_ContributionBase::assignToTemplate();
	CRM_Core_Payment_Form::buildPaymentForm($this, $this->_paymentProcessor, FALSE, FALSE);	
    
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    )); 

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
	$this->addFormRule(array('CRM_Paymentui_Form_Paymentui', 'formRule'), $this);
  }

   /**
   * global form rule
   *
   * @param array $fields the input form values
   * @param array $files the uploaded files if any
   * @param $self
   *
   * @internal param array $options additional user data
   *
   * @return true if no errors, else array of errors
   * @access public
   * @static
   */
  function formRule($fields, $files, $self) {
	$errors = array();
	//Validate the amount: should not be more than balance and should be numeric
	foreach ( $fields['payment'] as $pid => $amount ) {
		if ( $amount ) {
			if ( $self->_participantInfo[$pid]['balance'] < $amount ) {
				$errors['payment['.$pid.']'] = "Amount can not exceed the balance amount";
			}
			if ( !is_numeric( $amount ) ) {
				$errors['payment['.$pid.']'] = "Please enter a valid amount";
			}
		}
	}
	//Validate credit card fields
	$required = array(
		'credit_card_type'   => 'Credit Card Type',
		'credit_card_number' => 'Credit Card Number',
		'cvv2'               => 'CVV',
		'billing_first_name' => 'Billing First Name',
		'billing_last_name' => 'Billing Last Name',
		'billing_street_address-5'  => 'Billing Street Address',
		'billing_city-5' => 'City',
		'billing_state_province_id-5' => 'State Province',
		'billing_postal_code-5' => 'Postal Code',
		'billing_country_id-5' => 'Country'
    );
	
	foreach ( $required as $name => $fld) {
	  if ( !$fields[$name] ) {
		$errors[$name] = ts('%1 is a required field.', array(1 => $fld) );
	   
	  }
	}
	CRM_Core_Payment_Form::validateCreditCard($fields, $errors);
	return $errors;
  }
 
   /**
   * Function to process the form
   *
   * @access public
   *
   * @return void
   */
  public function postProcess() {
    $values = $this->exportValues();
	$this->_params = $this->controller->exportValues( $this->_name ); 
	$totalAmount = 0;
	$config = CRM_Core_Config::singleton();
	
	//Calculate total amount paid and individual amount for each contribution
	foreach ( $this->_params['payment'] as $pid => $pVal ) {
		$totalAmount += $pVal;
		$this->_participantInfo[$pid]['partial_payment_pay'] = $pVal;
	}
	//Building params for CC processing
	$this->_params["state_province-{$this->_bltID}"] = $this->_params["billing_state_province-{$this->_bltID}"] = CRM_Core_PseudoConstant::stateProvinceAbbreviation($this->_params["billing_state_province_id-{$this->_bltID}"]);
	
	$this->_params["country-{$this->_bltID}"]        = $this->_params["billing_country-{$this->_bltID}"] = CRM_Core_PseudoConstant::countryIsoCode($this->_params["billing_country_id-{$this->_bltID}"]);

	$this->_params['year']           = CRM_Core_Payment_Form::getCreditCardExpirationYear($this->_params);
	$this->_params['month']          = CRM_Core_Payment_Form::getCreditCardExpirationMonth($this->_params);
	$this->_params['ip_address']     = CRM_Utils_System::ipAddress();
	$this->_params['amount']         = $totalAmount;	  
	$this->_params['amount_level']   = $params['amount_level'];
	$this->_params['currencyID']     = $config->defaultCurrency;
	$this->_params['payment_action'] = 'Sale';
	$this->_params['invoiceID']      = md5(uniqid(rand(), TRUE));
 
	$paymentParams = $this->_params;
    
	//singleton function is not there anymore in specified file in version 5
	//$payment = CRM_Core_Payment::singleton($this->_mode, $this->_paymentProcessor, $this);
	//added this below line to get payment processor info
 	$payment = Civi\Payment\System::singleton()->getByProcessor($this->_paymentProcessor);
	$result  = $payment->doPayment($paymentParams);
        //CRM_Core_Error::debug_var("resultCodeFromExtension", "<pre>" . print_r($result,TRUE) . "</pre>");   
        //CRM_Core_Error::debug_var("madness", "yep");
 
     // if( isset($result['trxn_id']) && !empty($result['trxn_id']) ) {
        if ( is_array($result) && isset($result['trxn_id']) && !empty($result['trxn_id'])  ) {

          ///CRM_Core_Error::debug_var("shouldNotSeeThis", $statusMsg);

          //Create financial transaction and entity financial transaction records
         $CCFinancialTrxn = CRM_Paymentui_BAO_Paymentui::createFinancialTrxn( $paymentParams, $this->payment_processor_id );
  
	  $partialPaymentInfo = $this->_participantInfo;
    
    //Process all the partial payments and update the records
    //Function defined in bot.partial.payment extension - payment.php
 	  payment_civicrm_process_partial_payments( $paymentParams, $this->_participantInfo );
	  parent::postProcess();
  
	 //Define status message
	 $statusMsg = ts('The payment(s) have been processed successfully.');
    	CRM_Core_Session::setStatus($statusMsg, ts('Saved'), 'success');
    } else {
  	$statusMsg = ts('Sorry, an error occurred while processing the transaction. Please try again.');
        //CRM_Core_Error::debug_var("didwegethere", "thishastoShow");
    	CRM_Core_Session::setStatus(ts('Sorry, an error occurred while processing the transaction. Please try again.'), ts('Error'), 'error');
    }
  

        //debug code for storing payment results to error log, some payments failing at payment processor but no error message and civi thinks payment succeeded
        //Debug var of payment params
        //unset cvv and credit card number
        //unset($result['cvv2']);
        //unset($result['credit_card_number']);

	//Redirect to the same URL
	$url     = CRM_Utils_System::url('civicrm/roundlake/add/payment', "reset=1" ); 
	$session = CRM_Core_Session::singleton();
	CRM_Utils_System::redirect( $url );	 
  }



  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
