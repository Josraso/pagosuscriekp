{**
 * Template para mostrar las suscripciones del cliente
 *}

{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='Mis suscripciones de pago' mod='pagosuscriekp'}
{/block}

{block name='page_content'}
    <div class="pagosuscriekp-customer-subscriptions">

        {if $subscriptions && count($subscriptions) > 0}

            <p class="alert alert-info">
                <i class="material-icons">&#xE88E;</i>
                {l s='Aquí puedes consultar el estado de tus suscripciones de pago y ver qué cuotas están pendientes o pagadas.' mod='pagosuscriekp'}
            </p>

            {foreach from=$subscriptions item=sub}
                <div class="card subscription-card" style="margin-bottom: 20px;">
                    <div class="card-header" style="background: #f5f5f5; padding: 15px;">
                        <div class="row">
                            <div class="col-md-6">
                                <h3 style="margin: 0; font-size: 18px;">
                                    <i class="material-icons" style="vertical-align: middle;">&#xE8B8;</i>
                                    {l s='Suscripción' mod='pagosuscriekp'} #{$sub.subscription->id|intval}
                                </h3>
                                <p style="margin: 5px 0 0 0; color: #666;">
                                    {l s='Pedido:' mod='pagosuscriekp'} {$sub.order_reference|escape:'html':'UTF-8'}
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                {if $sub.subscription->status == 'active'}
                                    <span class="badge badge-success" style="background: #28a745; color: white; padding: 8px 12px; border-radius: 4px;">
                                        {l s='Activa' mod='pagosuscriekp'}
                                    </span>
                                {else}
                                    <span class="badge badge-secondary" style="background: #6c757d; color: white; padding: 8px 12px; border-radius: 4px;">
                                        {l s='Cancelada' mod='pagosuscriekp'}
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <div class="card-body" style="padding: 20px;">

                        {* Información general *}
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-3">
                                <strong>{l s='Fecha de creación:' mod='pagosuscriekp'}</strong><br>
                                {$sub.date_add_formatted|escape:'html':'UTF-8'}
                            </div>
                            <div class="col-md-3">
                                <strong>{l s='Total:' mod='pagosuscriekp'}</strong><br>
                                {$sub.total_amount_formatted nofilter}
                            </div>
                            <div class="col-md-3">
                                <strong>{l s='Pagado:' mod='pagosuscriekp'}</strong><br>
                                {$sub.paid_amount_formatted nofilter}
                            </div>
                            <div class="col-md-3">
                                <strong>{l s='Pendiente:' mod='pagosuscriekp'}</strong><br>
                                {$sub.pending_amount_formatted nofilter}
                            </div>
                        </div>

                        {* Barra de progreso *}
                        <div class="progress" style="height: 30px; margin-bottom: 20px; border-radius: 5px;">
                            <div class="progress-bar progress-bar-success"
                                 role="progressbar"
                                 style="width: {$sub.percentage|intval}%; background: #28a745;">
                                <strong>{$sub.percentage|intval}%</strong>
                            </div>
                        </div>

                        <p style="margin-bottom: 15px;">
                            <strong>{l s='Pagos:' mod='pagosuscriekp'}</strong>
                            {$sub.paid_count|intval} {l s='de' mod='pagosuscriekp'} {$sub.total_count|intval} {l s='cuotas pagadas' mod='pagosuscriekp'}
                        </p>

                        {* Lista de pagos *}
                        {if $sub.payments && count($sub.payments) > 0}
                            <div class="table-responsive">
                                <table class="table table-bordered" style="margin-bottom: 0;">
                                    <thead style="background: #f9f9f9;">
                                        <tr>
                                            <th class="text-center">{l s='Cuota' mod='pagosuscriekp'}</th>
                                            <th class="text-center">{l s='Importe' mod='pagosuscriekp'}</th>
                                            <th class="text-center">{l s='Fecha prevista' mod='pagosuscriekp'}</th>
                                            <th class="text-center">{l s='Estado' mod='pagosuscriekp'}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach from=$sub.payments item=payment}
                                            <tr>
                                                <td class="text-center">
                                                    <strong>{l s='Pago' mod='pagosuscriekp'} {$payment.installment_number|intval}</strong>
                                                </td>
                                                <td class="text-center">
                                                    {$payment.amount_formatted nofilter}
                                                </td>
                                                <td class="text-center">
                                                    {$payment.due_date_formatted|escape:'html':'UTF-8'}
                                                </td>
                                                <td class="text-center">
                                                    {if $payment.paid}
                                                        <span class="badge badge-success" style="background: #28a745; color: white; padding: 5px 10px;">
                                                            ✓ {l s='Pagado' mod='pagosuscriekp'}
                                                        </span>
                                                        {if $payment.paid_date_formatted}
                                                            <br><small>{$payment.paid_date_formatted|escape:'html':'UTF-8'}</small>
                                                        {/if}
                                                    {else}
                                                        <span class="badge badge-warning" style="background: #ffc107; color: #000; padding: 5px 10px;">
                                                            ⏱ {l s='Pendiente' mod='pagosuscriekp'}
                                                        </span>
                                                    {/if}
                                                </td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        {/if}

                        {* Alerta si está completamente pagado *}
                        {if $sub.is_fully_paid}
                            <div class="alert alert-success" style="margin-top: 15px; margin-bottom: 0;">
                                <i class="material-icons" style="vertical-align: middle;">&#xE86C;</i>
                                {l s='¡Felicidades! Esta suscripción ha sido completamente pagada.' mod='pagosuscriekp'}
                            </div>
                        {/if}

                    </div>
                </div>
            {/foreach}

        {else}

            <div class="alert alert-info">
                <i class="material-icons">&#xE88E;</i>
                {l s='No tienes ninguna suscripción de pago activa.' mod='pagosuscriekp'}
            </div>

            <p>
                <a href="{$link->getPageLink('index', true)|escape:'html':'UTF-8'}" class="btn btn-primary">
                    <i class="material-icons">&#xE8B1;</i>
                    {l s='Volver a la tienda' mod='pagosuscriekp'}
                </a>
            </p>

        {/if}

    </div>
{/block}

<style>
.subscription-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.subscription-card .card-header {
    border-bottom: 1px solid #ddd;
}

.subscription-card .table {
    margin-bottom: 0;
}

.subscription-card .table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
}

.subscription-card .badge {
    font-size: 13px;
    font-weight: 500;
}

.progress {
    box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
}

.progress-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: width 0.6s ease;
}
</style>
