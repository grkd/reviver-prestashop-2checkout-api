<?php

class TwoCheckoutReviverPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function initContent()
	{
		parent::initContent();
        
        $cart = $this->context->cart;
        
        if($cart->id == ''){
            Tools::redirect('index.php');
        }
        
        $this->context->smarty->assign(array(
            'twocheckout_sid' => Configuration::get('TWOCHECKOUT_SID'),
            'twocheckout_public_key' => Configuration::get('TWOCHECKOUT_PUBLIC'),
            'authfailed' => Tools::getValue('authfailed')
		));

		$this->setTemplate('payment_execution.tpl');
	}
}
