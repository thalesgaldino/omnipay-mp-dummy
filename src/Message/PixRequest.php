<?php

namespace Omnipay\Dummy\Message;

use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\MercadoPagoConfig;
use SilverStripe\Omnipay\GatewayInfo;

/**
 * Dummy Authorize/Purchase Request
 *
 * This is the request that will be called for any transaction which submits a pix - brazilian payment method,
 * including `authorize` and `purchase`
 */
class PixRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('amount');

        $gatewayParams = GatewayInfo::getParameters("Dummy");
        $email = $gatewayParams['pay_email'];

        if ($this->getParameter("card") != null) {
            $params = $this->getParameter("card")->getParameters();
            $email = $params['email'];
        }

        $orderId = (int)explode('-', $this->getTransactionId())[0];

        return array(
            'transaction_amount' => $this->getAmount(),
            "payer_email" => $email,
            "payment_method_id" => "pix",
            "order_id" => $orderId
        );
    }

    public function sendData($data)
    {
        $paymentMethod = $data['payment_method_id'];
        $transactionAmount = $data['transaction_amount'];
        $email = $data['payer_email'];
        $order_id = $data['order_id'];

        $gatewayParams = GatewayInfo::getParameters("Dummy");
        $accessToken = $gatewayParams['access_token'];

        MercadoPagoConfig::setAccessToken($accessToken);

        $client = new PaymentClient();
        $request_options = new RequestOptions();
        $request_options->setCustomHeaders(["X-Idempotency-Key: " . $order_id]);


        $req = [
            "payment_method_id" => $paymentMethod,
            "transaction_amount" => (float) $transactionAmount,
            "description" => "Compras Pedido #" . $order_id,
            "payer" => [
                "email" => $email,
                "identification" => [
                    "type" => "CPF",
                    "number" => "19119119100"
                ]
            ]
        ];

        $payment = $client->create($req, $request_options);

        $data['message'] = $payment->status === "pending" ? 'Success' : 'Failure';
        $data['status'] = $payment->status;
        $data['success'] = $payment->status === "pending";
        $data['ticket_url'] = $payment->point_of_interaction->transaction_data->ticket_url;

        return $this->response = new Response($this, $data);
    }
}
