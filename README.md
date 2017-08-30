This Extension is a combination of:

+ https://github.com/backoffice/BOT-Partial-Payment-Extension
+ https://github.com/backoffice/bot.roundlake.paymentui

modified to work with civicrm 4.7

Differences between this extension and Backoffice Originals:

+ Uses an api call to get related contacts and only gets parent/child relationships
+ Uses id 15 instead of 14 for partial payments
+ Uses the default payment processor
+ Creates a custom field to hold late fees and adds late fees according to the parsed late fee schedule
+ Creates a token of the table of partially paid event registrations for the contact and anyone they have a child of relationship to
+ Adds a credit card processing fee of 2% to all payments
+ Creates a settings page: civicrm/paymentui/feessettings where one can set the processing fee and the late fee
Note to create a partially paid event registration: Register user on the backend and edit the amount under payment Information to be less than the Total Fees

Added Features:

+ Token: 'Table of Partial Payment Information' that generates the table the user sees when on the payment page as a token to be used in emails.
+ Custom Field for late fee schedule "Event Late Fees"
+ Setting page for late fee and processing fee amounts
+ Sets default amount paid input text to be the amount owed if late plus the amount for the next payment

## Create a Partially Paid Event registration

This must be done on the backend. Go to a contact, add an event registration with a status of "partially paid" and enter into the record payment amount an amount less than the total. Visit: https://ymcaga.org/index.php?option=com_civicrm&task=civicrm/addpayment logged in on the front end as the contact you created a partially paid event registration for. you should see the cost being the payment option amount. Paid to Date being the amount entered into the record payment box. $$ remaining being the difference.

See Screenshots regarding this process in img folder.

For additional tests regarding late fees and processing fees see tests file.
