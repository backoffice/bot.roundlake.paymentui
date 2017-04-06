This Extension is a combination of:

+ https://github.com/backoffice/BOT-Partial-Payment-Extension
+ https://github.com/aghstrategies/bot.roundlake.paymentui

modified to work with civicrm 4.7

Differences between this extension and Backoffice Originals:

+ Uses an api call to get related contacts and only gets parent/child relationships
+ Uses id 15 instead of 14 for partial payments
+ Uses the default payment processor


Note to create a partially paid event registration: Register user on the backend and edit the amount under payment Information to be less than the Total Fees
