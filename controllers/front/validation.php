<?php
/**
 * Controlador de validación del pago por suscripción
 */

class PagoSuscriekpValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // Verificar que el módulo está activo
        if (!$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Obtener el carrito
        $cart = $this->context->cart;

        // Validaciones del carrito
        if ($cart->id_customer == 0 
            || $cart->id_address_delivery == 0 
            || $cart->id_address_invoice == 0 
            || !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Verificar que el cliente está autorizado
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'pagosuscriekp') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('Este método de pago no está disponible.', 'validation'));
        }

        // Obtener el cliente
        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Obtener el plan seleccionado
        $id_plan = (int)Tools::getValue('id_plan');

        if (!$id_plan) {
            $this->errors[] = $this->module->l('Plan de suscripción no válido', 'validation');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        // Verificar que el plan existe y está activo
        $plan = $this->getPlan($id_plan);

        if (!$plan || !$plan['active']) {
            $this->errors[] = $this->module->l('El plan seleccionado no está disponible', 'validation');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        // Obtener el importe total del carrito
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        // Crear el pedido
        $payment_method = 'Pago por suscripción - ' . $plan['name'];
        
        $this->module->validateOrder(
            (int)$cart->id,
            (int)Configuration::get('PAGOSUSCRIEKP_ORDER_STATE'),
            $total,
            $payment_method,
            null,
            array(),
            (int)$currency->id,
            false,
            $customer->secure_key
        );

        $id_order = (int)$this->module->currentOrder;

        if (!$id_order) {
            $this->errors[] = $this->module->l('Error al crear el pedido', 'validation');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        // IMPORTANTE: Eliminar el pago automático que crea PrestaShop
        // Solo queremos crear ps_order_payment cuando se marquen las cuotas individuales como pagadas
        Db::getInstance()->delete('order_payment', 'id_order = ' . (int)$id_order);

        // Crear la suscripción
        $subscription = new Subscription();
        $subscription->id_order = $id_order;
        $subscription->id_customer = (int)$customer->id;
        $subscription->id_product = $plan['id_product'] ? (int)$plan['id_product'] : null;
        $subscription->id_product_attribute = $plan['id_product_attribute'] ? (int)$plan['id_product_attribute'] : null;
        $subscription->status = 'active';

        if (!$subscription->add()) {
            $this->errors[] = $this->module->l('Error al crear la suscripción', 'validation');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        // Crear los pagos basados en el plan
        $order = new Order($id_order);
        if (!$subscription->createPaymentsFromPlan($id_plan, $order->date_add)) {
            $this->errors[] = $this->module->l('Error al crear los pagos de la suscripción', 'validation');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        // Enviar email de confirmación con los detalles de la suscripción
        $this->sendSubscriptionConfirmationEmail($subscription, $customer, $order);

        // Redireccionar a la página de confirmación
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id 
            . '&id_module=' . (int)$this->module->id 
            . '&id_order=' . $id_order 
            . '&key=' . $customer->secure_key);
    }

    /**
     * Obtener información del plan
     */
    private function getPlan($id_plan)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` 
                WHERE id_plan = ' . (int)$id_plan;
        
        return Db::getInstance()->getRow($sql);
    }

    /**
     * Enviar email de confirmación de suscripción
     */
    private function sendSubscriptionConfirmationEmail($subscription, $customer, $order)
    {
        $payments = $subscription->getPayments();

        // Preparar la lista de pagos para email TXT (texto plano)
        $payments_list_txt = '';
        foreach ($payments as $payment) {
            $payments_list_txt .= '- ' . Tools::displayPrice($payment['amount']) . ' - Vencimiento: ' . date('d/m/Y', strtotime($payment['due_date'])) . "\n";
        }

        // Preparar la lista de pagos para email HTML
        $payments_list_html = '';
        foreach ($payments as $payment) {
            $payments_list_html .= '<tr>';
            $payments_list_html .= '<td style="padding: 12px; border-bottom: 1px solid #dddddd;">Pago ' . $payment['installment_number'] . '</td>';
            $payments_list_html .= '<td style="padding: 12px; border-bottom: 1px solid #dddddd;"><strong>' . Tools::displayPrice($payment['amount']) . '</strong></td>';
            $payments_list_html .= '<td style="padding: 12px; border-bottom: 1px solid #dddddd;">' . date('d/m/Y', strtotime($payment['due_date'])) . '</td>';
            $payments_list_html .= '</tr>';
        }

        $bank_details_clean = Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS');
        $bank_address_clean = Configuration::get('PAGOSUSCRIEKP_BANK_ADDRESS');

        $templateVars = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{order_reference}' => $order->reference,
            '{order_total}' => Tools::displayPrice($subscription->getTotalAmount()),
            '{payments_list}' => $payments_list_txt,
            '{payments_list_html}' => $payments_list_html,
            '{bank_owner}' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
            '{bank_details}' => nl2br($bank_details_clean ? $bank_details_clean : 'No configurado'),
            '{bank_address}' => nl2br($bank_address_clean ? $bank_address_clean : ''),
        );

        return Mail::Send(
            (int)$order->id_lang,
            'subscription_confirmation',
            $this->module->l('Confirmación de tu suscripción', 'validation'),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            dirname(__FILE__) . '/../../mails/',
            false,
            (int)$order->id_shop
        );
    }
}