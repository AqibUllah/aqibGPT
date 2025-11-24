<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AI\ChatService;
use Illuminate\Support\Facades\Log;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $service = new ChatService();

        return response()->stream(function () use ($service, $data) {
            try {
                $service->respond($data['message'], [], [
                    'stream' => true,
                    'on_chunk' => function ($text) {
                        echo 'data: ' . str_replace(["\r", "\n"], ' ', $text) . "\n\n";
                        if (function_exists('ob_flush')) {
                            ob_flush();
                        }
                        flush();
                    },
                ]);
                echo "event: done\n";
                echo "data: [DONE]\n\n";
                if (function_exists('ob_flush')) {
                    ob_flush();
                }
                flush();
            } catch (\Throwable $e) {
                Log::error('GPT API Error: ' . $e->getMessage());
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => $e->getMessage()]) . "\n\n";
                echo "data: [DONE]\n\n";
                if (function_exists('ob_flush')) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Optional: Clear the conversation history.
     */
    public function reset(): JsonResponse
    {
        session()->forget('gpt_messages');
        return response()->json(['status' => 'success', 'message' => 'Conversation reset.']);
    }
}
