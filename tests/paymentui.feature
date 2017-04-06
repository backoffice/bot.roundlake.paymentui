Scenario: Making a Partial Payment

When a logged in user visits http://dev.ymcaga.org/index.php?option=com_civicrm&task=civicrm/roundlake/add/payment&reset=1
And they or a contact with a child of relationship to them have a partial payment for an event each paritally paid event will show up with the event, reigstraant cost, paid to date, $$ remaining and a box to make a payment
And if they enter an amount to the make payment box for any line the total amounts entered are added together and displaed on the total line
And if they enter an amount greater than $$ remaining the form will throw an error
And they can enter their credit card information to make a payment against the $$ remaining
Then if they pay the $$ remaining that partial payment will become paid and no longer show up and if they still owe it will continue to show up with the updated $$ remaining and paid to data amounts.
