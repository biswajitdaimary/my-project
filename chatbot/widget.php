<?php
/**
 * Chatbot Widget — name driven by site settings
 * Include this file in includes/footer.php or any page
 */
if (!defined('SITE_URL')) return;
if (!function_exists('site_settings_get')) {
    require_once __DIR__ . '/../helpers/site_settings_helper.php';
}
$chatbotApiUrl  = SITE_URL . '/chatbot/api.php';
$isLoggedIn     = !empty($_SESSION['user_id']);
$chatbotGymName = htmlspecialchars(site_settings_get('gym_name', SITE_NAME));
$chatbotBotName = $chatbotGymName . ' Assistant';
?>
<!-- ── PowerHouse Chatbot Widget ─────────────────────────────────── -->
<div id="ph-chatbot-wrap">
    <!-- Trigger Button -->
    <button id="ph-chat-btn" aria-label="Open Chat Assistant" title="Chat with us">
        <span id="ph-chat-icon"><i class="fa-solid fa-comments"></i></span>
        <span id="ph-chat-close-icon" style="display:none;"><i class="fa-solid fa-xmark"></i></span>
        <span id="ph-chat-badge" style="display:none;">1</span>
    </button>

    <!-- Chat Window -->
    <div id="ph-chat-window" aria-live="polite" role="dialog" aria-label="<?= $chatbotGymName ?> Chat">
        <!-- Header -->
        <div id="ph-chat-header">
            <div class="ph-chat-header-info">
                <div class="ph-chat-avatar"><i class="fa-solid fa-dumbbell"></i></div>
                <div>
                    <div class="ph-chat-name"><?= $chatbotBotName ?></div>
                    <div class="ph-chat-status"><span class="ph-online-dot"></span> Online</div>
                </div>
            </div>
            <button id="ph-chat-minimize" title="Minimize"><i class="fa-solid fa-minus"></i></button>
        </div>

        <!-- Messages -->
        <div id="ph-chat-messages"></div>

        <!-- Quick Replies Bar -->
        <div id="ph-quick-bar"></div>

        <!-- Input -->
        <div id="ph-chat-input-wrap">
            <input type="text" id="ph-chat-input" placeholder="Ask me anything…" autocomplete="off" maxlength="300">
            <button id="ph-chat-send" title="Send"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
        <div id="ph-chat-footer">Powered by <?= $chatbotGymName ?> AI</div>
    </div>
</div>

<style>
/* ── Variables ─────────────────────────────────── */
#ph-chatbot-wrap {
    --ph-primary: #FF6B35;
    --ph-primary-dark: #e55520;
    --ph-dark: #1a1a2e;
    --ph-text: #333;
    --ph-bg: #f8f9fa;
    --ph-radius: 1.2rem;
    --ph-shadow: 0 12px 48px rgba(0,0,0,0.18);
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 99999;
    font-family: 'Inter', 'Segoe UI', sans-serif;
}

/* ── Trigger Button ────────────────────────────── */
#ph-chat-btn {
    width: 62px; height: 62px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
    border: none; cursor: pointer;
    color: #fff; font-size: 1.5rem;
    box-shadow: 0 6px 24px rgba(255,107,53,0.45);
    transition: transform .2s, box-shadow .2s;
    position: relative;
    display: flex; align-items: center; justify-content: center;
}
#ph-chat-btn:hover { transform: scale(1.1); box-shadow: 0 10px 32px rgba(255,107,53,0.55); }
#ph-chat-badge {
    position: absolute; top: 4px; right: 4px;
    background: #ff3b3b; color: #fff;
    width: 18px; height: 18px; border-radius: 50%;
    font-size: 0.7rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
}

/* ── Chat Window ───────────────────────────────── */
#ph-chat-window {
    display: none;
    flex-direction: column;
    position: absolute; bottom: 80px; right: 0;
    width: 370px; height: 520px;
    background: #fff;
    border-radius: var(--ph-radius);
    box-shadow: var(--ph-shadow);
    overflow: hidden;
    animation: ph-slide-in .25s ease;
}
@keyframes ph-slide-in {
    from { opacity: 0; transform: translateY(20px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
#ph-chat-window.ph-open { display: flex; }

/* ── Header ────────────────────────────────────── */
#ph-chat-header {
    background: linear-gradient(135deg, var(--ph-dark), #2d2d50);
    color: #fff; padding: 14px 16px;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.ph-chat-header-info { display: flex; align-items: center; gap: 12px; }
.ph-chat-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.ph-chat-name { font-weight: 700; font-size: 0.95rem; }
.ph-chat-status { font-size: 0.75rem; color: rgba(255,255,255,0.7); display: flex; align-items: center; gap: 5px; }
.ph-online-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #2ecc71; display: inline-block;
    animation: ph-pulse 2s infinite;
}
@keyframes ph-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
#ph-chat-minimize {
    background: rgba(255,255,255,0.15); border: none; color: #fff;
    width: 30px; height: 30px; border-radius: 50%; cursor: pointer;
    font-size: 0.85rem; display: flex; align-items: center; justify-content: center;
    transition: background .2s;
}
#ph-chat-minimize:hover { background: rgba(255,255,255,0.3); }

/* ── Messages ──────────────────────────────────── */
#ph-chat-messages {
    flex: 1; overflow-y: auto; padding: 16px 14px;
    display: flex; flex-direction: column; gap: 10px;
    background: var(--ph-bg);
    scroll-behavior: smooth;
}
#ph-chat-messages::-webkit-scrollbar { width: 4px; }
#ph-chat-messages::-webkit-scrollbar-thumb { background: #ccc; border-radius: 2px; }

.ph-msg { display: flex; align-items: flex-end; gap: 8px; }
.ph-msg--bot { flex-direction: row; }
.ph-msg--user { flex-direction: row-reverse; }

.ph-msg-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.75rem; flex-shrink: 0;
}

.ph-bubble {
    max-width: 78%; padding: 10px 14px;
    border-radius: 18px; font-size: 0.875rem; line-height: 1.5;
    word-break: break-word;
}
.ph-msg--bot .ph-bubble {
    background: #fff; color: var(--ph-text);
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.ph-msg--user .ph-bubble {
    background: linear-gradient(135deg, var(--ph-primary), var(--ph-primary-dark));
    color: #fff; border-bottom-right-radius: 4px;
}
.ph-bubble strong { font-weight: 700; }
.ph-bubble br { margin-bottom: 2px; }

/* Action link inside bubble */
.ph-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 10px; padding: 7px 14px;
    background: var(--ph-primary); color: #fff;
    border-radius: 50px; font-size: 0.8rem; font-weight: 700;
    text-decoration: none; transition: background .2s, transform .1s;
}
.ph-action-btn:hover { background: var(--ph-primary-dark); color: #fff; transform: translateY(-1px); }

/* Typing indicator */
.ph-typing .ph-bubble { padding: 12px 16px; }
.ph-typing-dots { display: flex; gap: 5px; align-items: center; }
.ph-typing-dots span {
    width: 7px; height: 7px; border-radius: 50%;
    background: #bbb; animation: ph-bounce .8s infinite;
}
.ph-typing-dots span:nth-child(2) { animation-delay: .15s; }
.ph-typing-dots span:nth-child(3) { animation-delay: .3s; }
@keyframes ph-bounce { 0%,80%,100%{transform:scale(0.8);opacity:.6} 40%{transform:scale(1.2);opacity:1} }

/* ── Quick Replies ─────────────────────────────── */
#ph-quick-bar {
    padding: 8px 12px; background: #fff;
    border-top: 1px solid #f0f0f0;
    display: flex; flex-wrap: wrap; gap: 6px; flex-shrink: 0;
    min-height: 0; max-height: 80px; overflow-y: auto;
}
.ph-qr-btn {
    padding: 5px 12px; border-radius: 50px;
    background: #f4f4f4; border: 1px solid #e0e0e0;
    font-size: 0.78rem; cursor: pointer; color: var(--ph-dark);
    transition: all .15s; font-weight: 600; white-space: nowrap;
    text-decoration: none; display: inline-block;
}
.ph-qr-btn:hover { background: var(--ph-primary); color: #fff; border-color: var(--ph-primary); }

/* ── Input ─────────────────────────────────────── */
#ph-chat-input-wrap {
    padding: 10px 12px;
    display: flex; gap: 8px; background: #fff;
    border-top: 1px solid #eee; flex-shrink: 0;
}
#ph-chat-input {
    flex: 1; border: 1.5px solid #e0e0e0; border-radius: 50px;
    padding: 9px 16px; font-size: 0.875rem; outline: none;
    transition: border-color .2s;
}
#ph-chat-input:focus { border-color: var(--ph-primary); }
#ph-chat-send {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--ph-primary); border: none; color: #fff;
    cursor: pointer; font-size: 0.9rem;
    display: flex; align-items: center; justify-content: center;
    transition: background .2s, transform .1s;
    flex-shrink: 0;
}
#ph-chat-send:hover { background: var(--ph-primary-dark); transform: scale(1.08); }

#ph-chat-footer {
    text-align: center; font-size: 0.68rem; color: #bbb;
    padding: 4px 0 6px; background: #fff; flex-shrink: 0;
}

/* ── Responsive ────────────────────────────────── */
@media (max-width: 420px) {
    #ph-chat-window { width: calc(100vw - 20px); right: -14px; height: 480px; }
    #ph-chatbot-wrap { bottom: 16px; right: 16px; }
}
</style>

<script>
(function () {
    const API = '<?= $chatbotApiUrl ?>';
    const wrap = document.getElementById('ph-chatbot-wrap');
    const btn  = document.getElementById('ph-chat-btn');
    const win  = document.getElementById('ph-chat-window');
    const msgs = document.getElementById('ph-chat-messages');
    const inp  = document.getElementById('ph-chat-input');
    const send = document.getElementById('ph-chat-send');
    const qbar = document.getElementById('ph-quick-bar');
    const badge    = document.getElementById('ph-chat-badge');
    const iconOpen  = document.getElementById('ph-chat-icon');
    const iconClose = document.getElementById('ph-chat-close-icon');
    const minimize  = document.getElementById('ph-chat-minimize');

    let isOpen = false;

    // ── Toggle Window ────────────────────────────────────────
    function toggle() {
        isOpen = !isOpen;
        win.classList.toggle('ph-open', isOpen);
        iconOpen.style.display  = isOpen ? 'none' : 'flex';
        iconClose.style.display = isOpen ? 'flex' : 'none';
        badge.style.display = 'none';
        if (isOpen && msgs.children.length === 0) {
            setTimeout(() => sendMessage('hello'), 200);
        }
        if (isOpen) inp.focus();
    }
    btn.addEventListener('click', toggle);
    minimize.addEventListener('click', toggle);

    // ── Render Markdown-lite ─────────────────────────────────
    function renderMd(text) {
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    // ── Append Message ───────────────────────────────────────
    function appendMsg(role, html, action, quickReplies) {
        const isBot = role === 'bot';
        const div = document.createElement('div');
        div.className = 'ph-msg ph-msg--' + role;

        if (isBot) {
            div.innerHTML = `
                <div class="ph-msg-avatar"><i class="fa-solid fa-dumbbell"></i></div>
                <div class="ph-bubble">${html}${action ? `<br><a class="ph-action-btn" href="${action.url}" target="_self">${action.label}</a>` : ''}</div>`;
        } else {
            div.innerHTML = `<div class="ph-bubble">${html}</div>`;
        }

        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;

        // Render quick replies
        if (isBot && quickReplies && quickReplies.length > 0) {
            qbar.innerHTML = '';
            quickReplies.forEach(qr => {
                const el = qr.url
                    ? `<a class="ph-qr-btn" href="${qr.url}">${qr.label}</a>`
                    : `<button class="ph-qr-btn" data-query="${qr.label}">${qr.label}</button>`;
                qbar.insertAdjacentHTML('beforeend', el);
            });
            // Bind button quick replies
            qbar.querySelectorAll('.ph-qr-btn[data-query]').forEach(b => {
                b.addEventListener('click', () => sendMessage(b.dataset.query));
            });
        }
    }

    // ── Typing Indicator ─────────────────────────────────────
    function showTyping() {
        const t = document.createElement('div');
        t.className = 'ph-msg ph-msg--bot ph-typing';
        t.id = 'ph-typing';
        t.innerHTML = `<div class="ph-msg-avatar"><i class="fa-solid fa-dumbbell"></i></div>
            <div class="ph-bubble"><div class="ph-typing-dots"><span></span><span></span><span></span></div></div>`;
        msgs.appendChild(t);
        msgs.scrollTop = msgs.scrollHeight;
    }
    function hideTyping() {
        const t = document.getElementById('ph-typing');
        if (t) t.remove();
    }

    // ── Send Message ─────────────────────────────────────────
    function sendMessage(text) {
        text = text.trim();
        if (!text) return;
        inp.value = '';
        send.disabled = true;
        qbar.innerHTML = '';

        if (text.toLowerCase() !== 'hello') {
            appendMsg('user', renderMd(text), null, null);
        }

        showTyping();

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
        .then(r => r.json())
        .then(data => {
            hideTyping();
            if (data.error) {
                appendMsg('bot', '⚠️ ' + data.error, null, null);
            } else {
                appendMsg('bot', renderMd(data.response), data.action || null, data.quick_replies || []);
            }
            send.disabled = false;
            inp.focus();
        })
        .catch(() => {
            hideTyping();
            appendMsg('bot', '⚠️ Connection error. Please try again.', null, null);
            send.disabled = false;
        });
    }

    // ── Event Listeners ──────────────────────────────────────
    send.addEventListener('click', () => sendMessage(inp.value));
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(inp.value); });

    // Show badge on load after 3 seconds
    setTimeout(() => {
        if (!isOpen) {
            badge.style.display = 'flex';
            badge.textContent = '1';
        }
    }, 3000);
})();
</script>
<!-- ── End Chatbot Widget ─────────────────────────────────────────── -->
