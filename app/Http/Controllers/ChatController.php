<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AI\ChatService;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        try {

            $service = new ChatService();
            $response = $service->respond($data['message']);

            return response()->json($response);

         } catch (\Exception $e) {
            // Log the error and return a friendly error response
            Log::error('GPT API Error: ' . $e->getMessage());

            smilify('success', 'You are successfully reconnected');
            notify()->success('Laravel Notify is awesome!');

            return response()->json([
                'status' => 'error',
                'message' => 'Sorry, an error occurred while processing your request. Please try again later. '.$e->getMessage(),
                'details' => $e->getMessage(),
            ], 500);
        }
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
