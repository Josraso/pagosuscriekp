{**
 * Formulario para crear y editar planes de suscripción
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {if $editing}
            {l s='Editar plan de suscripción' mod='pagosuscriekp'}
        {else}
            {l s='Crear nuevo plan de suscripción' mod='pagosuscriekp'}
        {/if}
    </div>

    <form action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
        <input type="hidden" name="submitPlan" value="1">
        {if $editing}
            <input type="hidden" name="id_plan" value="{$id_plan|intval}">
        {/if}

        <div class="panel-body">
            
            {* Nombre del plan *}
            <div class="form-group">
                <label class="control-label col-lg-3 required">
                    <span class="label-tooltip" data-toggle="tooltip" title="{l s='Nombre descriptivo del plan' mod='pagosuscriekp'}">
                        {l s='Nombre del plan' mod='pagosuscriekp'}
                    </span>
                </label>
                <div class="col-lg-9">
                    <input type="text" name="plan_name" class="form-control" 
                           value="{if isset($plan.name)}{$plan.name|escape:'html':'UTF-8'}{/if}" 
                           required>
                    <p class="help-block">
                        {l s='Ejemplo: "Plan 3 cuotas sin intereses", "Suscripción mensual", etc.' mod='pagosuscriekp'}
                    </p>
                </div>
            </div>

            {* Producto asociado *}
            <div class="form-group">
                <label class="control-label col-lg-3">
                    <span class="label-tooltip" data-toggle="tooltip" title="{l s='Deja vacío para plan genérico aplicable a cualquier producto' mod='pagosuscriekp'}">
                        {l s='Producto específico' mod='pagosuscriekp'}
                    </span>
                </label>
                <div class="col-lg-9">
                    <select name="id_product" id="id_product" class="form-control">
                        <option value="0">{l s='-- Plan genérico (todos los productos) --' mod='pagosuscriekp'}</option>
                        {foreach from=$products item=product}
                            <option value="{$product.id_product|intval}" 
                                    {if isset($plan.id_product) && $plan.id_product == $product.id_product}selected{/if}>
                                {$product.name|escape:'html':'UTF-8'}
                            </option>
                        {/foreach}
                    </select>
                    <p class="help-block">
                        {l s='Si seleccionas un producto, este plan solo estará disponible para ese producto específico.' mod='pagosuscriekp'}
                    </p>
                </div>
            </div>

            {* Combinación/Variante *}
            <div class="form-group" id="combination_group" style="display:none;">
                <label class="control-label col-lg-3">
                    {l s='Combinación específica' mod='pagosuscriekp'}
                </label>
                <div class="col-lg-9">
                    <select name="id_product_attribute" id="id_product_attribute" class="form-control">
                        <option value="0">{l s='-- Todas las combinaciones --' mod='pagosuscriekp'}</option>
                    </select>
                    <p class="help-block">
                        {l s='Si el producto tiene combinaciones, puedes seleccionar una específica.' mod='pagosuscriekp'}
                    </p>
                </div>
            </div>

            {* Estado *}
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Estado' mod='pagosuscriekp'}
                </label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="active" id="active_on" value="1" 
                               {if !isset($plan.active) || $plan.active == 1}checked="checked"{/if}>
                        <label for="active_on">{l s='Sí' mod='pagosuscriekp'}</label>
                        <input type="radio" name="active" id="active_off" value="0" 
                               {if isset($plan.active) && $plan.active == 0}checked="checked"{/if}>
                        <label for="active_off">{l s='No' mod='pagosuscriekp'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Solo los planes activos estarán disponibles en el checkout.' mod='pagosuscriekp'}
                    </p>
                </div>
            </div>

            <hr>

            {* Cuotas del plan *}
            <div class="form-group">
                <label class="control-label col-lg-3 required">
                    {l s='Cuotas del plan' mod='pagosuscriekp'}
                </label>
                <div class="col-lg-9">
                    <div id="installments_container">
                        <table class="table table-bordered" id="installments_table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 60px;">{l s='#' mod='pagosuscriekp'}</th>
                                    <th>{l s='Importe (€)' mod='pagosuscriekp'}</th>
                                    <th>{l s='Días después de la compra' mod='pagosuscriekp'}</th>
                                    <th style="width: 80px;" class="text-center">{l s='Acción' mod='pagosuscriekp'}</th>
                                </tr>
                            </thead>
                            <tbody id="installments_body">
                                {if isset($installments) && count($installments) > 0}
                                    {foreach from=$installments item=installment name=inst}
                                        <tr class="installment-row">
                                            <td class="text-center">
                                                <strong class="installment-number">{$smarty.foreach.inst.iteration}</strong>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="number" name="installment_amount[]" 
                                                           class="form-control" step="0.01" min="0.01" 
                                                           value="{$installment.amount|escape:'html':'UTF-8'}" required>
                                                    <span class="input-group-addon">€</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="number" name="installment_days[]" 
                                                           class="form-control" min="0" 
                                                           value="{$installment.days_after_purchase|intval}" required>
                                                    <span class="input-group-addon">{l s='días' mod='pagosuscriekp'}</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm remove-installment">
                                                    <i class="icon-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    {/foreach}
                                {else}
                                    <tr class="installment-row">
                                        <td class="text-center">
                                            <strong class="installment-number">1</strong>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" name="installment_amount[]" 
                                                       class="form-control" step="0.01" min="0.01" 
                                                       placeholder="100.00" required>
                                                <span class="input-group-addon">€</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" name="installment_days[]" 
                                                       class="form-control" min="0" 
                                                       placeholder="0" value="0" required>
                                                <span class="input-group-addon">{l s='días' mod='pagosuscriekp'}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm remove-installment">
                                                <i class="icon-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                {/if}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4">
                                        <button type="button" class="btn btn-default btn-sm" id="add_installment">
                                            <i class="icon-plus"></i>
                                            {l s='Añadir cuota' mod='pagosuscriekp'}
                                        </button>
                                        <span class="help-block" style="display: inline-block; margin-left: 15px;">
                                            {l s='Añade todas las cuotas necesarias. El día 0 significa pago inmediato.' mod='pagosuscriekp'}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-right">
                                        <strong>{l s='Total del plan:' mod='pagosuscriekp'}</strong>
                                    </td>
                                    <td class="text-center">
                                        <strong id="plan_total">0.00 €</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <div class="panel-footer">
            <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" 
               class="btn btn-default">
                <i class="process-icon-cancel"></i>
                {l s='Cancelar' mod='pagosuscriekp'}
            </a>
            <button type="submit" name="submitPlan" class="btn btn-default pull-right">
                <i class="process-icon-save"></i>
                {l s='Guardar plan' mod='pagosuscriekp'}
            </button>
        </div>
    </form>
</div>

<script type="text/javascript">
$(document).ready(function() {
    
    // Calcular total del plan
    function calculatePlanTotal() {
        var total = 0;
        $('#installments_body input[name="installment_amount[]"]').each(function() {
            var amount = parseFloat($(this).val()) || 0;
            total += amount;
        });
        $('#plan_total').text(total.toFixed(2) + ' €');
    }

    // Actualizar números de cuota
    function updateInstallmentNumbers() {
        $('#installments_body tr.installment-row').each(function(index) {
            $(this).find('.installment-number').text(index + 1);
        });
    }

    // Añadir cuota
    $('#add_installment').on('click', function() {
        var newRow = `
            <tr class="installment-row">
                <td class="text-center">
                    <strong class="installment-number"></strong>
                </td>
                <td>
                    <div class="input-group">
                        <input type="number" name="installment_amount[]" 
                               class="form-control" step="0.01" min="0.01" 
                               placeholder="100.00" required>
                        <span class="input-group-addon">€</span>
                    </div>
                </td>
                <td>
                    <div class="input-group">
                        <input type="number" name="installment_days[]" 
                               class="form-control" min="0" 
                               placeholder="30" required>
                        <span class="input-group-addon">{l s='días' mod='pagosuscriekp'}</span>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-installment">
                        <i class="icon-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#installments_body').append(newRow);
        updateInstallmentNumbers();
        calculatePlanTotal();
    });

    // Eliminar cuota
    $(document).on('click', '.remove-installment', function() {
        if ($('#installments_body tr.installment-row').length > 1) {
            $(this).closest('tr').remove();
            updateInstallmentNumbers();
            calculatePlanTotal();
        } else {
            alert('{l s='Debe haber al menos una cuota' mod='pagosuscriekp' js=1}');
        }
    });

    // Calcular total cuando cambia el importe
    $(document).on('input', 'input[name="installment_amount[]"]', function() {
        calculatePlanTotal();
    });

    // Cargar combinaciones cuando se selecciona un producto
    $('#id_product').on('change', function() {
        var id_product = $(this).val();
        
        if (id_product > 0) {
            // Cargar combinaciones via AJAX
            $.ajax({
                url: '{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&ajax=1&action=getCombinations',
                data: { id_product: id_product },
                dataType: 'json',
                success: function(data) {
                    var options = '<option value="0">{l s='-- Todas las combinaciones --' mod='pagosuscriekp' js=1}</option>';
                    
                    if (data && data.length > 0) {
                        $.each(data, function(index, combination) {
                            options += '<option value="' + combination.id_product_attribute + '">' + 
                                      combination.name + '</option>';
                        });
                        $('#combination_group').show();
                    } else {
                        $('#combination_group').hide();
                    }
                    
                    $('#id_product_attribute').html(options);
                }
            });
        } else {
            $('#combination_group').hide();
            $('#id_product_attribute').html('<option value="0">{l s='-- Todas las combinaciones --' mod='pagosuscriekp' js=1}</option>');
        }
    });

    // Inicializar
    updateInstallmentNumbers();
    calculatePlanTotal();
    
    // Si hay producto seleccionado al cargar, mostrar combinaciones
    if ($('#id_product').val() > 0) {
        $('#id_product').trigger('change');
    }
});
</script>

<style>
.installment-row input[type="number"] {
    text-align: right;
}

#plan_total {
    font-size: 18px;
    color: #27ae60;
}

#installments_table {
    margin-top: 10px;
}

#installments_table thead {
    background: #f5f5f5;
}

.help-block {
    color: #999;
    font-size: 12px;
}
</style>