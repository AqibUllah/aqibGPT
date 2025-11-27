<?php

use Livewire\Volt\Component;
use function Livewire\Volt\{state, mount, rules, usesFileUploads};
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use League\CommonMark\CommonMarkConverter;
use App\Services\AI\ChatService;
use Livewire\WithFileUploads;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;

new class extends Component {

    use WithFileUploads;

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

usesFileUploads();

state(['prompt' => '', 'messages' => [],'conversationId', 'isStreaming' => false, 'streamedContent' => '', 'attachments' => []]);
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

$removeAttachments = (fn($index) => array_splice($this->attachments,$index,1));

// The core action to send the message and get the AI response
$send = function () {
    $this->validate([
        'prompt' => 'required|string',
        'attachments.*' => 'nullable|image|max:1024', // 1MB Max
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
    // $this->messages[] = ['role' => 'bot', 'text' => '', 'streaming' => true];

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
        $this->conversationId = $uuid;

        \App\Models\ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $this->prompt,
        ]);

        $this->streamedContent = '';
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        if($this->attachments){
            $this->prompt = [$this->prompt];

            foreach ($this->attachments as $key => $attachment) {
                $this->prompt[] = new Blob(
                    mimeType: MimeType::IMAGE_JPEG,
                    data: base64_encode(
                        file_get_contents($attachment->getRealPath())
                    )
                );
            }

        }

        $result = $service->respond($this->prompt, [], [
            'stream' => true,
            'on_chunk' => function ($text) use ($converter) {
                $this->streamedContent .= $text;

                $lastIndex = count($this->messages) - 1;
                if ($lastIndex >= 0 && ($this->messages[$lastIndex]['role'] ?? null) === 'bot') {
                    $this->messages[$lastIndex]['text'] = $this->streamedContent;
                }

                $this->stream(
                    to: 'streamed-answer',
                    content: $converter->convert($this->streamedContent)->getContent(),
                    replace: true,
                );

                $this->js("scrollToBottom('{$this->conversationId}')");
            },
        ]);

        $this->prompt = '';

        // Store in the "photos" directory with the filename "avatar.png".

        // $this->attachment->storeAs('attachments', 'avatar');

        $aiText = $result['content'] ?? ($result['message'] ?? '');
        $lastIndex = count($this->messages) - 1;
        if ($lastIndex >= 0 && ($this->messages[$lastIndex]['role'] ?? null) === 'bot') {
            $this->messages[$lastIndex]['text'] = $aiText;
            unset($this->messages[$lastIndex]['streaming']);
        }
        $this->isStreaming = false;

        $this->stream(
            to: 'streamed-answer',
            content: '',
            replace: true,
        );

        \App\Models\ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => (string)($result['content'] ?? ''),
            'model' => (string)($result['model'] ?? ''),
        ]);

    } catch (\Throwable $e) {
        $this->isStreaming = false;
        $lastIndex = count($this->messages) - 1;
        if ($lastIndex >= 0 && ($this->messages[$lastIndex]['role'] ?? null) === 'bot') {
            $this->messages[$lastIndex]['text'] = 'Sorry, something went wrong. Please try again.';
            unset($this->messages[$lastIndex]['streaming']);
        } else {
            $this->messages[] = ['role' => 'bot', 'text' => 'Sorry, something went wrong. Please try again.'];
        }

        $this->stream(
            to: 'streamed-answer',
            content: '',
            replace: true,
        );

        LivewireAlert::title('Error!')
            ->error()
            ->text($e->getMessage())
            ->show();
    }

$id = $this->conversationId ?? 'default';
$this->js("(function(){var el=document.getElementById('chatContainer-" . $id . "'); if(el){el.scrollTop=el.scrollHeight;}})();");
};

?>


<div class=" dark:text-white text-neutral-500 flex items-center justify-center px-6"
     id="chat-interface-{{ $conversationId ?? 'default' }}">

    <div class="w-full max-w-2xl mb-12">

        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-center">How can I help you today?</h1>


        <div id="chatContainer-{{ $conversationId ?? 'default' }}"
             class="mt-8 {{ isset($messages) && count($messages) > 0 ? '' : 'hidden' }}">

            <div id="messages-{{ $conversationId ?? 'default' }}" class="space-y-4">
                @foreach(($messages ?? []) as $m)
                    <div class="flex {{ ($m['role'] ?? '') === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="flex items-start max-w-[100%]">
                            <div class="chat-message prose prose-slate dark:prose-invert max-w-none {{ ($m['role'] ?? '') === 'user' ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white prose-invert' : 'bg-neutral-100 dark:bg-neutral-800 text-gray-700 dark:text-white' }} rounded-2xl px-4 py-3 shadow-sm w-full">
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

            <span wire:stream="streamed-answer" class="text-left">{!! $streamedContent !!}</span>

            <!-- <div id="typingIndicator-{{ $conversationId ?? 'default' }}" class="hidden"></div> -->

        </div>


        <div class="mt-6 relative">

            <form id="chatForm-{{ $conversationId ?? 'default' }}"
                  wire:submit="send"
                  class="my-6 {{ isset($messages) && count($messages) > 0 ? 'fixed bottom-0' : '' }} max-w-2xl w-full
            >
{{--            <form id="chatForm-{{ $conversationId ?? 'default' }}" onsubmit="return sendMessage(event, '{{ $conversationId ?? 'default' }}')">--}}

                @csrf

                <div class="relative">
                    <div class="relative inline-block">
                        @if ($attachments && count($attachments) > 0)
                            <div class="flex space-x-2">
                                @foreach ($attachments as $index => $attachment)
                                    <div class="relative inline-block">
                                        <img
                                            class="w-10 h-10 border-2 rounded-lg object-cover"
                                            src="{{ $attachment->temporaryUrl() }}"
                                        >

                                        <button
                                            wire:click="removeAttachments({{ $index }})"
                                            type="button"
                                            class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-700"
                                        >
                                            ×
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                    </div>

                    <div
                        class="flex items-center bg-neutral-50 dark:bg-neutral-800 border border-neutral-100 dark:border-neutral-700 rounded-full shadow-xl">
                        <label
                            for="fileInput-{{ $conversationId ?? 'default' }}"
                            class="m-1 p-2 rounded-full bg-gray-300 hover:bg-gray-400 hover:text-gray-100 cursor-pointer
                                dark:bg-white text-gray-800 dark:text-black flex items-center justify-center transition-colors"
                        >
                            @if($isStreaming)
                                <i class="fas fa-spinner fa-spin"></i>
                            @else
                                <i class="fas fa-arrow-up"></i>
                            @endif

                            <flux:icon.plus />
                        </label>

                        <input
                            type="file"
                            multiple
                            wire:model="attachments"
                            @if($isStreaming) disabled @endif
                            id="fileInput-{{ $conversationId ?? 'default' }}"
                            class="hidden"
                        />

                        <input
                            type="text"
                            wire:model="prompt"
                            id="messageInput-{{ $conversationId ?? 'default' }}"
                            placeholder="Ask me anyting..."
                            class="flex-1 bg-transparent border-none focus:outline-none w-full text-gray-700 dark:text-white placeholder:text-neutral-400 px-4 py-3"
                            autocomplete="off"
                            @if($isStreaming) disabled @endif
                        >


                        <button

                            type="submit"
                            @if($isStreaming) disabled @endif
                            id="sendButton-{{ $conversationId ?? 'default' }}"
                            class="m-1 p-2 rounded-full bg-black hover:bg-gray-900 hover:cursor-pointer dark:bg-white text-gray-200 dark:text-black flex items-center justify-center transition-colors"
                        >

                            @if($isStreaming)
                                <i class="fas fa-spinner fa-spin"></i>
                            @else
                                <i class="fas fa-arrow-up"></i>
                            @endif

                            <flux:icon.paper-airplane />

                        </button>

                    </div>

                </div>

            </form>
            @error('attachment') <span class="error">{{ $message }}</span> @enderror

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
        highlightChatCode();
    };

    function highlightChatCode() {
        if (window.hljs) {
            document.querySelectorAll('#messages-{{ $conversationId ?? 'default' }} pre code').forEach((block) => {
                window.hljs.highlightElement(block);
            });
        }
    }

    document.addEventListener('livewire:load', () => {
        highlightChatCode();
        if (window.Livewire) {
            Livewire.hook('message.processed', () => highlightChatCode());
        }
    });
</script>
