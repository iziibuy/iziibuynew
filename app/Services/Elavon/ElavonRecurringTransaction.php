<?php

declare(strict_types=1);

namespace App\Services\Elavon;

use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\RecurringType;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\DataObject\ShopperInteraction;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;

class ElavonRecurringTransaction
{
    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function applyFirstSetup(array $body): array
    {
        $body['recurringType'] = RecurringType::FIRST;
        $body['shopperInteraction'] = ShopperInteraction::ECOMMERCE;

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function applySubsequentMerchantInitiated(array $body, string $apiBase, ?string $initialTransactionId): array
    {
        $body['recurringType'] = RecurringType::SUBSEQUENT;
        $body['shopperInteraction'] = ShopperInteraction::MAIL_ORDER;

        if (filled($initialTransactionId)) {
            $body['previousRecurringTransaction'] = self::transactionResourceUrl($apiBase, (string) $initialTransactionId);
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function appendThreeDSecureFromSession(array $body, PaymentSessionResponse $session): array
    {
        $threeDS = $session->getThreeDSecure();
        if (! $threeDS) {
            return $body;
        }

        $body['threeDSecure'] = [
            'directoryServerTransactionId' => $threeDS->getDirectoryServerTransactionId(),
            'transactionStatus' => $threeDS->getTransactionStatus(),
            'electronicCommerceIndicator' => $threeDS->getElectronicCommerceIndicator(),
            'authenticationValue' => $threeDS->getAuthenticationValue(),
            'protocolVersion' => $threeDS->getProtocolVersion(),
        ];

        return $body;
    }

    public static function transactionResourceUrl(string $apiBase, string $transactionId): string
    {
        $transactionId = trim($transactionId);
        if (str_contains($transactionId, '://')) {
            return $transactionId;
        }

        return rtrim($apiBase, '/').'/'.Endpoint::TRANSACTION.'/'.$transactionId;
    }

    /**
     * Run a zero-amount account verification to register the card for future MIT billing.
     *
     * @return array{status: bool, data: array<string, mixed>}
     */
    public static function verifyStoredCardForSubscriptionSetup(
        Converge2 $client,
        PaymentSessionResponse $session,
        array $baseTransactionBody,
        callable $transactionIsApproved
    ): array {
        $body = self::applyFirstSetup(
            self::appendThreeDSecureFromSession($baseTransactionBody, $session)
        );

        $response = $client->createSaleTransaction($body);

        if (! $response->isSuccess() || ! $transactionIsApproved($response)) {
            return [
                'status' => false,
                'data' => [
                    'message' => self::extractFailureMessage($response) ?: 'Card verification was not approved.',
                ],
            ];
        }

        return [
            'status' => true,
            'data' => ['transactionId' => (string) $response->getId()],
        ];
    }

    public static function extractFailureMessage(ResponseInterface $response): string
    {
        $parts = [];

        if ($response->hasFailures()) {
            foreach ($response->getFailures() as $failure) {
                $description = method_exists($failure, 'getDescription') ? (string) $failure->getDescription() : '';
                if ($description !== '') {
                    $parts[] = $description;
                }
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : '';
    }
}
