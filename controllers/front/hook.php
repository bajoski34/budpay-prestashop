<?php 

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class PaypalAbstarctModuleFrontController
 */
class BudpayWebhookhandlerModuleFrontController extends ModuleFrontController
{
    /** @var Stripe $module */
    public $module;

    /**
     * StripeHookModuleFrontController constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        $this->ssl = Tools::usingSecureMode();
    }

    /**
     * Prevent displaying the maintenance page
     *
     * @return void
     */
    protected function displayMaintenancePage()
    {
    }

    /**
     * Post process to handle webhook settings.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function postProcess()
    {
        $body = file_get_contents('php://input');

        if (!empty($body) && $data = json_decode($body, true)) {
            // Verify with Budpay.
            try {
                //TODO: Handle hooks.
            } catch (\Exception $e) {
                die('ko');
            }
            // switch ($data['type']) {
            //     case 'review.closed':
            //         $this->processApproved($event);

            //         break;
            //     case 'charge.refunded':
            //         $this->processRefund($event);

            //         break;
            //     case 'charge.succeeded':
            //         $this->processSucceeded($event);

            //         break;
            //     case 'charge.captured':
            //         Logger::addLog(json_encode($event));
            //         $this->processCaptured($event);

            //         break;
            //     case 'charge.failed':
            //         $this->processFailed($event);

            //         break;
            // }

            die('ok');
        }

        header('Content-Type: text/plain');
        die('ko');
    }
}