<?php

use Livewire\Volt\Component;
use function Livewire\Volt\{state, mount, rules};
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use League\CommonMark\CommonMarkConverter;
use App\Services\AI\ChatService;

new class extends Component {

    public $messages = [];
    public $conversationId;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($conversationId = null)
    {
        $this->conversationId = $conversationId ?? \Illuminate\Support\Str::uuid();

        $this->messages = collect([]);

        // 2. Load existing or default messages
        if (is_null($this->messages)) {
            // We initialize messages as a Collection for easy handling and pre-load a welcome message
            $this->messages = Collection::make([
                ['role' => 'bot', 'text' => 'Hello! I am your AI assistant. How can I help you today?'],
            ]);
        }
    }


};


state(['prompt' => '', 'messages' => [],'conversationId', 'isStreaming' => false, 'streamedContent' => '']);
mount(function () {
    $uuid = request('session');
    if ($uuid) {
        $this->conversationId = $uuid;
        $session = \App\Models\ChatSession::where('uuid', $uuid)->where('user_id', auth()->id())->first();
        if ($session) {
            $msgs = \App\Models\ChatMessage::where('chat_session_id', $session->id)->orderBy('id')->get();
            $this->messages = $msgs->map(function ($m) {
                return ['role' => $m->role, 'text' => $m->content];
            })->toArray();
        }
    }
});

// Rules for input validation
rules(['prompt' => ['required', 'string', 'max:2000']]);

// --- Actions ---

// The core action to send the message and get the AI response
$send = function () {
    $this->validate([
        'prompt' => 'required|string',
//        'conversationId' => 'nullable|string',
    ]);

    $this->prompt = trim($this->prompt);
    if ($this->prompt === '') {
        return;
    }

    $this->messages[] = ['role' => 'user', 'text' => $this->prompt];
    $this->isStreaming = true;
    $this->streamedContent = '';

    // Add placeholder for bot message
    $this->messages[] = ['role' => 'bot', 'text' => '', 'streaming' => true];

    $this->js('$wire.ask()');

    };

$ask = function()
{
    $this->js("scrollToBottom('{$this->conversationId}')");

    try {

        $service = new ChatService();

        $uuid = $this->conversationId ?? Str::uuid()->toString();
        $session = \App\Models\ChatSession::firstOrCreate(
            ['uuid' => $uuid, 'user_id' => auth()->id()],
            ['title' => Str::limit($this->prompt, 80)]
        );

        \App\Models\ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $this->prompt,
        ]);

        $result = $service->respond($this->prompt, [], [
            'stream' => true,
//            'on_chunk' => function ($text) {
//                echo 'data: ' . str_replace(["\r", "\n"], ' ', $text) . "\n\n";
//                if (function_exists('ob_flush')) {
//                    ob_flush();
//                }
//                flush();
//            },
        ]);

        $this->prompt = '';

        $aiText = $result['content'] ?? ($result['message'] ?? '');
        $this->streamedContent .= $aiText;
        if ($aiText !== '') {
            $this->messages[] = ['role' => 'bot', 'text' => $this->streamedContent];
            $this->stream(
                to: 'streamed-answer',
                content: $this->streamedContent,
                replace: true,
            );
        }

        \App\Models\ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => (string)($result['content'] ?? ''),
            'model' => (string)($result['model'] ?? ''),
        ]);

    } catch (\Throwable $e) {
        $this->messages[] = ['role' => 'bot', 'text' => 'Sorry, something went wrong. <br>'.$e->getMessage()];

        LivewireAlert::title('Error!')
            ->error()
            ->show();
    }

    $id = $this->conversationId ?? 'default';
    $this->js("(function(){var el=document.getElementById('chatContainer-" . $id . "'); if(el){el.scrollTop=el.scrollHeight;}})();");
};

// Handle the streamed response
$handleStream = function ($chunk) {
    $this->streamedContent .= $chunk;

    // Update the last message (bot message) with streamed content
    $lastIndex = count($this->messages) - 1;
    if ($lastIndex >= 0 && isset($this->messages[$lastIndex]['role']) && $this->messages[$lastIndex]['role'] === 'bot') {
        $this->messages[$lastIndex]['text'] = $this->streamedContent;
    }

    $this->js("scrollToBottom('{$this->conversationId}')");
};

// Handle stream completion
$streamComplete = function () {
    $this->isStreaming = false;

    // Remove streaming flag from the last message
    $lastIndex = count($this->messages) - 1;
    if ($lastIndex >= 0 && isset($this->messages[$lastIndex]['role']) && $this->messages[$lastIndex]['role'] === 'bot') {
        unset($this->messages[$lastIndex]['streaming']);

        // Save the completed message to database
        try {
            $session = \App\Models\ChatSession::firstOrCreate(
                ['uuid' => $this->conversationId],
                ['user_id' => auth()->id(), 'uuid' => $this->conversationId]
            );

            // Save user message (second to last)
            if (count($this->messages) >= 2) {
                $userMessage = $this->messages[count($this->messages) - 2];
                \App\Models\ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role' => 'user',
                    'content' => $userMessage['text']
                ]);
            }

            // Save bot message (last)
            $botMessage = $this->messages[count($this->messages) - 1];
            \App\Models\ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'bot',
                'content' => $botMessage['text']
            ]);

        } catch (\Throwable $e) {
            \Log::error('Failed to save chat message: ' . $e->getMessage());
        }
    }
};

// Handle stream error
$streamError = function ($error) {
    $this->isStreaming = false;

    // Update the last message with error
    $lastIndex = count($this->messages) - 1;
    if ($lastIndex >= 0 && isset($this->messages[$lastIndex]['role']) && $this->messages[$lastIndex]['role'] === 'bot') {
        $this->messages[$lastIndex]['text'] = 'Sorry, there was an error processing your request. Please try again.';
        unset($this->messages[$lastIndex]['streaming']);
    }

    LivewireAlert::title('Stream Error!')
        ->error()
        ->show();

    $this->js("scrollToBottom('{$this->conversationId}')");
};

?>


<div class=" dark:text-white text-neutral-500 flex items-center justify-center px-6"
     id="chat-interface-{{ $conversationId ?? 'default' }}">

    <div class="w-full max-w-2xl">

        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-center">How can I help you today?</h1>


        <div id="chatContainer-{{ $conversationId ?? 'default' }}"
             class="mt-8 {{ isset($messages) && count($messages) > 0 ? '' : 'hidden' }}">

            <div id="messages-{{ $conversationId ?? 'default' }}" class="space-y-4">
                @foreach(($messages ?? []) as $m)
                    <div class="flex {{ ($m['role'] ?? '') === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="flex items-start max-w-[80%]">
                            <div class="{{ ($m['role'] ?? '') === 'user' ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white' : 'bg-neutral-100 dark:bg-neutral-800 text-gray-700 dark:text-white' }} rounded-2xl px-4 py-3 shadow-sm">
                                @php
                                    if (!function_exists('render_markdown')) {
                                        function render_markdown(string $text): string {
                                            static $converter = null;
                                            if ($converter === null) {
                                                $converter = new CommonMarkConverter([
                                                    'html_input' => 'strip',
                                                    'allow_unsafe_links' => false,
                                                ]);
                                            }
                                            return $converter->convert($text)->getContent();
                                        }
                                    }
                                    $html = render_markdown($m['text'] ?? '');
                                @endphp
                                {!! $html !!}

                                @if(($m['streaming'] ?? false) && $isStreaming)
                                    <div class="inline-block ml-1 typing-indicator">
                                        <span class="dot"></span>
                                        <span class="dot"></span>
                                        <span class="dot"></span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($isStreaming)
                <div class="flex justify-start">
                    <div class="bg-neutral-100 dark:bg-neutral-800 rounded-2xl px-4 py-3 shadow-sm">
                        <div class="typing-indicator">
                            <span class="dot"></span>
                            <span class="dot"></span>
                            <span class="dot"></span>
                        </div>
                    </div>
                </div>
            @endif

            <span wire:stream="streamed-answer">{!! $streamedContent !!}</span>

            <div id="typingIndicator-{{ $conversationId ?? 'default' }}" class="hidden"></div>

        </div>


        <div class="mt-6">

            <form id="chatForm-{{ $conversationId ?? 'default' }}"
                  wire:submit="send"
                  wire:stream="handleStream"
                  wire:stream.complete="streamComplete"
                  wire:stream.error="streamError"
            >
{{--            <form id="chatForm-{{ $conversationId ?? 'default' }}" onsubmit="return sendMessage(event, '{{ $conversationId ?? 'default' }}')">--}}

                @csrf

                <div class="relative">

                    <div
                        class="flex items-center bg-neutral-100 dark:bg-neutral-800 border border-neutral-100 dark:border-neutral-700 rounded-xl shadow-sm">

                        <input
                            type="text"
                            wire:model="prompt"
                            id="messageInput-{{ $conversationId ?? 'default' }}"
                            placeholder="Write something..."
                            class="flex-1 bg-transparent border-none focus:outline-none w-full text-gray-700 dark:text-white placeholder:text-neutral-400 px-4 py-3"
                            autocomplete="off"
                            @if($isStreaming) disabled @endif
                        >


                        <button

                            type="submit"
                            @if($isStreaming) disabled @endif
                            id="sendButton-{{ $conversationId ?? 'default' }}"
                            class="m-2 px-5 h-9 rounded-md bg-neutral-200 hover:bg-neutral-300 hover:cursor-pointer dark:bg-neutral-900 text-neutral-900 dark:text-neutral-500 flex items-center justify-center transition-colors"
                        >

                            @if($isStreaming)
                                <i class="fas fa-spinner fa-spin"></i>
                            @else
                                <i class="fas fa-arrow-up"></i>
                            @endif

                            send

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

<script>
    // Simple JavaScript to ensure the chat scrolls to the bottom on load
    function scrollToBottom(conversationId) {
        const id = conversationId || 'default';
        const chatContainer = document.getElementById('chatContainer-' + id);
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    }
    window.onload = function () {
        const chatContainer = document.getElementById('chatContainer-{{ $conversationId ?? 'default' }}');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    };

    // Livewire stream event listeners
    document.addEventListener('livewire:initialized', () => {
        const component = @this;

        // Handle stream chunks
        component.on('stream-chunk', (chunk) => {
            // This is handled automatically by wire:stream
            scrollToBottom('{{ $conversationId ?? 'default' }}');
        });
    });
</script>
