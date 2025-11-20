import Swal from 'sweetalert2'

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
    const data = await res.json();
    typing && typing.classList.add('hidden');
    const aiBubble = document.createElement('div');
    aiBubble.className = 'flex justify-start';
    aiBubble.innerHTML = `<div class="flex items-start max-w-[80%]"><div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold mr-2 flex-shrink-0">AI</div><div class="bg-gray-100 text-gray-800 rounded-2xl px-4 py-3 shadow-sm"><p>${escapeHtml(data.content || '')}</p><p class="text-xs text-gray-500 mt-1">${data.model || ''}</p></div></div>`;
    container.appendChild(aiBubble);
    container.parentElement.scrollTop = container.parentElement.scrollHeight;
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
