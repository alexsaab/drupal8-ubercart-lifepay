<?php

namespace Drupal\uc_lifepay\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_cart\CartManagerInterface;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_payment\Plugin\PaymentMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller routines for uc_Lifepay.
 */
class LifepayController extends ControllerBase
{
    /** @var \Drupal\uc_cart\CartManagerInterface The cart manager */
    protected $cartManager;

    /** @var Store Session */
    protected $session;

    /** @var array Variable for store configuration */
    protected $configuration = array();

    /**
     * Constructs a LifepayController.
     *
     * @param \Drupal\uc_cart\CartManagerInterface $cart_manager
     *   The cart manager.
     */
    public function __construct(CartManagerInterface $cart_manager)
    {
        $this->cartManager = $cart_manager;
    }


    /**
     * Create method
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @return \Drupal\uc_lifepay\Controller\LifepayController
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('uc_cart.manager')
        );
    }


    /**
     * Notification callback function
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function notification(Request $request)
    {
        $posted = \Drupal::request()->request->all();

        // Try to get values from request
        $orderId = $posted['order_id'];
        // Get first if
        if (!isset($orderId)) {
            \Drupal::messenger()->addMessage($this->t('Site can not get info from you transaction. Please return to store and perform the order'),
                'success');
            $response = new RedirectResponse('/', 302);
            $response->send();
            return;
        }
        $order = Order::load($orderId);

        // Load configuration
        $plugin = \Drupal::service('plugin.manager.uc_payment.method')
            ->createFromOrder($order);
        $this->configuration = $plugin->getConfiguration();

        $orderTotal = uc_currency_format($order->getTotal(), false, false, '.');

        if ($order->getStateId() != $this->configuration['order_status_after_pay']) {
            if ($this->checkIpnRequestIsValid($posted)) {
                $comment = $this->t('Paid by Lifepay method use payment type "@type", order #@order and transaction number in Lifepay system is @transaction. Also look payment callback log: @dump ',
                    [
                        '@type' => Html::escape($posted['service_id']),
                        '@order' => Html::escape($posted['order_id']),
                        '@transaction' => Html::escape($posted['tid']),
                        '@dump' => Html::escape(print_r($posted, true)),
                    ]);
                uc_payment_enter($order->id(), 'lifepay', $orderTotal, $order->getOwnerId(), null, $comment);
                $order->setStatusId($this->configuration['order_status_after_pay'])->save();
                die('success');
            } else {
                $this->onCancel($order, $request);
                return;
            }
        } else {
            \Drupal::messenger()->addMessage($this->t('Order complete! Thank you for payment'), 'success');
            return $this->cartManager->completeSale($order);
        }
    }

    /**
     * Callback order success proceed
     * @param OrderInterface $order
     * @param Request $request
     */
    public function onReturnBack(Request $request)
    {
        $posted = $_REQUEST;

        // Try to get values from request
        $orderId = $posted['order_id'];
        $order = Order::load($orderId);

        \Drupal::messenger()->addMessage($this->t('Order complete! Thank you for payment'), 'success');
        return $this->cartManager->completeSale($order);
    }

    /**
     * Callback order fail proceed
     * @param OrderInterface $order
     * @param Request $request
     */
    public function onCancelBack(Request $request)
    {
        $posted = $_REQUEST;
        // Try to get values from request
        $orderId = $posted['order_id'];
        $order = Order::load($orderId);

        \Drupal::messenger()->addMessage($this->t('You have canceled checkout at Lifepay but may resume the checkout process here when you are ready.'),
            'error');
        uc_order_comment_save($order->id(), 0,
            $this->t('Order have not passed Lifepay declined. Lifepay make call back with data: @dump', [
                '@dump' => Html::escape(print_r($posted, true))
            ]));

        $url = '/cart/checkout/';
        $response = new RedirectResponse($url, 302);
        $response->send();
    }

    /**
     * Check LIFE PAY IPN validity
     * @param $posted
     * @return bool
     */
    private function checkIpnRequestIsValid($posted): bool
    {
        $url = \Drupal::request()->getHost().$_SERVER['REQUEST_URI'];
        $check = $posted['check'];
        unset($posted['check']);

        $signature = null;

        if ($this->configuration['api_version'] === '2.0') {
            $signature = $this->getSign2("POST", $url, $posted, $this->configuration['skey']);
        } elseif ($this->configuration['api_version'] === '1.0') {
            $signature = $this->getSign1($posted, $this->configuration['skey']);
        }

//        $this->logger($signature, '$signature');

        if ($signature === $check) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Part of sign generator
     * @param $queryData
     * @param string $argSeparator
     * @return string
     */
    private function httpBuildQueryRfc3986($queryData, string $argSeparator = '&'): string
    {
        $r = '';
        $queryData = (array) $queryData;
        if (!empty($queryData)) {
            foreach ($queryData as $k => $queryVar) {
                $r .= $argSeparator.$k.'='.rawurlencode($queryVar);
            }
        }
        return trim($r, $argSeparator);
    }

    /**
     * Sign generator
     * @param $method
     * @param $url
     * @param $params
     * @param $secretKey
     * @param false $skipPort
     * @return string
     */
    private function getSign2($method, $url, $params, $secretKey, bool $skipPort = false)
    {
        ksort($params, SORT_LOCALE_STRING);

        $urlParsed = parse_url($url);
        $path = $urlParsed['path'];
        $host = isset($urlParsed['host']) ? $urlParsed['host'] : "";
        if (isset($urlParsed['port']) && $urlParsed['port'] != 80) {
            if (!$skipPort) {
                $host .= ":{$urlParsed['port']}";
            }
        }

        $method = strtoupper($method) == 'POST' ? 'POST' : 'GET';

        $data = implode("\n",
            array(
                $method,
                $host,
                $path,
                $this->httpBuildQueryRfc3986($params)
            )
        );

        $signature = base64_encode(
            hash_hmac("sha256",
                "{$data}",
                "{$secretKey}",
                true
            )
        );

        return $signature;
    }

    /**
     * Add sign number two version
     * @param $posted
     * @param $key
     * @return string
     */
    private function getSign1($posted, $key): string
    {
        return rawurlencode(md5($posted['tid'].$posted['name'].$posted['comment'].$posted['partner_id'].
            $posted['service_id'].$posted['order_id'].$posted['type'].$posted['cost'].$posted['income_total'].
            $posted['income'].$posted['partner_income'].$posted['system_income'].$posted['command'].
            $posted['phone_number'].$posted['email'].$posted['resultStr'].
            $posted['date_created'].$posted['version'].$key));
    }

    /**
     * Logger function
     * @param  [type] $var  [description]
     * @param  string  $text  [description]
     * @return [type]       [description]
     */
    public function logger($var, $text = '')
    {
        $loggerFile = __DIR__.'/logger.log';
        if (is_object($var) || is_array($var)) {
            $var = (string) print_r($var, true);
        } else {
            $var = (string) $var;
        }
        $string = date("Y-m-d H:i:s")." - ".$text.' - '.$var."\n";
        file_put_contents($loggerFile, $string, FILE_APPEND);
    }
}
