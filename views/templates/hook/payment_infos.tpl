{**
 * Plantilla para mostrar información del plan de pago en el checkout
 *}

<div class="pagosuscriekp-payment-info">
    <div class="plan-details">
        <h4>{l s='Detalles del plan de suscripción' mod='pagosuscriekp'}</h4>
        
        {if $installments && count($installments) > 0}
            <div class="alert alert-info">
                <p><strong>{l s='Este pago se fraccionará en' mod='pagosuscriekp'} {count($installments)} {l s='cuotas:' mod='pagosuscriekp'}</strong></p>
            </div>

            <table class="table table-bordered installments-table">
                <thead>
                    <tr>
                        <th class="text-center">{l s='Cuota' mod='pagosuscriekp'}</th>
                        <th class="text-center">{l s='Importe' mod='pagosuscriekp'}</th>
                        <th class="text-center">{l s='Días después de la compra' mod='pagosuscriekp'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$installments item=installment}
                        <tr>
                            <td class="text-center">
                                <strong>{l s='Pago' mod='pagosuscriekp'} {$installment.installment_number}</strong>
                            </td>
                            <td class="text-center">
                                <span class="price">{$installment.amount_formatted nofilter}</span>
                            </td>
                            <td class="text-center">
                                {if $installment.days_after_purchase == 0}
                                    <span class="badge badge-success">{l s='Inmediato' mod='pagosuscriekp'}</span>
                                {else}
                                    {$installment.days_after_purchase} {l s='días' mod='pagosuscriekp'}
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" class="text-right"><strong>{l s='Total:' mod='pagosuscriekp'}</strong></td>
                        <td class="text-center">
                            <strong class="price">{$total_formatted nofilter}</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        {/if}

        <div class="bank-details-preview">
            <h5>{l s='Datos bancarios para realizar los pagos:' mod='pagosuscriekp'}</h5>
            <div class="well">
                {if $bank_owner}
                    <p><strong>{l s='Titular:' mod='pagosuscriekp'}</strong> {$bank_owner|escape:'html':'UTF-8'}</p>
                {/if}
                {if $bank_details}
                    <p><strong>{l s='Datos bancarios:' mod='pagosuscriekp'}</strong><br>
                    {$bank_details nofilter}</p>
                {/if}
                {if $bank_address}
                    <p><strong>{l s='Dirección:' mod='pagosuscriekp'}</strong><br>
                    {$bank_address nofilter}</p>
                {/if}
            </div>
        </div>

        <div class="alert alert-warning">
            <i class="icon-info-circle"></i>
            {l s='Al confirmar este pedido, recibirás un email con toda la información detallada de tu suscripción y las fechas de pago.' mod='pagosuscriekp'}
        </div>
    </div>
</div>

<style>
.pagosuscriekp-payment-info {
    margin: 15px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.pagosuscriekp-payment-info h4 {
    margin-top: 0;
    color: #333;
    font-size: 18px;
    margin-bottom: 15px;
}

.pagosuscriekp-payment-info h5 {
    color: #555;
    font-size: 14px;
    margin-top: 20px;
    margin-bottom: 10px;
}

.installments-table {
    margin: 15px 0;
    background: white;
}

.installments-table thead {
    background: #f5f5f5;
}

.installments-table .price {
    font-size: 16px;
    font-weight: bold;
    color: #27ae60;
}

.installments-table .total-row {
    background: #f5f5f5;
    font-weight: bold;
}

.bank-details-preview {
    margin-top: 20px;
}

.bank-details-preview .well {
    background: white;
    border: 1px solid #ddd;
    padding: 15px;
    margin-top: 10px;
}

.bank-details-preview .well p {
    margin-bottom: 10px;
}

.bank-details-preview .well p:last-child {
    margin-bottom: 0;
}
</style>