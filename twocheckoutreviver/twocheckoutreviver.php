<?php
/*
* The MIT License
* 
* Copyright (c) 2017 - Kiril Griazev
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*/

if (!defined('_PS_VERSION_'))
    exit;

class Twocheckoutreviver extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'twocheckoutreviver';
        $this->displayName = '2Checkout Payment API';
        $this->tab = 'payments_gateways';
        $this->author = 'Reviver.lt';
        $this->version = 1.0;


        $config = Configuration::getMultiple(array('TWOCHECKOUT_SID', 'TWOCHECKOUT_PUBLIC', 'TWOCHECKOUT_PRIVATE',
            'TWOCHECKOUT_SANDBOX', 'TWOCHECKOUT_CURRENCIES'));

        if (isset($config['TWOCHECKOUT_SID']))
            $this->SID = $config['TWOCHECKOUT_SID'];
        if (isset($config['TWOCHECKOUT_PUBLIC']))
            $this->PUBLIC = $config['TWOCHECKOUT_PUBLIC'];
        if (isset($config['TWOCHECKOUT_PRIVATE']))
            $this->PRIVATE = $config['TWOCHECKOUT_PRIVATE'];
        if (isset($config['TWOCHECKOUT_SANDBOX']))
            $this->SANDBOX = $config['TWOCHECKOUT_SANDBOX'];
        if (isset($config['TWOCHECKOUT_CURRENCIES']))
            $this->currencies = $config['TWOCHECKOUT_CURRENCIES'];

        parent::__construct();

        /* The parent construct is required for translations */
        $this->page = basename(__FILE__, '.php');
        $this->description = $this->l('Accept payments using 2Checkout Payment API');

        if (!isset($this->SID) OR !isset($this->currencies))
            $this->warning = $this->l('your 2Checkout vendor account number must be configured in order to use this module correctly');

        if (!Configuration::get('TWOCHECKOUT_CURRENCIES'))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                    $authorized_currencies[] = $currency['id_currency'];
            Configuration::updateValue('TWOCHECKOUT_CURRENCIES', implode(',', $authorized_currencies));
        }
    }


    function install()
    {
        //Call PaymentModule default install function
        $install = parent::install() && $this->registerHook('payment') && $this->registerHook('header');
        //Create Valid Currencies
        $currencies = Currency::getCurrencies();
        $authorized_currencies = array();
        foreach ($currencies as $currency)
        $authorized_currencies[] = $currency['id_currency'];
        Configuration::updateValue('TWOCHECKOUT_CURRENCIES', implode(',', $authorized_currencies));
        $this->registerHook('displayMobileHeader');
        return $install;
    }


    function uninstall()
    {
        Configuration::deleteByName('TWOCHECKOUT_SID');
        Configuration::deleteByName('TWOCHECKOUT_PUBLIC');
        Configuration::deleteByName('TWOCHECKOUT_PRIVATE');
        Configuration::deleteByName('TWOCHECKOUT_SANDBOX');
        Configuration::deleteByName('TWOCHECKOUT_CURRENCIES');
        return $this->unregisterHook('payment') && $this->unregisterHook('header') && $this->unregisterHook('displayMobileHeader') && parent::uninstall();
    }


    public function hookDisplayMobileHeader()
    {
        return $this->hookHeader();
    }


    public function hookHeader()
    {
        if (Tools::getValue('controller') != 'order-opc' && Tools::getValue('controller') != 'payment' && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order.php' || $_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order-opc.php' || Tools::getValue('controller') == 'order' || Tools::getValue('controller') == 'orderopc' || Tools::getValue('step') == 3)))
            return;

        if (Configuration::get('TWOCHECKOUT_SANDBOX')) {
            $output = '<script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID').'"></script>';
        } else {
            $output = '
            <script type="text/javascript" src="https://www.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID').'"></script>';
        }

        $this->smarty->assign('twocheckout_sid', Configuration::get('TWOCHECKOUT_SID'));
        $this->smarty->assign('twocheckout_public_key', Configuration::get('TWOCHECKOUT_PUBLIC'));

        return $output;
    }


    function getContent()
    {
        if (isset($_POST['btnSubmit']))
        {
            Configuration::updateValue('TWOCHECKOUT_SID', $_POST['SID']);
            Configuration::updateValue('TWOCHECKOUT_PUBLIC', $_POST['PUBLIC']);
            Configuration::updateValue('TWOCHECKOUT_PRIVATE', $_POST['PRIVATE']);
            Configuration::updateValue('TWOCHECKOUT_SANDBOX', $_POST['SANDBOX']);
        }
        elseif (isset($_POST['currenciesSubmit']))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
                    $authorized_currencies[] = $currency['id_currency'];
            Configuration::updateValue('TWOCHECKOUT_CURRENCIES', implode(',', $authorized_currencies));
        }

        $this->_html = '<h2>'.$this->displayName.'</h2>';

        if (!empty($_POST))
        {
            $this->_postValidation();
            if (!sizeof($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors AS $err)
                    $this->_html .= "<div class='alert error'>{$err}</div>";
        }
        else
        {
            $this->_html .= "<br />";
        }

        $this->_displaycheckout();
        $this->_displayForm();

        return $this->_html;
    }
    
    function hookPayment($params)
    {
        global $smarty;
        $smarty->assign(array(
        'this_path' 		=> $this->_path,
        'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

        return $this->display(__FILE__, 'payment.tpl');
    }

    private function _postValidation()
    {
        if (isset($_POST['btnSubmit']))
        {
            if (empty($_POST['SID']))
                $this->_postErrors[] = $this->l('Your 2Checkout account number is required.');
        }
        elseif (isset($_POST['currenciesSubmit']))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
                    $authorized_currencies[] = $currency['id_currency'];
                if (!sizeof($authorized_currencies))
                    $this->_postErrors[] = $this->l('at least one currency is required.');
        }
    }


    private function _postProcess()
    {
        $ok = $this->l('Ok');
        $updated = $this->l('Settings Updated');
        $this->_html .= "<div class='conf confirm'><img src='../img/admin/ok.gif' alt='{$ok}' />{$updated}</div>";
    }

    private function _displaycheckout()
    {
        $modDesc 	= $this->l('This module allows you to accept payments using 2Checkout\'s Payment API services.');
        $modStatus	= $this->l('2Checkout\'s online payment service could be the right solution for you');
        $modconfirm	= $this->l('');
        $this->_html .= "<img src='../modules/checkout/2Checkout.gif' style='float:left; margin-right:15px;' />
                                        <b>{$modDesc}</b>
                                        <br />
                                        <br />
                                        {$modStatus}
                                        <br />
                                        {$modconfirm}
                                        <br />
                                        <br />
                                        <br />";
    }




    private function _displayForm()
    {
        $modcheckout	            = $this->l('2Checkout Setup');
        $modcheckoutDesc	        = $this->l('Please specify the 2Checkout account number and secret word.');
        $modClientLabelSid	        = $this->l('2Checkout Account Number');
        $modClientValueSid	        = Configuration::get('TWOCHECKOUT_SID');
        $modClientLabelPublic	    = $this->l('Publishable Key');
        $modClientValuePublic	    = Configuration::get('TWOCHECKOUT_PUBLIC');
        $modClientLabelPrivate	    = $this->l('Private Key');
        $modClientValuePrivate	    = Configuration::get('TWOCHECKOUT_PRIVATE');
        $modClientLabelSandbox      = $this->l('Use Sandbox?');
        $modClientValueSandbox      = Configuration::get('TWOCHECKOUT_SANDBOX');
        $modCurrencies		        = $this->l('Currencies');
        $modUpdateSettings 	        = $this->l('Update settings');
        $modCurrenciesDescription   = $this->l('Currencies authorized for 2Checkout payment');
        $modAuthorizedCurrencies    = $this->l('Authorized currencies');
        $this->_html .=
        "
        <br />
        <br />
        <p><form action='{$_SERVER['REQUEST_URI']}' method='post'>
                <fieldset>
                <legend><img src='../img/admin/access.png' />{$modcheckout}</legend>
                        <table border='0' width='500' cellpadding='0' cellspacing='0' id='form'>
                                <tr>
                                        <td colspan='2'>
                                                {$modcheckoutDesc}<br /><br />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelSid}</td>
                                        <td>
                                                <input type='text' name='SID' value='{$modClientValueSid}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelPublic}</td>
                                        <td>
                                                <input type='text' name='PUBLIC' value='{$modClientValuePublic}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelPrivate}</td>
                                        <td>
                                                <input type='text' name='PRIVATE' value='{$modClientValuePrivate}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelSandbox}</td>
                                        <td>
                                            <input type='radio' name='SANDBOX' value='0'".(!$modClientValueSandbox ? " checked='checked'" : '')." /> No
                                            <br />
                                            <input type='radio' name='SANDBOX' value='1'".($modClientValueSandbox ? " checked='checked'" : '')." /> Yes
                                            <br />
                                        </td>
                                </tr>
                                <tr>
                                        <td colspan='2' align='center'>
                                                <input class='button' name='btnSubmit' value='{$modUpdateSettings}' type='submit' />
                                        </td>
                                </tr>
                        </table>
                </fieldset>
        </form>
        </p>
        <br />
        <br />
        <form action='{$_SERVER['REQUEST_URI']}' method='post'>
                <fieldset>
                <legend>{$modAuthorizedCurrencies}</legend>
                        <table border='0' width='500' cellpadding='0' cellspacing='0' id='form'>
                                <tr>
                                        <td colspan='2'>
                                                {$modCurrenciesDescription}
                                                <br />
                                                <br />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130' style='height: 35px; vertical-align:top'>{$modCurrencies}</td>
                                        <td>";
                $currencies = Currency::getCurrencies();
                $authorized_currencies = array_flip(explode(',', Configuration::get('TWOCHECKOUT_CURRENCIES')));
                foreach ($currencies as $currency)
                    $this->_html .= '<label style="float:none; "><input type="checkbox" value="true" name="currency_'.$currency['id_currency'].'"'.(isset($authorized_currencies[$currency['id_currency']]) ? ' checked="checked"' : '').' />&nbsp;<span style="font-weight:bold;">'.$currency['name'].'</span> ('.$currency['sign'].')</label><br />';
                    $this->_html .="
                                        </td>
                                </tr>
                                <tr>
                                        <td colspan='2' align='center'>
                                                <br />
                                                <input class='button' name='currenciesSubmit' value='{$modUpdateSettings}' type='submit' />
                                        </td>
                                </tr>
                        </table>
                </fieldset>
        </form>";
    }
}

?>
