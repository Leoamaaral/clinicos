<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppReminderResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsAppReminderResponseService $responseService): JsonResponse
    {
        $responseService->handle($request->all());

        return response()->json(['received' => true]);
    }
}
