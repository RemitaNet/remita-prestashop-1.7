<?php
/**
 * Plugin Name: Remita Prestashop Payment Gateway
 * Plugin URI:  https://www.remita.net
 * Description: Remita Prestashop Payment gateway allows you to accept payment on your Prestashop store via Visa Cards, Mastercards, Verve Cards, eTranzact, PocketMoni, Paga, Internet Banking, Bank Branch and Remita Account Transfer.
 * Author:      SystemSpecs Limited
 * Version:     2.0
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Remita extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;
    
    public function __construct()
    {
        $this->name = 'remita';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Remita';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('REMITA_SECRETKEY','REMITA_PUBLICKEY','REMITA_MODE'));
     
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Remita', array(), 'Modules.Remita.Admin');
        $this->description = $this->trans('This module allows you to accept payments from CreditCard, Bank Internet Site & Bank Branches.', array(), 'Modules.Remita.Admin');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.Remita.Admin');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        $this->extra_mail_vars = array(
            '{remita_owner}' => Configuration::get('REMITA_USERNAME'),
            '{remita_details}' => nl2br(Configuration::get('REMITA_DETAILS')),
            //'{remita_address}' => nl2br(Configuration::get('REMITA_ADDRESS'))
        );

    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions') || !$this->registerHook('header')) {
            return false;
        }

        $newState = new OrderState();

        $newState->send_email = true;
        $newState->module_name = $this->name;
        $newState->invoice = true;
        $newState->color = "#04b404";
        $newState->unremovable = false;
        $newState->logable = true;
        $newState->delivery = false;
        $newState->hidden = false;
        $newState->shipped = false;
        $newState->paid = true;
        $newState->delete = false;

        $languages = Language::getLanguages(true);
        foreach ($languages as $lang) {
            if ($lang['iso_code'] == 'id') {
                $newState->name[(int)$lang['id_lang']] = 'Menunggu pembayaran via Remita';
            } else {
                $newState->name[(int)$lang['id_lang']] = 'Payment successful';
            }
            $newState->template = "remita";
        }


        if ($newState->add()) {
            Configuration::updateValue('PS_OS_REMITA', $newState->id);
            copy(dirname(__FILE__).'/logo.png', _PS_IMG_DIR_.'tmp/order_state_mini_'.(int)$newState->id.'_1.png');
        } else {
            return false;
        }

        return true;
    }

    public function uninstall()
    {

        if (!Configuration::deleteByName('REMITA_SECRETKEY')
            || !Configuration::deleteByName('REMITA_PUBLICKEY')
            || !parent::uninstall()
        ) {
            return false;
        }
        return true;
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('REMITA_SECRETKEY', Tools::getValue('REMITA_SECRETKEY'));
            Configuration::updateValue('REMITA_PUBLICKEY', Tools::getValue('REMITA_PUBLICKEY'));
            Configuration::updateValue('REMITA_MODE', Tools::getValue('REMITA_MODE'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    private function _displayRemita()
    {
        return $this->display(__FILE__, 'paymentinfos.tpl');
    }

    public function addJsRC($js_uri)
    {
        $this->context->controller->addJS($js_uri);
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayRemita();
        $this->_html .= $this->renderRemitaForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        if (!$this->checkCurrencyNGN($params['cart'])) {
            return;
        }
        $config = $this->getConfigFieldsValues();
        $publicKey = $config['REMITA_PUBLICKEY'];
        if ($config['REMITA_MODE'] == 1) {
            $remitaUrl = "https://remitademo.net/payment/v1/remita-pay-inline.bundle.js";
        } else {
            $remitaUrl = "https://login.remita.net/payment/v1/remita-pay-inline.bundle.js";

        }

        $gateway_chosen = 'none';
        $orderId =  $params['cart']->id;
        $uniqueId = uniqid();
        $trxref = $uniqueId. '_' .$orderId;

        if (Tools::getValue('gateway') == "remita") {
            $cart = $this->context->cart;
            $gateway_chosen = 'remita';
            $customer = new Customer((int)($cart->id_customer));
            $amount = $cart->getOrderTotal(true, Cart::BOTH);
            $currency_order = new Currency($cart->id_currency);

            $params = array(
              "reference"   => $trxref,
              "total_amount"=> $amount,
              "key"         => $publicKey,
              "url"         => $remitaUrl,
              "currency"    => $currency_order->iso_code,
              "email"       => $this->context->customer->email,
              "firstname"       => $this->context->customer->firstname,
              "lastname"       => $this->context->customer->lastname,
            );
            $this->context->smarty->assign(
                array(
                'gateway_chosen' => 'remita',
                'form_url'       => $this->context->link->getModuleLink($this->name, 'remitasuccess', array(), true),
                )
            );
            $this->context->smarty->assign(
                $params
            );
        }

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->trans('Pay by Remita'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:remita/views/templates/hook/intro.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/remita-payment-options.png'))
            ->setInputs(
                array(
                  'wcst_iframe' => array(
                  'name' =>'wcst_iframe',
                  'type' =>'hidden',
                  'value' =>'1',
                  )
                )
            );
        if ($gateway_chosen == 'remita') {
                $newOption->setAdditionalInformation(
                    $this->context->smarty->fetch('module:remita/views/templates/front/embedded.tpl')
                );
        }
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        $reference = Tools::getValue('reference');

        if ($reference == "" || $reference == NULL) {
            $reference = $params['order']->reference;
        }
        if (in_array(
            $state,
            array(
                Configuration::get('PS_OS_REMITA'),
                Configuration::get('PS_OS_OUTOFSTOCK'),
                Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
            )
        )
        ) {
            $remitaOwner = $this->owner;
           
            $this->smarty->assign(
                array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'remitaDetails' => $remitaDetails,
                'remitaAddress' => $remitaAddress,
                'remitaOwner' => $remitaOwner,
                'status' => 'ok',
                'reference' => $reference,
                'contact_url' => $this->context->link->getPageLink('contact', true)
                )
            );
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed',
                    'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:remita/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function checkCurrencyNGN($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        if ($currency_order->iso_code == 'NGN') {
            return true;
        }
        return false;
    }

    public function renderRemitaForm()
    {



        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('User details', array(), 'Modules.Remita.Admin'),
                    'icon' => 'icon-user'
                ),
        
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Test Mode', array(), 'Modules.Remita.Admin'),
                        'name' => 'REMITA_MODE',
                        'is_bool' => true,
                        'required' => true,
                         'values' =>array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Test', array(), 'Modules.Remita.Admin')
                            ),array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('False', array(), 'Modules.Remita.Admin')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Secret key', array(), 'Modules.Remita.Admin'),
                        'name' => 'REMITA_SECRETKEY',
                       
                    ),
                      array(
                        'type' => 'text',
                        'label' => $this->trans('Public key', array(), 'Modules.Remita.Admin'),
                        'name' => 'REMITA_PUBLICKEY',
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $fields_form_customization = array();

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_customization));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'REMITA_SECRETKEY' => Tools::getValue('REMITA_SECRETKEY', Configuration::get('REMITA_SECRETKEY')),
            'REMITA_PUBLICKEY' => Tools::getValue('REMITA_PUBLICKEY', Configuration::get('REMITA_PUBLICKEY')),
            'REMITA_MODE' => Tools::getValue('REMITA_MODE', Configuration::get('REMITA_MODE')),
        );
    }
}
