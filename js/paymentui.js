CRM.$(function ($) {
  $.each($('table.partialPaymentInfo tr'), function () {
    if ($('td.balance', this).text() == '$ 0.00') {
      $('td.payment', this).css('visibility', 'hidden');
    }
  });

  var calculateTotal = function () {
    var total = 0;
    $.each($("input[name^='payment']"), function () {
      var amt = $(this).val();
      if ($.isNumeric(amt)) {
        total = parseFloat(total) + parseFloat(amt);
      }
    });

    var creditcardfees = (total * CRM.vars.paymentui.processingFee / 100).toFixed(2);
    var latefees = 0;
    if (parseFloat($('#latefees').html()) > 0) {
      latefees = $('#latefees').html();
    }

    document.getElementById('creditCardFees').innerHTML = creditcardfees;
    total = (parseFloat(total) + parseFloat(creditcardfees) + parseFloat(latefees)).toFixed(2);
    document.getElementById('total').innerHTML = total;
  };

  $("input[id^='payment']").keyup(function () {
    calculateTotal();
  });

  calculateTotal();
});
