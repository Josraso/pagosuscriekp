<?php
/**
 * Script de debug para verificar el estado del módulo
 */

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/pagosuscriekp.php');

echo "<h1>Debug Módulo PagoSuscriekp</h1>";

$module = Module::getInstanceByName('pagosuscriekp');

echo "<h2>1. Estado del Módulo</h2>";
echo "Instalado: " . ($module ? 'SÍ' : 'NO') . "<br>";
echo "Activo: " . ($module && $module->active ? 'SÍ' : 'NO') . "<br>";
echo "ID: " . ($module ? $module->id : 'N/A') . "<br>";

echo "<h2>2. Hooks Registrados</h2>";
$sql = 'SELECT h.name
        FROM ' . _DB_PREFIX_ . 'hook_module hm
        INNER JOIN ' . _DB_PREFIX_ . 'hook h ON h.id_hook = hm.id_hook
        WHERE hm.id_module = ' . (int)$module->id;
$hooks = Db::getInstance()->executeS($sql);
echo "<ul>";
foreach ($hooks as $hook) {
    echo "<li>" . $hook['name'] . "</li>";
}
echo "</ul>";

echo "<h2>3. Planes en Base de Datos</h2>";
$sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'pagosuscriekp_plan';
$plans = Db::getInstance()->executeS($sql);
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nombre</th><th>id_product</th><th>Activo</th></tr>";
foreach ($plans as $plan) {
    echo "<tr>";
    echo "<td>" . $plan['id_plan'] . "</td>";
    echo "<td>" . $plan['name'] . "</td>";
    echo "<td>" . ($plan['id_product'] ? $plan['id_product'] : 'TODOS') . "</td>";
    echo "<td>" . ($plan['active'] ? 'SÍ' : 'NO') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>4. Configuración</h2>";
echo "Bank Owner: " . Configuration::get('PAGOSUSCRIEKP_BANK_OWNER') . "<br>";
echo "Bank Details: " . (Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS') ? 'Configurado' : 'NO configurado') . "<br>";
echo "Order State: " . Configuration::get('PAGOSUSCRIEKP_ORDER_STATE') . "<br>";
echo "Completed State: " . Configuration::get('PAGOSUSCRIEKP_COMPLETED_STATE') . "<br>";

echo "<h2>5. Test de Hook</h2>";
$cart = new Cart(1); // Usar carrito 1 para test
if ($module) {
    $params = array('cart' => $cart);
    $result = $module->hookPaymentOptions($params);
    echo "Resultado hook: ";
    if ($result) {
        echo count($result) . " opciones de pago devueltas<br>";
        var_dump($result);
    } else {
        echo "NULL o vacío<br>";
    }
}

echo "<h2>6. Verificar archivo validation.php</h2>";
$validation_file = dirname(__FILE__) . '/controllers/front/validation.php';
echo "Existe: " . (file_exists($validation_file) ? 'SÍ' : 'NO') . "<br>";
