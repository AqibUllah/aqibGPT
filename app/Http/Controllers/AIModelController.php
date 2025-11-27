<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AIModelController extends Controller
{
    public function set(Request $request)
    {
        $request->validate([
            'ai_model' => 'required|in:ai-studio,gemini,openai,ollama',
        ]);

        session(['ai_model' => $request->string('ai_model')->toString()]);

        return back();
    }
}
