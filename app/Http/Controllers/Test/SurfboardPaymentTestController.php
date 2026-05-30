<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Services\Surfboard\SurfboardClientFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class SurfboardPaymentTestController extends Controller
{
    public function __construct(
        protected SurfboardClientFactory $clientFactory,
    ) {}

    /**
     * Fetch Surfboard payment or order data by ID (local/testing only).
     *
     * Query: type=payment|order|order-status (default payment)
     *        context=platform|shop|external (default platform)
     *        shop_id= when context=shop
     *        booking= ULID when context=external
     *        sandbox=1 to force sandbox API keys
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            abort(404);
        }

        try {
            $client = $this->clientFactory->makeFromRequest($request);
            $result = match ($request->query('type', 'payment')) {
                'order' => $client->getOrder($id),
                'order-status' => $client->getOrderStatus($id),
                'payment' => $client->getPayment($id),
                default => throw new InvalidArgumentException(
                    'Invalid type. Use payment, order, or order-status.',
                ),
            };
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        $body = $result['body'];
        $apiStatus = is_array($body) ? ($body['status'] ?? null) : null;

        return response()->json([
            'success' => $result['http_status'] >= 200 && $result['http_status'] < 300,
            'type' => $request->query('type', 'payment'),
            'context' => $request->query('context', 'platform'),
            'id' => $id,
            'http_status' => $result['http_status'],
            'api_status' => $apiStatus,
            'data' => $body,
        ], $result['http_status'] >= 400 ? $result['http_status'] : 200);
    }
}
