<?php

namespace Drupal\uc_lifepay\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\uc_order\Entity\OrderStatus;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;

/**
 * Defines the lifepay payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "lifepay",
 *   name = @Translation("lifepay"),
 *   redirect = "\Drupal\uc_lifepay\Form\lifepayForm",
 * )
 */
class Lifepay extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface
{

    /**
     * @var string payment url
     */
    protected $url = 'https://partner.life-pay.ru/alba/input/';

    /**
     * Display label for payment method
     * @param string $label
     * @return mixed
     */
    public function getDisplayLabel($label)
    {
        $build['label'] = [
            '#prefix' => '<div class="uc-lifepay">',
            '#plain_text' => $label,
            '#suffix' => '</div>',
        ];
        $build['image'] = [
            '#theme' => 'image',
            '#uri' => drupal_get_path('module', 'uc_lifepay').'/images/logo.png',
            '#alt' => $this->t('Lifepay'),
            '#attributes' => ['class' => ['uc-lifepay-logo']]
        ];

        return $build;
    }

    /**
     * Return default module settengs
     * @return array
     */
    public function defaultConfiguration()
    {

        $returned = [
                'service_id' => '',
                'key' => '',
                'skey' => '',
                'shop_hostname' => 'Store www...., order #',
                'api_version' => '1.0',
                'payment_method' => 'full_prepayment',
                'vat_products' => 'none',
                'vat_delivery' => 'none',
                'unit_products' => 'piece',
                'unit_delivery' => 'service',
                'object_products' => 'commodity',
                'object_delivery' => 'service',
                'send_email' => true,
                'order_status_after_pay' => 'processing'
            ] + parent::defaultConfiguration();

        return $returned;

    }

    /**
     * Setup (settings) form for module
     * @param array $form
     * @param FormStateInterface $form_state
     * @return array
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['service_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Service ID"),
            '#description' => $this->t("Visit merchant interface in Lifepay site https://home.life-pay.ru/alba/index/ and copy Service ID field"),
            '#default_value' => $this->configuration['service_id'],
            '#required' => true,
        ];

        $form['key'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Service key"),
            '#description' => $this->t("Input service key here"),
            '#default_value' => $this->configuration['key'],
            '#required' => true,
        ];

        $form['skey'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Service key"),
            '#description' => $this->t("Input secret key here"),
            '#default_value' => $this->configuration['skey'],
            '#required' => true,
        ];

        $form['shop_hostname'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Hostname and order description"),
            '#description' => $this->t("Order description with host name"),
            '#default_value' => $this->configuration['shop_hostname'],
            '#required' => false,
        ];

        $form['api_version'] = [
            '#type' => 'select',
            '#title' => $this->t("API version"),
            '#description' => $this->t("See you API version in partner (merchant) interface"),
            '#options' => self::getApiVersionOptions(),
            '#default_value' => $this->configuration['api_version'],
            '#required' => true,
        ];

        $form['payment_method'] = [
            '#type' => 'select',
            '#title' => $this->t("Payment method"),
            '#description' => $this->t("Select payment method usually full_prepayment"),
            '#options' => self::getPaymentMethodOptions(),
            '#default_value' => $this->configuration['payment_method'],
            '#required' => true,
        ];

        $form['vat_products'] = [
            '#type' => 'select',
            '#title' => $this->t("VAT for products"),
            '#description' => $this->t("Select VAT for products"),
            '#options' => self::getVatOptions(),
            '#default_value' => $this->configuration['vat_products'],
            '#required' => true,
        ];

        $form['vat_delivery'] = [
            '#type' => 'select',
            '#title' => $this->t("VAT for delivery"),
            '#description' => $this->t("Select VAT for delivery"),
            '#options' => self::getVatOptions(),
            '#default_value' => $this->configuration['vat_delivery'],
            '#required' => true,
        ];

        $form['unit_products'] = [
            '#type' => 'select',
            '#title' => $this->t("Object for products"),
            '#description' => $this->t("Select units for products"),
            '#options' => self::getUnitOptions(),
            '#default_value' => $this->configuration['unit_products'],
            '#required' => true,
        ];

        $form['unit_delivery'] = [
            '#type' => 'select',
            '#title' => $this->t("Units for delivery"),
            '#description' => $this->t("Select units for delivery"),
            '#options' => self::getUnitOptions(),
            '#default_value' => $this->configuration['unit_delivery'],
            '#required' => true,
        ];

        $form['object_products'] = [
            '#type' => 'select',
            '#title' => $this->t("Object for products"),
            '#description' => $this->t("Select objects for products"),
            '#options' => self::getPaymentObjectOptions(),
            '#default_value' => $this->configuration['object_products'],
            '#required' => true,
        ];

        $form['object_delivery'] = [
            '#type' => 'select',
            '#title' => $this->t("Object for delivery"),
            '#description' => $this->t("Select objects for delivery"),
            '#options' => self::getPaymentObjectOptions(),
            '#default_value' => $this->configuration['object_delivery'],
            '#required' => true,
        ];

        $form['send_email'] = [
            '#type' => 'checkbox',
            '#title' => $this->t("Attach email in order"),
            '#description' => $this->t("Attach email in order or not"),
            '#value' => true,
            '#false_values' => [false],
            '#default_value' => $this->configuration['send_email'],
            '#required' => false,
        ];

        $form['order_status_after_pay'] = [
            '#type' => 'select',
            '#title' => $this->t("Order status after successfully payment"),
            '#description' => $this->t("Set order status after successfully payment"),
            '#options' => OrderStatus::getOptionsList(),
            '#default_value' => $this->configuration['order_status_after_pay'],
            '#required' => true,
        ];

        return $form;

    }

    /**
     * Setting save submit form
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        // Parent method will reset configuration array and further condition will
        // fail.
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['service_id'] = $values['service_id'];
            $this->configuration['key'] = $values['key'];
            $this->configuration['skey'] = $values['skey'];
            $this->configuration['shop_hostname'] = $values['shop_hostname'];
            $this->configuration['api_version'] = $values['api_version'];
            $this->configuration['payment_method'] = $values['payment_method'];
            $this->configuration['vat_products'] = $values['vat_products'];
            $this->configuration['vat_delivery'] = $values['vat_delivery'];
            $this->configuration['unit_products'] = $values['unit_products'];
            $this->configuration['unit_delivery'] = $values['unit_delivery'];
            $this->configuration['object_products'] = $values['object_products'];
            $this->configuration['object_delivery'] = $values['object_delivery'];
            $this->configuration['send_email'] = $values['send_email'];
            $this->configuration['order_status_after_pay'] = $values['order_status_after_pay'];
        }
    }

    /**
     * Generate payment form
     * {@inheritdoc}
     */
    public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = null)
    {
        // Get config
        $configs = $this->configuration;

        // Get amount
        $totalPriceNumber = uc_currency_format($order->getTotal(), false, false, '.');

        // Get Email
        $customerEmail = $order->getEmail();

        // Get order ID
        $orderId = $order->id();

        $items = [];
        $items['items'] = self::getOrderItems($order, $configs);

        $data = array(
            'key' => $configs['key'],
            'cost' => $totalPriceNumber,
            'order_id' => $orderId,
            'name' => $configs['shop_hostname'].$orderId,
            'invoice_data' => json_encode($items),
        );

        if ($configs['send_email']) {
            $data['email'] = $customerEmail;
        }

        if ($configs['api_version'] === '2.0') {
            unset($data['key']);
            $data['version'] = $configs['api_version'];
            $data['service_id'] = $configs['service_id'];
            $current_uri = \Drupal::request()->getSchemeAndHttpHost().\Drupal::request()->getRequestUri();
            $data['check'] = $this->getSign2('POST', $this->url, $data, $configs['skey']);
        }

//       print <<<EOL
//        <!doctype html>
//<html lang="en">
//<head>
//  <meta charset="utf-8">
//  <meta name="viewport" content="width=device-width, initial-scale=1">
//  <title>Payment form Lifepay payment system</title>
//  <meta name="description" content="Payment form lifepay">
//  <meta name="author" content="SitePoint">
//</head>
//<body>
//EOL;
//        print "<form action='{$this->payment_url}' method='post' id='lifepay-payment-form'>";
//        foreach ($data as $key => $value) {
//            print "<input type='hidden' value='{$value}' name='$key'>";
//        }
//        print <<<EOL
//<div class="buttons">
//    <div class="pull-right">
//      <input type="submit" style="visibility:hidden;" value="Paynow"/>
//    </div>
//  </div>
//</form>
//<script type="text/javascript">
//    let paymentForm = document.getElementById('lifepay-payment-form')
//    paymentForm.submit()
//</script>
//</body>
//</html>
//EOL;

        return $this->generateForm($data, $this->url);
    }


    /**
     * Get order items
     * @param $order
     * @param array $config
     * @return array
     */
    public static function getOrderItems($order, array $config): array
    {
        $itemsArray = [];

        // Products lines
        foreach ($order->products as $product) {

            $price = (float) uc_currency_format($product->price->value, false, false, '.');
            $qty = (int) $product->qty->value;
            $total = $price * $qty;
            $itemsArray[] = [
                'code' => $product->model->value,
                'name' => $product->title->value,
                'price' => $price,
                'unit' => $config['unit_products'],
                'payment_object' => $config['object_products'],
                'payment_method' => $config['payment_method'],
                'quantity' => $qty,
                'sum' => $total,
                'vat_mode' => $config['vat_products'],
            ];
        }

        // Shipping lines
        foreach ($order->getLineItems() as $item) {
            $price = uc_currency_format($item['amount'], false, false, '.');
            if ($item['type'] == 'shipping') {
                $itemsArray[] = [
                    'code' => 'shipping',
                    'name' => $item['title'],
                    'price' => (float) $price,
                    'unit' => $config['unit_delivery'],
                    'payment_object' => $config['object_delivery'],
                    'payment_method' => $config['payment_method'],
                    'quantity' => 1,
                    'sum' => (float) $price,
                    'vat_mode' => $config['vat_delivery'],
                ];
            }
        }

        return $itemsArray;
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
     * Generate payment form
     * @param $data
     * @param string $url
     *
     * @return mixed
     */
    public function generateForm($data, $url)
    {
        $form['#action'] = $url;
        foreach ($data as $k => $v) {
            if (!is_array($v)) {
                $form[$k] = [
                    '#type' => 'hidden',
                    '#value' => $v
                ];
            } else {
                $i = 0;
                foreach ($v as $val) {
                    $form[$k.'['.$i++.']'] = [
                        '#type' => 'hidden',
                        '#value' => $val
                    ];
                }
            }
        }
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit order'),
        ];
        return $form;
    }


    /**
     * Get VAT options
     * @return string[]
     */
    public static function getVatOptions(): array
    {
        return [
            'none' => 'НДС не облагается',
            'vat10' => '10%, включая',
            'vat110' => '10%, поверх',
            'vat18' => '18%, включая',
            'vat118' => '18%, поверх',
            'vat20' => '20%, включая',
            'vat120' => '20%, поверх',
        ];
    }

    /**
     * Get API version options
     * @return string[]
     */
    private static function getApiVersionOptions(): array
    {
        return [
            '1.0' => '1.0',
            '2.0' => '2.0',
        ];
    }

    /**
     * Get unit options
     * @return string[]
     */
    private function getUnitOptions(): array
    {
        return [
            'piece' => 'штука',
            'service' => 'услуга',
            'package' => 'комплект',
            'g' => 'грамм',
            'kg' => 'килограмм',
            't' => 'тонна',
            'ml' => 'миллилитр',
            'm3' => 'кубометр',
            'hr' => 'час',
            'm' => 'метр',
            'km' => 'километр',
        ];
    }

    /**
     * Get payment method options
     * @return string []
     * @since
     */
    public static function getPaymentMethodOptions(): array
    {
        return [
            'full_prepayment' => 'Предоплата 100%',
            'prepayment' => 'Предоплата',
            'advance' => 'Аванс',
            'full_payment' => 'Полный расчёт',
            'partial_payment' => 'Частичный расчёт',
            'credit' => 'Передача в кредит',
            'credit_payment' => 'Оплата кредита',
        ];
    }

    /**
     * Get payment object options
     * @return string[]
     * @since
     */
    public static function getPaymentObjectOptions(): array
    {
        return [
            'commodity' => 'Товар (Значение по умолчанию. Передается, в том числе, при отсутствии параметра)',
            'excise' => 'Подакциозный товар',
            'job' => 'Работа',
            'service' => 'Услуга',
            'gambling_bet' => 'Ставка азартной игры',
            'gambling_prize' => 'Выигрыш азартной игры',
            'lottery' => 'Лотерейный билет',
            'lottery_prize' => 'Выигрыш лотереи',
            'intellectual_activity' => 'Предоставление результатов интеллектуальной деятельности',
            'payment' => 'Платёж',
            'agent_commission' => 'Агентское вознаграждение',
            'composite' => 'Составной предмет расчёта',
            'another' => 'Другое',
        ];
    }
}
