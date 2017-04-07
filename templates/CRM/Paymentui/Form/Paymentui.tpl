{* HEADER *}
<div class="form-item">
	<fieldset>
	<legend>{ts}{$displayName}{/ts}</legend> 
		<table class=" form-layout">		
			<tr>
				<td colspan="2">
					<table class="form-layout">   
						<thead class="sticky"> 
							{foreach from=$columnHeaders item=header}							  
								<th scope="col"><strong>{$header}</strong></th>
							{/foreach}
						</thead>
						{foreach from=$participantInfo item=row}
							<tr class="{$row.rowClass}">
								<td class="">{$row.event_name}</td>
								<td class="">{$row.contact_name}</td>
								<td class="">{$row.total_amount|crmMoney}</td>
								<td class="">{$row.paid|crmMoney}</td>
								<td class="">{$row.balance|crmMoney}</td>				

								<td class="">{$form.payment[$row.pid].html|crmMoney}</td>
								
							</tr>
						{/foreach}
						{if $contactId}
						<thead class="sticky">  
									<td colspan = 5 scope="col"><strong>Total</strong></th>
									<td class="font-size12pt "><span>$ </span><span name='total' id ='total'>0</span></td>                   
						</thead>
						{/if}
					</table>
				</td>
			</tr>	
		</table>						
	</fieldset>							
</div>

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}
{include file="CRM/Core/BillingBlock.tpl" context="front-end"}
{if $form.payment_processor.label}
  {* PP selection only works with JS enabled, so we hide it initially *}
  <fieldset class="crm-group payment_options-group" style="display:none;">
    <legend>{ts}Payment Options{/ts}</legend>
    <div class="crm-section payment_processor-section">
      <div class="label">{$form.payment_processor.label}</div>
      <div class="content">{$form.payment_processor.html}</div>
      <div class="clear"></div>
    </div>
  </fieldset>
  {/if}

  {if $is_pay_later}
  <fieldset class="crm-group pay_later-group">
    <legend>{ts}Payment Options{/ts}</legend>
    <div class="crm-section pay_later_receipt-section">
      <div class="label">&nbsp;</div>
      <div class="content">
        [x] {$pay_later_text}
      </div>
      <div class="clear"></div>
    </div>
  </fieldset>
  {/if}

  <div id="billing-payment-block">
    {* If we have a payment processor, load it - otherwise it happens via ajax *}
    {if $ppType}
      {include file="CRM/Contribute/Form/Contribution/Main.tpl" snippet=4}
    {/if}
  </div>
  {include file="CRM/common/paymentBlock.tpl"}

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{literal}

<script type="text/javascript">
function calculateTotal() {
	var total = 0.00;
	cj.each(cj( "input[name^='payment']" ), function() { 
		var amt = cj(this).val();
		if ( cj.isNumeric(amt) ) {
			total = parseFloat(total)+parseFloat(amt);
		}
	});
	total = Math.round(total*100, 2)/100;
    total.toFixed(2);
	
	document.getElementById('total').innerHTML = total;		
}
calculateTotal();
</script>
{/literal}
