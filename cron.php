<?php
/**
 * Cron job para envío de recordatorios de pago
 * Este archivo puede ser ejecutado manualmente o mediante un cron del servidor
 * 
 * Configurar en crontab:
 * 0 9 * * * php /path/to/prestashop/modules/pagosuscriekp/cron.php > /dev/null 2>&1
 * (Se ejecuta todos los días a las 9:00 AM)
 */

// Definir que estamos en modo cron
define('_PS_ADMIN_DIR_', getcwd());

// Incluir configuración de PrestaShop
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/pagosuscriekp.php');
require_once(dirname(__FILE__) . '/classes/Subscription.php');
require_once(dirname(__FILE__) . '/classes/SubscriptionPayment.php');

// Verificar token de seguridad (opcional pero recomendado)
$secure_token = Tools::getValue('token');
$expected_token = Configuration::get('PAGOSUSCRIEKP_CRON_TOKEN');

if ($expected_token && $secure_token !== $expected_token) {
    die('Invalid token');
}

// Log inicio
$log_file = dirname(__FILE__) . '/logs/cron.log';
$log_dir = dirname($log_file);

if (!file_exists($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
    @file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

logMessage('===== INICIO CRON PAGOSUSCRIEKP =====');

try {
    // Instanciar el módulo
    $module = Module::getInstanceByName('pagosuscriekp');
    
    if (!$module || !$module->active) {
        logMessage('ERROR: El módulo no está activo o no se pudo cargar');
        exit(1);
    }

    logMessage('Módulo cargado correctamente');

    // Obtener días de recordatorio
    $reminder_days = (int)Configuration::get('PAGOSUSCRIEKP_REMINDER_DAYS');
    logMessage("Días de recordatorio configurados: {$reminder_days}");

    // Calcular fecha objetivo
    $target_date = date('Y-m-d', strtotime('+' . $reminder_days . ' days'));
    logMessage("Buscando pagos con vencimiento: {$target_date}");

    // Obtener pagos pendientes que vencen en la fecha objetivo
    $sql = 'SELECT p.*, s.id_customer, s.id_order, s.status as subscription_status
            FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` p
            INNER JOIN `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` s ON (p.id_subscription = s.id_subscription)
            WHERE p.paid = 0 
            AND p.due_date = "' . pSQL($target_date) . '"
            AND s.status = "active"';

    $payments = Db::getInstance()->executeS($sql);

    if (!$payments || count($payments) == 0) {
        logMessage('No hay pagos pendientes para recordar en esta fecha');
        logMessage('===== FIN CRON PAGOSUSCRIEKP =====');
        exit(0);
    }

    logMessage('Encontrados ' . count($payments) . ' pagos pendientes');

    $sent_count = 0;
    $error_count = 0;

    foreach ($payments as $payment_data) {
        try {
            $customer = new Customer($payment_data['id_customer']);
            $order = new Order($payment_data['id_order']);
            
            if (!Validate::isLoadedObject($customer) || !Validate::isLoadedObject($order)) {
                logMessage("ERROR: Cliente u orden no válidos para pago ID {$payment_data['id_payment']}");
                $error_count++;
                continue;
            }

            logMessage("Enviando recordatorio a {$customer->email} - Pedido {$order->reference}");

            $templateVars = array(
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{order_reference}' => $order->reference,
                '{amount}' => Tools::displayPrice($payment_data['amount']),
                '{due_date}' => Tools::displayDate($payment_data['due_date']),
                '{bank_owner}' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
                '{bank_details}' => Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS'),
            );

            $result = Mail::Send(
                (int)$order->id_lang,
                'payment_reminder',
                Mail::l('Recordatorio de pago pendiente', (int)$order->id_lang),
                $templateVars,
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                null,
                null,
                null,
                null,
                dirname(__FILE__) . '/mails/',
                false,
                (int)$order->id_shop
            );

            if ($result) {
                logMessage("✓ Email enviado correctamente a {$customer->email}");
                $sent_count++;
            } else {
                logMessage("✗ Error al enviar email a {$customer->email}");
                $error_count++;
            }

        } catch (Exception $e) {
            logMessage("ERROR: Excepción al procesar pago ID {$payment_data['id_payment']}: " . $e->getMessage());
            $error_count++;
        }
    }

    logMessage("Resumen: {$sent_count} emails enviados, {$error_count} errores");
    logMessage('===== FIN CRON PAGOSUSCRIEKP =====');

    exit(0);

} catch (Exception $e) {
    logMessage('ERROR FATAL: ' . $e->getMessage());
    logMessage('===== FIN CRON PAGOSUSCRIEKP (CON ERROR) =====');
    exit(1);
}