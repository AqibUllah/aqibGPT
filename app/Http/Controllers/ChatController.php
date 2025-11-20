<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        try {

            $service = new ChatService();
            $response = $service->respond($data['message'],[],['stream' => true]);

            return response()->json($response);

         } catch (\Exception $e) {
            // Log the error and return a friendly error response
            Log::error('GPT API Error: ' . $e->getMessage());

            LivewireAlert::title('Error!')
            ->error()
            ->show();

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
