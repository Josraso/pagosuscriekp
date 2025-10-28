{**
 * Vista detallada de una suscripción
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-user"></i>
        {l s='Detalles de la suscripción' mod='pagosuscriekp'} #{$subscription->id|intval}
        {if $subscription->status == 'active'}
            <span class="badge badge-success">{l s='Activa' mod='pagosuscriekp'}</span>
        {else}
            <span class="badge badge-warning">{l s='Cancelada' mod='pagosuscriekp'}</span>
        {/if}
    </div>

    <div class="panel-body">
        
        {* Información general *}
        <div class="row">
            <div class="col-md-6">
                <h4>{l s='Información del cliente' mod='pagosuscriekp'}</h4>
                <dl class="well list-detail">
                    <dt>{l s='Cliente:' mod='pagosuscriekp'}</dt>
                    <dd>
                        <a href="{$link->getAdminLink('AdminCustomers')|escape:'html':'UTF-8'}&id_customer={$customer->id|intval}&viewcustomer" target="_blank">
                            {$customer->firstname|escape:'html':'UTF-8'} {$customer->lastname|escape:'html':'UTF-8'}
                        </a>
                    </dd>
                    
                    <dt>{l s='Email:' mod='pagosuscriekp'}</dt>
                    <dd>{$customer->email|escape:'html':'UTF-8'}</dd>
                    
                    <dt>{l s='Pedido:' mod='pagosuscriekp'}</dt>
                    <dd>
                        <a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&id_order={$order->id|intval}&vieworder" target="_blank">
                            {$order->reference|escape:'html':'UTF-8'}
                        </a>
                    </dd>
                    
                    <dt>{l s='Fecha de creación:' mod='pagosuscriekp'}</dt>
                    <dd>{dateFormat date=$subscription->date_add full=1}</dd>
                </dl>
            </div>

            <div class="col-md-6">
                <h4>{l s='Resumen financiero' mod='pagosuscriekp'}</h4>
                <dl class="well list-detail">
                    <dt>{l s='Total suscripción:' mod='pagosuscriekp'}</dt>
                    <dd><strong style="font-size: 18px;">{displayPrice price=$total_amount}</strong></dd>
                    
                    <dt>{l s='Total pagado:' mod='pagosuscriekp'}</dt>
                    <dd><strong style="color: #27ae60; font-size: 16px;">{displayPrice price=$paid_amount}</strong></dd>
                    
                    <dt>{l s='Pendiente:' mod='pagosuscriekp'}</dt>
                    <dd><strong style="color: #e74c3c; font-size: 16px;">{displayPrice price=$pending_amount}</strong></dd>
                    
                    <dt>{l s='Progreso:' mod='pagosuscriekp'}</dt>
                    <dd>
                        {assign var="percentage" value=0}
                        {if $total_amount > 0}
                            {assign var="percentage" value=($paid_amount / $total_amount * 100)}
                        {/if}
                        <div class="progress">
                            <div class="progress-bar progress-bar-success" style="width: {$percentage|string_format:"%.0f"}%">
                                {$percentage|string_format:"%.0f"}%
                            </div>
                        </div>
                    </dd>
                </dl>
            </div>
        </div>

        <hr>

        {* Acciones rápidas *}
        <div class="row">
            <div class="col-md-12">
                <h4>{l s='Acciones' mod='pagosuscriekp'}</h4>
                <div class="btn-group">
                    {if $subscription->status == 'active'}
                        {if !$is_fully_paid}
                            <a href="{$current_index|escape:'html':'UTF-8'}&viewsubscription={$subscription->id|intval}&markAllPaid&token={$token|escape:'html':'UTF-8'}" 
                               class="btn btn-success"
                               onclick="return confirm('{l s='¿Marcar todos los pagos como pagados?' mod='pagosuscriekp' js=1}');">
                                <i class="icon-check"></i>
                                {l s='Marcar todo como pagado' mod='pagosuscriekp'}
                            </a>
                        {/if}
                        <a href="{$current_index|escape:'html':'UTF-8'}&id_subscription={$subscription->id|intval}&cancelpagosuscriekp_subscription&token={$token|escape:'html':'UTF-8'}" 
                           class="btn btn-warning"
                           onclick="return confirm('{l s='¿Cancelar esta suscripción?' mod='pagosuscriekp' js=1}');">
                            <i class="icon-times"></i>
                            {l s='Cancelar suscripción' mod='pagosuscriekp'}
                        </a>
                    {else}
                        <a href="{$current_index|escape:'html':'UTF-8'}&id_subscription={$subscription->id|intval}&reactivatepagosuscriekp_subscription&token={$token|escape:'html':'UTF-8'}" 
                           class="btn btn-success"
                           onclick="return confirm('{l s='¿Reactivar esta suscripción?' mod='pagosuscriekp' js=1}');">
                            <i class="icon-check"></i>
                            {l s='Reactivar suscripción' mod='pagosuscriekp'}
                        </a>
                    {/if}
                </div>
            </div>
        </div>

        <hr>

        {* Lista de pagos *}
        <h4>{l s='Pagos programados' mod='pagosuscriekp'}</h4>
        
        {if $payments && count($payments) > 0}
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">{l s='#' mod='pagosuscriekp'}</th>
                            <th>{l s='Importe' mod='pagosuscriekp'}</th>
                            <th>{l s='Fecha vencimiento' mod='pagosuscriekp'}</th>
                            <th class="text-center">{l s='Estado' mod='pagosuscriekp'}</th>
                            <th>{l s='Fecha de pago' mod='pagosuscriekp'}</th>
                            <th class="text-center" style="width: 150px;">{l s='Acciones' mod='pagosuscriekp'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$payments item=payment name=payments}
                            {assign var="today" value=$smarty.now|date_format:"%Y-%m-%d"}
                            {assign var="is_overdue" value=($payment.due_date < $today && !$payment.paid)}
                            
                            <tr class="{if $payment.paid}success{elseif $is_overdue}danger{else}warning{/if}">
                                <td class="text-center">
                                    <strong>{$smarty.foreach.payments.iteration}</strong>
                                </td>
                                <td>
                                    <strong style="font-size: 16px;">{displayPrice price=$payment.amount}</strong>
                                </td>
                                <td>
                                    {dateFormat date=$payment.due_date full=0}
                                    {if $is_overdue}
                                        <br>
                                        <span class="badge badge-danger">
                                            <i class="icon-warning"></i> {l s='Vencido' mod='pagosuscriekp'}
                                        </span>
                                    {/if}
                                </td>
                                <td class="text-center">
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
                                        {if $payment.id_order_payment}
                                            <br>
                                            <small class="text-muted">
                                                <i class="icon-link"></i> 
                                                {l s='Registrado en pedido' mod='pagosuscriekp'}
                                            </small>
                                        {/if}
                                    {else}
                                        <span class="text-muted">-</span>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    {if !$payment.paid}
                                        <a href="{$current_index|escape:'html':'UTF-8'}&viewsubscription={$subscription->id|intval}&markPaid&id_payment={$payment.id_payment|intval}&token={$token|escape:'html':'UTF-8'}" 
                                           class="btn btn-success btn-xs"
                                           onclick="return confirm('{l s='¿Marcar este pago como pagado?' mod='pagosuscriekp' js=1}');">
                                            <i class="icon-check"></i>
                                            {l s='Marcar pagado' mod='pagosuscriekp'}
                                        </a>
                                    {else}
                                        <a href="{$current_index|escape:'html':'UTF-8'}&viewsubscription={$subscription->id|intval}&markUnpaid&id_payment={$payment.id_payment|intval}&token={$token|escape:'html':'UTF-8'}" 
                                           class="btn btn-warning btn-xs"
                                           onclick="return confirm('{l s='¿Desmarcar este pago?' mod='pagosuscriekp' js=1}');">
                                            <i class="icon-undo"></i>
                                            {l s='Desmarcar' mod='pagosuscriekp'}
                                        </a>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                {l s='No hay pagos registrados para esta suscripción.' mod='pagosuscriekp'}
            </div>
        {/if}

    </div>

    <div class="panel-footer">
        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" class="btn btn-default">
            <i class="icon-arrow-left"></i>
            {l s='Volver al listado' mod='pagosuscriekp'}
        </a>
    </div>
</div>

<style>
.list-detail dt {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.list-detail dd {
    margin-bottom: 15px;
}

.table tbody tr.success {
    background-color: #dff0d8;
}

.table tbody tr.warning {
    background-color: #fcf8e3;
}

.table tbody tr.danger {
    background-color: #f2dede;
}

.progress {
    height: 30px;
    margin-bottom: 0;
}

.progress-bar {
    line-height: 30px;
    font-weight: bold;
}
</style>