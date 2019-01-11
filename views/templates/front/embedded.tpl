{*
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
*}

{if isset($gateway_chosen) && $gateway_chosen == 'remita'}


<form name="custompaymentmethod" id="remita_form" method="post" action="{$form_url}">
    <input type="hidden" name="amounttotal" value="{$total_amount}" />
    <input type="hidden" name="key" value="{$key}" />
    <input type="hidden" name="email" value="{$email}" />
    <input type="hidden" name="reference" value="{$reference}" />
    <input type="hidden" name="url" value="{$url}" />
    <input type="hidden" name="firstname" value="{$firstname}" />
    <input type="hidden" name="lastname" value="{$lastname}" />
</form>

<script src='{$url}'></script>

<script type="text/javascript">
    var paymentEngine = RmPaymentEngine.init({
        key: '{$key}',
        customerId: '{$email}',
        firstName: '{$firstname}',
        lastName: '{$lastname}',
        transactionId: '{$reference}',
        narration: "bill pay",
        email: '{$email}',
        amount: '{$total_amount}',
        onSuccess: function (response) {
            $( "#remita_form" ).submit();
            console.log('callback Successful Response', response);
        },
        onError: function (response) {

            console.log('callback Error Response', response);
        },
        onClose: function () {
            console.log("closed");
        }
    });

    paymentEngine.showPaymentWidget();


</script>
{/if}
