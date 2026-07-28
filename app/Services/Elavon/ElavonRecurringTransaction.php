<?php

declare(strict_types=1);

namespace App\Services\Elavon;

use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\DataObject\ShopperInteraction;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;

class ElavonRecurringTransaction
{
    public const CREDENTIAL_ON_FILE_SUBSCRIPTION = 'subscription';

    public const SHOPPER_INTERACTION_MERCHANT_INITIATED = 'merchantInitiated';

    /**
     * Customer-initiated first transaction that opts the card into subscription MIT billing.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function applyFirstSetup(array $body): array
    {
        unset($body['recurringType'], $body['previousRecurringTransaction']);

        $body['credentialOnFileType'] = self::CREDENTIAL_ON_FILE_SUBSCRIPTION;
        $body['shopperInteraction'] = ShopperInteraction::ECOMMERCE;

        return $body;
    }

    /**
     * Merchant-initiated renewal using a vaulted card.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function applySubsequentMerchantInitiated(array $body, string $apiBase, ?string $initialTransactionId): array
    {
        unset($body['recurringType'], $body['previousRecurringTransaction']);

        $body['credentialOnFileType'] = self::CREDENTIAL_ON_FILE_SUBSCRIPTION;
        $body['shopperInteraction'] = self::SHOPPER_INTERACTION_MERCHANT_INITIATED;

        // $apiBase / $initialTransactionId are retained for call-site compatibility.
        // EU Converge expects credentialOnFileData (from the initial txn) when using
        // integrator-managed cards; stored-card renewals use the vaulted storedCard href.

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
