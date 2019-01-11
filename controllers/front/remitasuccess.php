<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class RemitaRemitasuccessModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function transaction_verification($trxref){
        // Callback remita to get real remita transaction status
        $secretkey = Configuration::get('REMITA_SECRETKEY');
        $publicKey = Configuration::get('REMITA_PUBLICKEY');
        $mode = Configuration::get('REMITA_MODE');
        $hash_string = $trxref . $secretkey;
        $txnHash = hash('sha512', $hash_string);

        if ($mode == '1') {
            $query_url = 'https://remitademo.net/payment/v1/payment/query/';
        }else{
            $query_url = 'https://login.remita.net/payment/v1/payment/query/';
        }

        $remitaQueryUrl = $query_url . $trxref ;

        $header = array(
            'Content-Type: application/json',
            'publicKey:'. $publicKey,
            'TXN_HASH:' . $txnHash
        );


        //  Initiate curl
        $ch = curl_init();

        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        // Set the url
        curl_setopt($ch, CURLOPT_URL, $remitaQueryUrl);

        // Set the header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);


        // Execute
        $result = curl_exec($ch);

        // Closing
        curl_close($ch);

        // decode json
        $response = json_decode($result, true);

        return $response;
    }
    public function initContent()
    {

		$cart = $this->context->cart;
    	$txn_code = Tools::getValue('reference');
        if(Tools::getValue('reference') == ""){
          $txn_code = $_POST['reference'];
        }
        $amount = Tools::getValue('amount');
        $email = Tools::getValue('email');
        $result_response = $this->transaction_verification($txn_code);

        $paymentReference = $result_response['responseData']['0']['paymentReference'];
        $order_details = explode('_', $result_response['responseData']['0']['transactionId']);
        $order_id = (int) $order_details[1];



        if($result_response['responseCode'] == '00'){

            $extra_vars = array(
                'transaction_id' => $txn_code
            );

            $total = $result_response ['responseData']['0']['amount'];;
            $customer = new Customer($cart->id_customer);
            $status = 'approved';

            $this->module->validateOrder(
                $cart->id,
                Configuration::get('PS_OS_REMITA'),
                $total,
                $this->module->displayName,
                'Remita Reference: '.$txn_code,
                $extra_vars,
                (int)$cart->id_currency,
                false,
                $customer->secure_key
            );

            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key.'&reference='.$txn_code);


        } elseif ($result_response['responseCode'] == '34'){

            //$this->context->link->getModuleLink($this->name, 'remitaerror', array(), true);
            Tools::redirect(Context::getContext()->link->getModuleLink('remita', 'remitaerror'));
            //Tools::redirect('index.php?controller=order&step=1');
            //return $this->fetch('module:remita/views/templates/hook/payment_error.tpl');

        } else {

          	Tools::redirect('404');
        }
    }
}
