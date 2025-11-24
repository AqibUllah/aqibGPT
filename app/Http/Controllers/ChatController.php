<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AI\ChatService;
use Illuminate\Support\Facades\Log;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function createSession(Request $request): JsonResponse
    {
        $user = auth()->user();
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        $session = \App\Models\ChatSession::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'title' => 'New Chat',
        ]);
        return response()->json([
            'uuid' => $session->uuid,
            'title' => $session->title,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|string',
        ]);

        $service = new ChatService();

        return response()->stream(function () use ($service, $data) {
            try {
                Log::info($data);
                $user = auth()->user();
                $uuid = $data['conversation_id'] ?? Str::uuid()->toString();
                $session = ChatSession::firstOrCreate(
                    ['uuid' => $uuid, 'user_id' => $user->id],
                    ['title' => Str::limit($data['message'], 80)]
                );
                Log::info('session:'.$session);

                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role' => 'user',
                    'content' => $data['message'],
                ]);

                echo "event: session\n";
                echo 'data: '.json_encode(['uuid' => $uuid, 'title' => (string)($session->title ?? \Illuminate\Support\Str::limit($data['message'], 48)), 'new' => $session->wasRecentlyCreated])."\n\n";
                if (function_exists('ob_flush')) { ob_flush(); }
                flush();

                $result = $service->respond($data['message'], [], [
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

                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role' => 'assistant',
                    'content' => (string)($result['content'] ?? ''),
                    'model' => (string)($result['model'] ?? ''),
                ]);
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
