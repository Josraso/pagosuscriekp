<?php
/**
 * Script de test para verificar el envío de correos
 */

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/pagosuscriekp.php');

// Obtener contexto
$context = Context::getContext();

echo "<h1>Test de Envío de Correos - PagoSuscriekp</h1>";

// 1. Verificar configuración de correo de PrestaShop
echo "<h2>1. Configuración de Correo de PrestaShop</h2>";
echo "<strong>Método de envío:</strong> " . Configuration::get('PS_MAIL_METHOD') . "<br>";
echo "<strong>Servidor SMTP:</strong> " . Configuration::get('PS_MAIL_SERVER') . "<br>";
echo "<strong>Usuario SMTP:</strong> " . Configuration::get('PS_MAIL_USER') . "<br>";
echo "<strong>Puerto SMTP:</strong> " . Configuration::get('PS_MAIL_SMTP_PORT') . "<br>";
echo "<strong>Encriptación:</strong> " . Configuration::get('PS_MAIL_SMTP_ENCRYPTION') . "<br>";
echo "<strong>Email de la tienda:</strong> " . Configuration::get('PS_SHOP_EMAIL') . "<br>";

// 2. Verificar que existen los templates
echo "<h2>2. Templates de Correo</h2>";
$module_dir = dirname(__FILE__);
$html_template = $module_dir . '/mails/es/subscription_confirmation.html';
$txt_template = $module_dir . '/mails/es/subscription_confirmation.txt';

echo "<strong>Template HTML:</strong> " . ($file_exists = file_exists($html_template) ? 'SÍ ✓' : 'NO ✗') . " ($html_template)<br>";
echo "<strong>Template TXT:</strong> " . (file_exists($txt_template) ? 'SÍ ✓' : 'NO ✗') . " ($txt_template)<br>";

// 3. Test de envío de correo simple
echo "<h2>3. Test de Envío de Correo</h2>";

$test_email = Configuration::get('PS_SHOP_EMAIL'); // Cambia esto a tu email para hacer el test

echo "<p>Enviando correo de prueba a: <strong>$test_email</strong></p>";

$templateVars = array(
    '{firstname}' => 'TEST',
    '{lastname}' => 'USUARIO',
    '{email}' => $test_email,
    '{order_reference}' => 'TEST123',
    '{order_total}' => '100,00 €',
    '{payments_list}' => "- 50,00 € - Vencimiento: 01/11/2025\n- 50,00 € - Vencimiento: 01/12/2025\n",
    '{bank_owner}' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
    '{bank_details}' => Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS'),
    '{bank_address}' => Configuration::get('PAGOSUSCRIEKP_BANK_ADDRESS'),
);

$result = Mail::Send(
    (int)$context->language->id,
    'subscription_confirmation',
    'TEST: Confirmación de tu suscripción',
    $templateVars,
    $test_email,
    'TEST Usuario',
    null,
    null,
    null,
    null,
    $module_dir . '/mails/',
    false,
    (int)$context->shop->id
);

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✓ Correo enviado correctamente</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Error al enviar el correo</p>";

    // Mostrar errores de PHP
    if (function_exists('error_get_last')) {
        $error = error_get_last();
        if ($error) {
            echo "<pre>";
            print_r($error);
            echo "</pre>";
        }
    }
}

// 4. Verificar logs de PrestaShop
echo "<h2>4. Información Adicional</h2>";
echo "<p><strong>Ruta de logs de PrestaShop:</strong> " . _PS_ROOT_DIR_ . "/var/logs/</p>";
echo "<p>Revisa los logs de PrestaShop para más información sobre errores de correo.</p>";

// 5. Test con correo nativo de PrestaShop (para comparar)
echo "<h2>5. Test con Correo Nativo de PrestaShop</h2>";

$test_native = Mail::Send(
    (int)$context->language->id,
    'contact',
    'TEST: Mensaje de prueba',
    array(
        '{email}' => $test_email,
        '{message}' => 'Este es un correo de prueba desde el módulo PagoSuscriekp'
    ),
    $test_email,
    'TEST Usuario',
    null,
    null,
    null,
    null,
    _PS_MAIL_DIR_,
    false,
    (int)$context->shop->id
);

if ($test_native) {
    echo "<p style='color: green; font-weight: bold;'>✓ Correo nativo enviado correctamente</p>";
    echo "<p>Los correos de PrestaShop funcionan. El problema puede estar en el template del módulo.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Error al enviar el correo nativo</p>";
    echo "<p>Los correos de PrestaShop NO funcionan. Revisa la configuración SMTP en Parámetros > Parámetros avanzados > Email.</p>";
}

echo "<hr>";
echo "<h2>Instrucciones</h2>";
echo "<ol>";
echo "<li>Verifica que los correos nativos de PrestaShop funcionan (sección 5)</li>";
echo "<li>Si no funcionan, configura SMTP en: Parámetros > Parámetros avanzados > Email</li>";
echo "<li>Si funcionan pero los del módulo no, revisa los templates en: modules/pagosuscriekp/mails/es/</li>";
echo "<li>Revisa los logs en: var/logs/</li>";
echo "</ol>";
