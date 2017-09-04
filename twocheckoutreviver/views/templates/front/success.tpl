<div class="row">
    <div class="col-xs-12" style="padding-bottom: 20px;margin-bottom: 20px;border-bottom: 1px solid #ccc;text-transform: none;font-size: 17px;">
        {$HOOK_ORDER_CONFIRMATION}
	    {$HOOK_PAYMENT_RETURN}
        <h3 style="margin-bottom: 20px;margin-top:5px;">{l s='Thank You! Your order has been successfully placed' mod='twocheckoutreviver'}</h3>
        <p>{l s='Order reference' mod='twocheckoutreviver'}: <strong>{$reference}</strong></p>
        <p>{l s='You will also receive order details via email' mod='twocheckoutreviver'}</p>
    </div>
</div>