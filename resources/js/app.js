import Swal from 'sweetalert2'
import { marked } from "marked";
window.Swal = Swal

window.sendMessage = async function (e, conversationId) {
  e.preventDefault();
  console.log('here')
  const input = document.getElementById(`messageInput-${conversationId}`);
  const container = document.getElementById(`messages-${conversationId}`);
  const typing = document.getElementById(`typingIndicator-${conversationId}`);
  const chat = document.getElementById(`chatContainer-${conversationId}`);
  const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const message = (input.value || '').trim();
  if (!message) return false;
  chat && chat.classList.remove('hidden');
  typing && typing.classList.remove('hidden');
  const userBubble = document.createElement('div');
  userBubble.className = 'flex justify-end';
  userBubble.innerHTML = `<div class="flex items-start max-w-[80%]"><div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-2xl px-4 py-3 shadow-sm"><p>${escapeHtml(message)}</p></div><div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold ml-2 flex-shrink-0">You</div></div>`;
  container.appendChild(userBubble);
  input.value = '';
  try {
    const res = await fetch('/chat/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ message }),
    });

    const aiBubble = document.createElement('div');
    aiBubble.className = 'flex justify-start';
    const aiTextId = `aiText-${conversationId}-${Date.now()}`;
    aiBubble.innerHTML = `<div class="flex items-start max-w-[80%]"><div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold mr-2 flex-shrink-0">AI</div><div class="bg-gray-100 text-gray-800 rounded-2xl px-4 py-3 shadow-sm"><div id="${aiTextId}" class="bot-stream"></div></div></div>`;
    container.appendChild(aiBubble);

    const decoder = new TextDecoder();
    const reader = res.body.getReader();
    let buffer = '';
    let fullText = '';
    const target = document.getElementById(aiTextId);

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });

      const chunks = buffer.split('\n\n');
      buffer = chunks.pop();
      for (const chunk of chunks) {
        const lines = chunk.split('\n');
        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6);
            if (data === '[DONE]') {
              if (target) {
                target.innerHTML = renderMarkdown(fullText);
              }
              typing && typing.classList.add('hidden');
              break;
            }
            fullText += data;
            if (target) {
              target.textContent += data;
              container.parentElement.scrollTop = container.parentElement.scrollHeight;
            }
          }
        }
      }
    }
    typing && typing.classList.add('hidden');
  } catch (err) {
    typing && typing.classList.add('hidden');
  }
  return false;
};

function escapeHtml(str) {
  return str.replace(/[&<>"]+/g, function (s) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
    return map[s] || s;
  });
}

function renderMarkdown(text) {
  try {
    let html = escapeHtml(text);
    html = html.replace(/```([a-zA-Z0-9_+-]*)\n([\s\S]*?)```/g, function (_, lang, code) {
      const language = lang ? `language-${lang}` : '';
      return `<pre><code class="${language}">${escapeHtml(code)}</code></pre>`;
    });
    html = html.replace(/`([^`]+)`/g, function (_, code) {
      return `<code>${escapeHtml(code)}</code>`;
    });
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/^\s*\#\s+(.+)$/gm, '<h3>$1</h3>');
    return html;
  } catch (e) {
    return escapeHtml(text);
  }
}
