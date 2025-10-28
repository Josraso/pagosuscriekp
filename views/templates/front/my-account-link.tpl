{**
 * Enlace en "Mi cuenta" para ver suscripciones
 *}

<a class="col-lg-4 col-md-6 col-sm-6 col-xs-12" id="pagosuscriekp-link"
   href="{$link->getModuleLink('pagosuscriekp', 'account', [], true)|escape:'html':'UTF-8'}"
   title="{l s='Mis suscripciones' mod='pagosuscriekp'}">
    <span class="link-item">
        <i class="material-icons">&#xE8B8;</i>
        {l s='Mis suscripciones de pago' mod='pagosuscriekp'}
    </span>
</a>
