{**
 * Plantilla para la página de confirmación del pedido
 *}

<div class="box pagosuscriekp-confirmation">
    <h3 class="page-subheading">
        {l s='Tu pedido con pago por suscripción ha sido confirmado' mod='pagosuscriekp'}
    </h3>

    <div class="alert alert-success">
        <i class="icon-check"></i>
        {l s='¡Gracias por tu pedido! Hemos registrado tu suscripción correctamente.' mod='pagosuscriekp'}
    </div>

    <div class="subscription-summary">
        <h4>{l s='Resumen de tu suscripción' mod='pagosuscriekp'}</h4>
        
        {if $payments && count($payments) > 0}
            <div class="alert alert-info">
                <strong>{l s='Tu pago se ha fraccionado en' mod='pagosuscriekp'} {count($payments)} {l s='cuotas' mod='pagosuscriekp'}</strong>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{l s='Cuota' mod='pagosuscriekp'}</th>
                            <th>{l s='Importe' mod='pagosuscriekp'}</th>
                            <th>{l s='Fecha de vencimiento' mod='pagosuscriekp'}</th>
                            <th>{l s='Estado' mod='pagosuscriekp'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$payments item=payment name=payments}
                            <tr>
                                <td>
                                    <strong>{l s='Pago' mod='pagosuscriekp'} {$smarty.foreach.payments.iteration}</strong>
                                </td>
                                <td>
                                    <span class="amount">{displayPrice price=$payment.amount}</span>
                                </td>
                                <td>
                                    {dateFormat date=$payment.due_date full=0}
                                </td>
                                <td>
                                    {if $payment.paid}
                                        <span class="badge badge-success">{l s='Pagado' mod='pagosuscriekp'}</span>
                                    {else}
                                        <span class="badge badge-warning">{l s='Pendiente' mod='pagosuscriekp'}</span>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" class="text-right"><strong>{l s='Total:' mod='pagosuscriekp'}</strong></td>
                            <td>
                                <strong class="total-amount">
                                    {assign var="total" value=0}
                                    {foreach from=$payments item=payment}
                                        {assign var="total" value=$total+$payment.amount}
                                    {/foreach}
                                    {displayPrice price=$total}
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        {/if}

        <div class="payment-instructions">
            <h4>{l s='Instrucciones para realizar los pagos' mod='pagosuscriekp'}</h4>
            
            <div class="bank-info">
                {if $bank_owner}
                    <p><strong>{l s='Titular de la cuenta:' mod='pagosuscriekp'}</strong><br>
                    {$bank_owner|escape:'html':'UTF-8'}</p>
                {/if}

                {if $bank_details}
                    <p><strong>{l s='Datos bancarios:' mod='pagosuscriekp'}</strong><br>
                    {$bank_details nofilter}</p>
                {/if}

                {if $bank_address}
                    <p><strong>{l s='Dirección del banco:' mod='pagosuscriekp'}</strong><br>
                    {$bank_address nofilter}</p>
                {/if}
            </div>

            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                <strong>{l s='Importante:' mod='pagosuscriekp'}</strong>
                <ul>
                    <li>{l s='Por favor, realiza cada pago en la fecha indicada.' mod='pagosuscriekp'}</li>
                    <li>{l s='Recibirás un recordatorio por email unos días antes de cada vencimiento.' mod='pagosuscriekp'}</li>
                    <li>{l s='En el concepto de la transferencia indica tu número de pedido para facilitar la identificación del pago.' mod='pagosuscriekp'}</li>
                    <li>{l s='Hemos enviado un email a tu correo con toda esta información.' mod='pagosuscriekp'}</li>
                </ul>
            </div>
        </div>

        <div class="contact-info">
            <p>
                <i class="icon-envelope"></i>
                {l s='Si tienes alguna duda sobre tu suscripción, no dudes en contactarnos.' mod='pagosuscriekp'}
            </p>
        </div>
    </div>
</div>

<style>
.pagosuscriekp-confirmation {
    margin: 20px 0;
    padding: 20px;
}

.pagosuscriekp-confirmation h3 {
    color: #333;
    margin-bottom: 20px;
}

.pagosuscriekp-confirmation h4 {
    color: #555;
    margin-top: 25px;
    margin-bottom: 15px;
    font-size: 16px;
}

.subscription-summary {
    margin-top: 20px;
}

.subscription-summary table {
    margin: 20px 0;
}

.subscription-summary .amount {
    font-weight: bold;
    color: #27ae60;
    font-size: 15px;
}

.subscription-summary .total-row {
    background: #f5f5f5;
    font-weight: bold;
}

.subscription-summary .total-amount {
    color: #27ae60;
    font-size: 18px;
}

.payment-instructions {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.bank-info {
    background: white;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 15px 0;
}

.bank-info p {
    margin-bottom: 15px;
}

.bank-info p:last-child {
    margin-bottom: 0;
}

.payment-instructions .alert ul {
    margin-top: 10px;
    margin-bottom: 0;
    padding-left: 20px;
}

.payment-instructions .alert li {
    margin-bottom: 5px;
}

.contact-info {
    margin-top: 20px;
    padding: 15px;
    background: #e8f4f8;
    border-left: 4px solid #3498db;
}

.contact-info p {
    margin: 0;
}
</style>