<?php

namespace Omnipay\Dummy\Message;

use Exception;
use MercadoPago\Exceptions\MPApiException;
use Omnipay\Common\Message\ResponseInterface;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\MercadoPagoConfig;
use SilverStripe\Omnipay\GatewayInfo;
use SilverShop\Cart\ShoppingCart;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Dummy Authorize/Purchase Request
 *
 * This is the request that will be called for any transaction which submits a pix - brazilian payment method,
 * including `authorize` and `purchase`
 */
class PixRequest extends PixAbstractRequest
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

        $id_type_selected = "CPF";

        if ($this->getParameter("IdTypeSelected") == "option2") {
            $id_type_selected = "CNPJ";
        }
        $identification_id = $this->getParameter("IdentificationId");

        return array(
            'transaction_amount' => $this->getAmount(),
            "payer_email" => $email,
            "payment_method_id" => "pix",
            "order_id" => $orderId,
            "id_type_selected" => $id_type_selected,
            "identification_id" => $identification_id
        );
    }

    public function sendData($data)
    {
        $paymentMethod = $data['payment_method_id'];
        $transactionAmount = $data['transaction_amount'];
        $email = $data['payer_email'];
        $order_id = $data['order_id'];
        $id_type_selected = $data['id_type_selected'];
        $identification_id = $data['identification_id'];

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
                    "type" => $id_type_selected,
                    "number" => $identification_id
                ]
            ]
        ];

        $payment = null;
        $data['message'] = 'Falha no pagamento na última tentativa. Tente novamente.';
        try {
            $payment = $client->create($req, $request_options);
            if ($payment !== null) {
                $data['message'] = $payment->status === "pending" ? 'Pagamento em andamento' : 'Falha no pagamento na última tentativa. Tente novamente.';
                $data['status'] = $payment->status;
                $data['success'] = $payment->status === "pending";
                $data['ticket_url'] = $payment->point_of_interaction->transaction_data->ticket_url ?? "";
            }
        } catch (MPApiException $e) {
            Injector::inst()->get(LoggerInterface::class)->error("Payment Error: " . $e->getMessage() . " status code:" . $e->getStatusCode());
            $data['status_code'] = $e->getStatusCode();
            $data['message'] = $e->getStatusCode() . ': Falha no pagamento na última tentativa. Tente novamente.';
        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error("Error: " . $e->getMessage());
            $data['status_code'] = $e->getStatusCode();
            $data['message'] = $e->getStatusCode() . ': Falha no pagamento na última tentativa. Tente novamente.';
        }

        if ($payment == null) {
            $data['status'] = null;
            $data['success'] = false;
            $data['ticket_url'] = null;
        }

        return $this->response = new Response($this, $data);
    }
}
