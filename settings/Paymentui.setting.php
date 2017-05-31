<?php
/**
 * @file
 * Settings metadata for bot.roundlake.paymentui.
 * Copyright (C) 2016, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */
return array(
  'paymentui_processingfee' => array(
    'group_name' => 'Custom Styling for Civi Contribution Pages',
    'group' => 'paymentui',
    'name' => 'paymentui_processingfee',
    'type' => 'Integer',
    'default' => NULL,
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Processing Fee for Partially Paid Registrations',
    'help_text' => 'a 4% processing fee should be entered as 4',
  ),
  'paymentui_latefee' => array(
    'group_name' => 'Custom Styling for Civi Contribution Pages',
    'group' => 'paymentui',
    'name' => 'paymentui_latefee',
    'type' => 'Integer',
    'default' => NULL,
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Late Fee to be applied to payments made against late Partially Paid Event Registration',
    'help_text' => 'The payment schedule is held in the Event Late Fees custom field',
  ),
  'paymentui_processor' => array(
    'group_name' => 'Payment Processor to use for Partial Payment Page',
    'group' => 'paymentui',
    'name' => 'paymentui_processor',
    'type' => 'Integer',
    'default' => NULL,
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Payment Processor to use for Partial Payment page',
    'help_text' => 'Select One',
  ),
);
