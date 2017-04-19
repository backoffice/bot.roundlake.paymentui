<?php

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Paymentui_Form_FeesSettings extends CRM_Core_Form {
  public function buildQuickForm() {

    // add form elements
    $this->add(
      // field type
      'text',
      // field name
      'processing_fee',
      // field label
      'Processing Fee'
    );
    // add form elements
    $this->add(
      // field type
      'text',
      // field name
      'late_fee',
      // field label
      'Late Fee'
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    // Set Defaults
    $defaults = array();
    try {
      $existingSetting = civicrm_api3('Setting', 'get', array(
        'sequential' => 1,
        'return' => array("paymentui_processingfee", "paymentui_latefee"),
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(t('API Error: %1', array(1 => $error, 'domain' => 'bot.roundlake.paymentui')));
    }
    if (!empty($existingSetting['values'][0]['paymentui_processingfee'])) {
      $defaults['processing_fee'] = $existingSetting['values'][0]['paymentui_processingfee'];
    }
    if (!empty($existingSetting['values'][0]['paymentui_latefee'])) {
      $defaults['late_fee'] = $existingSetting['values'][0]['paymentui_latefee'];
    }
    $this->setDefaults($defaults);
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    $params = array();
    if (!empty($values['processing_fee'])) {
      $params['paymentui_processingfee'] = $values['processing_fee'];
    }
    if (!empty($values['processing_fee'])) {
      $params['paymentui_latefee'] = $values['late_fee'];
    }
    if (!empty($params)) {
      try {
        $existingSetting = civicrm_api3('Setting', 'create', $params);
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Error::debug_log_message(
          t('API Error: %1', array(1 => $error, 'domain' => 'bot.roundlake.paymentui'))
        );
      }
      CRM_Core_Session::setStatus(ts('The Processing fee is now set to "%1"', array(
        1 => $values['processing_fee'],
      )));
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
