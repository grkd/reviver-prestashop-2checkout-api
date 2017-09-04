<?php

class TwoCheckoutReviverValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        if (!defined('_PS_VERSION_'))
            exit;

        $twocheckout = new Twocheckoutreviver();
        $this->context = Context::getContext();

        if (isset($_POST['token'])){

            include(dirname(dirname(dirname(__FILE__))).'/lib/Twocheckout/TwocheckoutApi.php');

            $token = $_POST['token'];

            $cart = $this->context->cart;
            $user = $this->context->customer;
            $delivery = new Address(intval($cart->id_address_delivery));
            $invoice = new Address(intval($cart->id_address_invoice));
            $customer = new Customer(intval($cart->id_customer));
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array_flip(explode(',', Configuration::get('TWOCHECKOUT_CURRENCIES')));
            $currencies_used = array();
            foreach ($currencies as $key => $currency) {
                if (isset($authorized_currencies[$currency['id_currency']])) {
                    $currencies_used[] = $currencies[$key];
                }
            }
            foreach ($currencies_used as $currency) {
                if ($currency['id_currency'] == $cart->id_currency) {
                    $order_currency = $currency['iso_code'];
                }
            }

            try {

                $params = array(
                    "sellerId" => Configuration::get('TWOCHECKOUT_SID'),
                    "merchantOrderId" => $cart->id,
                    "token"      => $token,
                    "currency"   => $order_currency,
                    "total"      => number_format($cart->getOrderTotal(true, 3), 2, '.', ''),
                    "billingAddr" => array(
                    "name" => $invoice->firstname . ' ' . $invoice->lastname,
                    "addrLine1" => $invoice->address1,
                    "addrLine2" => $invoice->address2,
                    "city" => $invoice->city,
                    "state" => ($invoice->country == "United States" || $invoice->country == "Canada") ? State::getNameById($invoice->id_state) : 'XX',
                    "zipCode" => $invoice->postcode,
                    "country" => $invoice->country,
                    "email" => $customer->email,
                    "phoneNumber" => $invoice->phone
                )
                );

                if ($delivery) {
                    $shippingAddr = array(
                        "name" => $delivery->firstname . ' ' . $delivery->lastname,
                        "addrLine1" => $delivery->address1,
                        "addrLine2" => $delivery->address2,
                        "city" => $delivery->city,
                        "state" => (Validate::isLoadedObject($delivery) AND $delivery->id_state) ? new State(intval($delivery->id_state)) : 'XX',
                        "zipCode" => $delivery->postcode,
                        "country" => $delivery->country
                    );
                    array_merge($shippingAddr, $params);
                }

                if (Configuration::get('TWOCHECKOUT_SANDBOX')) {
                    TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'), 'sandbox');
                } else {
                    TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'));
                }
                $charge = Twocheckout_Charge::auth($params);

            } catch (Twocheckout_Error $e) {
                $message = 'Payment Authorization Failed';
                Tools::redirect($this->context->link->getModuleLink('twocheckoutreviver','payment',array('authfailed'=>'show')));
                Tools::redirect('index.php?controller=order&step=3&twocheckouterror='.$message);
            }

            if (isset($charge['response']['responseCode'])) {
                $order_status = (int)Configuration::get('TWOCHECKOUT_ORDER_STATUS');
                $message = $charge['response']['responseMsg'];
                $this->module->validateOrder((int)$cart->id, _PS_OS_PAYMENT_, $charge['response']['total'], $this->module->displayName, $message, array(), null, false, $this->context->customer->secure_key);
                
                $order = Order::getOrderByCartId((int)($cart->id));
                $order = new Order($order);

                $this->context->smarty->assign(
                    array(
                    'is_guest' => (($this->context->customer->is_guest) || $this->context->customer->id == false),
                    'order' => $this->module->currentOrder,
                    'HOOK_ORDER_CONFIRMATION' => $this->displayOrderConfirmationRvr(),
                    'HOOK_PAYMENT_RETURN' => $this->displayPaymentReturnRvr(),
                    'reference' => $order->reference
                )
                );
                if (version_compare(_PS_VERSION_, '1.5', '>')) {
                    $this->context->smarty->assign(array(
                        'reference_order' => Order::getUniqReferenceOf($this->module->currentOrder),
                    ));
                }

                if (($this->context->customer->is_guest) || $this->context->customer->id == false) {
                    $this->context->smarty->assign(
                        array(
                        'cartid' => $cart->id,
                        'cartid2' => $this->context->cart->id,
                        'key' => $this->context->customer->secure_key,
                        'id_order' => (int) $this->module->currentOrder,
                        'id_order_formatted' => sprintf('#%06d', (int) $this->module->currentOrder),
                    )
                    );

                    /* If guest we clear the cookie for security reason */
                    $this->context->customer->mylogout();
                }

                $this->setTemplate('success.tpl');
                
            } else {
                $message = 'Payment Authorization Failed';
                Tools::redirect('index.php');
                Tools::redirect($this->context->link->getModuleLink('twocheckoutreviver','payment',array('authfailed'=>'show')));
            }



        }else{
            Tools::redirect('index.php');
        }


    }

    private function displayHookRvr()
    {
        if (Validate::isUnsignedId($this->module->currentOrder) && Validate::isUnsignedId($this->module->id)) {
            $order = new Order((int) $this->module->currentOrder);
            $currency = new Currency((int) $order->id_currency);

            if (Validate::isLoadedObject($order)) {
                $params = array();
                $params['objOrder'] = $order;
                $params['currencyObj'] = $currency;
                $params['currency'] = $currency->sign;
                $params['total_to_pay'] = $order->getOrdersTotalPaid();

                return $params;
            }
        }

        return false;
    }

    public function displayPaymentReturnRvr()
    {
        $params = $this->displayHookRvr();

        if ($params && is_array($params)) {
            return Hook::exec('displayPaymentReturn', $params, (int) $this->module->id);
        }

        return false;
    }

    /**
     * Execute the hook displayOrderConfirmation
     */
    public function displayOrderConfirmationRvr()
    {
        $params = $this->displayHookRvr();
        if ($params && is_array($params)) {
            return Hook::exec('displayOrderConfirmation', $params);
        }

        return false;
    }
}

