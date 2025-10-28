{**
 * Plantilla para mostrar información de la suscripción en la página del pedido
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-credit-card"></i>
        {l s='Suscripción de pago' mod='pagosuscriekp'}
        {if $subscription->status == 'active'}
            <span class="badge badge-success">{l s='Activa' mod='pagosuscriekp'}</span>
        {else}
            <span class="badge badge-warning">{l s='Cancelada' mod='pagosuscriekp'}</span>
        {/if}
    </div>
    
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <a href="{$module_link|escape:'html':'UTF-8'}&viewsubscription={$subscription->id|intval}" 
                   class="btn btn-primary btn-sm pull-right" target="_blank">
                    <i class="icon-external-link"></i>
                    {l s='Gestionar suscripción' mod='pagosuscriekp'}
                </a>
                <h4>{l s='Pagos programados' mod='pagosuscriekp'}</h4>
            </div>
        </div>

        {if $payments && count($payments) > 0}
            <div class="table-responsive" style="margin-top: 15px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-center">{l s='#' mod='pagosuscriekp'}</th>
                            <th>{l s='Importe' mod='pagosuscriekp'}</th>
                            <th>{l s='Fecha vencimiento' mod='pagosuscriekp'}</th>
                            <th>{l s='Estado' mod='pagosuscriekp'}</th>
                            <th>{l s='Fecha pago' mod='pagosuscriekp'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$payments item=payment name=payments}
                            <tr class="{if $payment.paid}success{else}warning{/if}">
                                <td class="text-center">
                                    <strong>{$smarty.foreach.payments.iteration}</strong>
                                </td>
                                <td>
                                    {displayPrice price=$payment.amount}
                                </td>
                                <td>
                                    {dateFormat date=$payment.due_date full=0}
                                    {assign var="days_diff" value=0}
                                    {assign var="today" value=$smarty.now|date_format:"%Y-%m-%d"}
                                    {assign var="due" value=$payment.due_date}
                                    
                                    {if $due < $today && !$payment.paid}
                                        <span class="badge badge-danger">
                                            <i class="icon-warning"></i> {l s='Vencido' mod='pagosuscriekp'}
                                        </span>
                                    {/if}
                                </td>
                                <td>
                                    {if $payment.paid}
                                        <span class="badge badge-success">
                                            <i class="icon-check"></i> {l s='Pagado' mod='pagosuscriekp'}
                                        </span>
                                    {else}
                                        <span class="badge badge-warning">
                                            <i class="icon-time"></i> {l s='Pendiente' mod='pagosuscriekp'}
                                        </span>
                                    {/if}
                                </td>
                                <td>
                                    {if $payment.date_paid}
                                        {dateFormat date=$payment.date_paid full=1}
                                    {else}
                                        -
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right">
                                <strong>{l s='Total suscripción:' mod='pagosuscriekp'}</strong>
                                {assign var="total" value=0}
                                {assign var="paid" value=0}
                                {foreach from=$payments item=payment}
                                    {assign var="total" value=$total+$payment.amount}
                                    {if $payment.paid}
                                        {assign var="paid" value=$paid+$payment.amount}
                                    {/if}
                                {/foreach}
                                {displayPrice price=$total}
                                <br>
                                <strong>{l s='Pagado:' mod='pagosuscriekp'}</strong>
                                <span style="color: #27ae60;">{displayPrice price=$paid}</span>
                                <br>
                                <strong>{l s='Pendiente:' mod='pagosuscriekp'}</strong>
                                <span style="color: #e74c3c;">{displayPrice price=$total-$paid}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="alert alert-info">
                <i class="icon-info"></i>
                {l s='Para gestionar los pagos de esta suscripción (marcar como pagado, cancelar, etc.), utiliza el botón "Gestionar suscripción" en la parte superior.' mod='pagosuscriekp'}
            </div>
        {/if}
    </div>
</div>

<style>
.panel .badge {
    margin-left: 10px;
}

.table tbody tr.success {
    background-color: #dff0d8;
}

.table tbody tr.warning {
    background-color: #fcf8e3;
}
</style>