<?php

namespace App\Http\Controllers;

use App\Models\GatewayWebhookEvent;
use App\Models\Subscription;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoService $mercadoPago
    ): JsonResponse {
        $secret = trim(
            (string) config(
                'services.mercadopago.webhook_secret'
            )
        );

        if ($secret === '') {
            return response()->json([
                'received' => false,
                'message' => 'Webhook secret is not configured.',
            ], 500);
        }

        /*
         * O ID usado para VALIDAR a assinatura deve vir
         * exclusivamente do query param data.id.
         *
         * O simulador do Mercado Pago pode enviar o ID
         * apenas no corpo. Nesse caso, o par "id:"
         * não participa do manifest HMAC.
         */
        $signatureDataId =
            $this->queryDataId(
                $request
            );

        /*
         * Já para processar a notificação, podemos usar
         * o ID da query ou o ID enviado no corpo.
         */
        $dataId =
            $signatureDataId
            ?: data_get(
                $request->json()->all(),
                'data.id'
            );

        if (
            ! is_string($dataId)
            || $dataId === ''
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Order ID not found.',
            ], 422);
        }

        if (
            ! $this->hasValidSignature(
                $request,
                $signatureDataId,
                $secret
            )
        ) {
            return response()->json([
                'received' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        $payload =
            $request->json()->all();

        $eventType =
            (string) (
                data_get(
                    $payload,
                    'action'
                )
                ?: data_get(
                    $payload,
                    'type'
                )
                ?: 'order'
            );

        $providerEventId =
            (string) (
                data_get(
                    $payload,
                    'id'
                )
                ?: hash(
                    'sha256',
                    $eventType
                    .'|'
                    .$dataId
                )
            );

        $webhookEvent =
            GatewayWebhookEvent::firstOrCreate(
                [
                    'provider' => 'mercadopago',

                    'provider_event_id' => $providerEventId,
                ],
                [
                    'event_type' => $eventType,

                    'payload' => $payload,
                ]
            );

        if ($webhookEvent->processed_at) {
            return response()->json([
                'received' => true,
                'duplicate' => true,
            ]);
        }

        try {
            $order =
                $mercadoPago
                    ->getOrder(
                        $dataId
                    );

            $subscription =
                $this->resolveSubscription(
                    $order
                );

            DB::transaction(
                function () use (
                    $webhookEvent,
                    $eventType,
                    $payload,
                    $subscription,
                    $order,
                    $mercadoPago
                ): void {
                    $webhookEvent->update([
                        'event_type' => $eventType,

                        'payload' => $payload,
                    ]);

                    if ($subscription) {
                        $mercadoPago
                            ->syncPixOrder(
                                $subscription,
                                $order
                            );
                    }

                    $webhookEvent->update([
                        'processed_at' => now(),
                    ]);
                }
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return response()->json([
                'received' => false,
                'message' => 'Webhook could not be processed.',
            ], 500);
        }

        return response()->json([
            'received' => true,
        ]);
    }

    private function resolveSubscription(
        array $order
    ): ?Subscription {
        $externalReference =
            data_get(
                $order,
                'external_reference'
            );

        if (
            is_string(
                $externalReference
            )
            && preg_match(
                '/^negozia-pix-subscription-(\d+)$/',
                $externalReference,
                $matches
            )
        ) {
            return Subscription::find(
                (int) $matches[1]
            );
        }

        $orderId =
            data_get(
                $order,
                'id'
            );

        if (
            ! is_string($orderId)
            || $orderId === ''
        ) {
            return null;
        }

        return Subscription::query()
            ->where(
                'payment_provider',
                'mercadopago_pix'
            )
            ->where(
                'provider_checkout_id',
                $orderId
            )
            ->first();
    }

    private function hasValidSignature(
        Request $request,
        ?string $dataId,
        string $secret
    ): bool {
        $signature =
            (string) $request->header(
                'x-signature',
                ''
            );

        $requestId =
            (string) $request->header(
                'x-request-id',
                ''
            );

        if ($signature === '') {
            return false;
        }

        $parts = [];

        foreach (
            explode(
                ',',
                $signature
            ) as $part
        ) {
            [$key, $value] =
                array_pad(
                    explode(
                        '=',
                        trim($part),
                        2
                    ),
                    2,
                    null
                );

            if (
                $key
                && $value
            ) {
                $parts[$key] =
                    $value;
            }
        }

        $timestamp =
            $parts['ts']
            ?? null;

        $receivedHash =
            $parts['v1']
            ?? null;

        if (
            ! $timestamp
            || ! $receivedHash
        ) {
            return false;
        }

        /*
         * O manifest segue exatamente a regra oficial:
         *
         * id:<data.id>;
         * request-id:<x-request-id>;
         * ts:<timestamp>;
         *
         * Se algum valor não vier na notificação,
         * aquele par é omitido.
         */
        $manifest = '';

        if (
            is_string($dataId)
            && $dataId !== ''
        ) {
            $manifest .=
                'id:'
                .$dataId
                .';';
        }

        if ($requestId !== '') {
            $manifest .=
                'request-id:'
                .$requestId
                .';';
        }

        $manifest .=
            'ts:'
            .$timestamp
            .';';

        $expectedHash =
            hash_hmac(
                'sha256',
                $manifest,
                $secret
            );

        return hash_equals(
            $expectedHash,
            $receivedHash
        );
    }

    private function queryDataId(
        Request $request
    ): ?string {
        /*
         * O Mercado Pago documenta o parâmetro como
         * data.id.
         *
         * Em PHP, entretanto, nomes com ponto podem
         * aparecer normalizados como data_id.
         *
         * Aceitamos ambos para montar exatamente o
         * mesmo manifest utilizado pelo Mercado Pago.
         */
        $query =
            (string) $request->server(
                'QUERY_STRING',
                ''
            );

        if (
            preg_match(
                '/(?:^|&)(?:data\.id|data_id)=([^&]+)/',
                $query,
                $matches
            )
        ) {
            return urldecode(
                $matches[1]
            );
        }

        foreach (
            [
                'data_id',
                'data.id',
            ] as $key
        ) {
            $value =
                $request->query(
                    $key
                );

            if (
                is_string($value)
                && $value !== ''
            ) {
                return $value;
            }
        }

        return null;
    }
}
