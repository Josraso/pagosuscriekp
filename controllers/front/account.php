<?php
/**
 * Controlador frontend para la cuenta del cliente - Mis suscripciones
 */

class PagoSuscriekpAccountModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'my-account';
    public $ssl = true;

    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
    }

    public function initContent()
    {
        parent::initContent();

        $id_customer = (int)$this->context->customer->id;

        // Obtener suscripciones del cliente
        $sql = 'SELECT s.*, o.reference as order_reference
                FROM ' . _DB_PREFIX_ . 'pagosuscriekp_subscription s
                LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = s.id_order
                WHERE s.id_customer = ' . (int)$id_customer . '
                ORDER BY s.date_add DESC';

        $subscriptions_data = Db::getInstance()->executeS($sql);

        $subscriptions = array();

        if ($subscriptions_data) {
            foreach ($subscriptions_data as $sub_data) {
                $subscription = new Subscription($sub_data['id_subscription']);
                $payments = $subscription->getPayments();

                $paid_count = 0;
                $pending_count = 0;
                foreach ($payments as $payment) {
                    if ($payment['paid']) {
                        $paid_count++;
                    } else {
                        $pending_count++;
                    }
                }

                $total_amount = $subscription->getTotalAmount();
                $paid_amount = $subscription->getPaidAmount();
                $percentage = $total_amount > 0 ? round(($paid_amount / $total_amount) * 100) : 0;

                // Formatear fechas y precios
                $date_add_formatted = date('d/m/Y', strtotime($subscription->date_add));

                $payments_formatted = array();
                foreach ($payments as $payment) {
                    $payments_formatted[] = array(
                        'installment_number' => $payment['installment_number'],
                        'amount' => $payment['amount'],
                        'amount_formatted' => Tools::displayPrice($payment['amount']),
                        'due_date' => $payment['due_date'],
                        'due_date_formatted' => date('d/m/Y', strtotime($payment['due_date'])),
                        'paid' => $payment['paid'],
                        'paid_date' => $payment['paid_date'],
                        'paid_date_formatted' => $payment['paid_date'] ? date('d/m/Y', strtotime($payment['paid_date'])) : null
                    );
                }

                $subscriptions[] = array(
                    'subscription' => $subscription,
                    'order_reference' => $sub_data['order_reference'],
                    'date_add_formatted' => $date_add_formatted,
                    'payments' => $payments_formatted,
                    'paid_count' => $paid_count,
                    'pending_count' => $pending_count,
                    'total_count' => count($payments),
                    'total_amount' => $total_amount,
                    'total_amount_formatted' => Tools::displayPrice($total_amount),
                    'paid_amount' => $paid_amount,
                    'paid_amount_formatted' => Tools::displayPrice($paid_amount),
                    'pending_amount' => $subscription->getPendingAmount(),
                    'pending_amount_formatted' => Tools::displayPrice($subscription->getPendingAmount()),
                    'percentage' => $percentage,
                    'is_fully_paid' => $subscription->isFullyPaid()
                );
            }
        }

        $this->context->smarty->assign(array(
            'subscriptions' => $subscriptions,
            'back_url' => $this->context->link->getPageLink('my-account', true)
        ));

        $this->setTemplate('module:pagosuscriekp/views/templates/front/account.tpl');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = array(
            'title' => $this->module->l('Mis suscripciones', 'account'),
            'url' => $this->context->link->getModuleLink($this->module->name, 'account')
        );

        return $breadcrumb;
    }

    public function setMedia()
    {
        parent::setMedia();

        // Agregar estilos CSS si es necesario
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/front.css');
    }
}
