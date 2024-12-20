<?php 

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class BudpayWebhookhandlerModuleFrontController
 */
class BudpayWebhookhandlerModuleFrontController extends ModuleFrontController
{
    /** @var Budpay $module */
    public $module;

    /**
     * BudpayWebhookhandlerModuleFrontController constructor.
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
            try {
                // Validate webhook signature
                $is_live = Tools::getValue(Budpay::GO_LIVE);
                $sec = Tools::getValue(Budpay::SECRET_KEY_TEST);
                $pub = Tools::getValue(Budpay::PUBLIC_KEY_TEST);
                if($is_live === 1) {
                    $sec = Tools::getValue(Budpay::SECRET_KEY_LIVE);
                    $pub = Tools::getValue(Budpay::SECRET_KEY_LIVE);
                }
                
                $merchantsignature = hash_hmac("SHA512", $pub, $sec);
                $webhooksignature = $_SERVER['HTTP_SIGNATURE'] ?? '';

                if ($merchantsignature !== $webhooksignature) {
                    Logger::addLog('Fraudulent Attempt: Invalid signature', 3);
                    die('DON’T HONOUR. IT IS A FRAUDULENT ATTEMPT');
                }

                // Signature is valid
                Logger::addLog('Valid Webhook Signature');

                
                // Determine the type of notification
                if (isset($data['notify']) && isset($data['notifyType'])) {
                    switch ($data['notify']) {
                        case 'transaction':
                            $this->handleTransaction($data);
                            break;
                        case 'payout':
                            $this->handlePayout($data);
                            break;
                        default:
                            throw new Exception('Unknown notification type');
                    }
                } else {
                    throw new Exception('Invalid payload structure');
                }

                die('ok');
            } catch (Exception $e) {
                Logger::addLog('Webhook Error: ' . $e->getMessage(), 3);
                die('ko');
            }
        }

        header('Content-Type: text/plain');
        die('ko');
    }

    /**
     * Handle transaction notifications.
     *
     * @param array $data
     * @return void
     */
    private function handleTransaction(array $data)
    {
        if ($data['notifyType'] === 'successful') {
            // Extract transaction details
            $transaction = $data['data'];

            // Log transaction success
            Logger::addLog('Transaction Success: ' . json_encode($transaction));

            // TODO: Update order status, record transaction in DB, etc.
        } else {
            throw new Exception('Unhandled transaction notification type');
        }
    }

    /**
     * Handle payout notifications.
     *
     * @param array $data
     * @return void
     */
    private function handlePayout(array $data)
    {
        if ($data['notifyType'] === 'successful') {
            // Extract payout details
            $payout = $data['data'];

            // Log payout success
            Logger::addLog('Payout Success: ' . json_encode($payout));

            // TODO: Update payout record in DB, notify user, etc.
        } else {
            throw new Exception('Unhandled payout notification type');
        }
    }
}
