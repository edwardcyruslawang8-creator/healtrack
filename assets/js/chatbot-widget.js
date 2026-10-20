/* =============================================================
   GLOBAL CHATBOT WIDGET JS (includes/shared/chatbot_widget.php)
   ---------------------------------------------------------------
   Loaded on every public page (see includes/shared/footer.php).
   Message-sending/rendering logic below is carried over from the old
   assets/js/chatbot.js (full "Talk with NSHC" page, deleted) and
   assets/js/home.js (old embedded homepage widget, deleted) — both
   called the same real api/chatbot-ask.php endpoint
   (includes/public/chatbot_engine.php: plain keyword/fuzzy-name
   matching against real Firestore data, not a general AI model), so
   this is one copy of that logic instead of two near-identical ones.
   New here: opening/closing the floating panel and the idle "cute
   phrase" bubble that rotates while it's closed. The mascot's own idle
   motion (a gentle blink) is pure CSS — see assets/css/chatbot-widget.css's
   hc-fab-blink keyframes.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    const fabWrap = document.getElementById('chatbotFabWrap');
    const fabBtn = document.getElementById('chatbotFabBtn');
    const idleBubble = document.getElementById('chatbotIdleBubble');
    const panel = document.getElementById('chatbotWidgetPanel');
    const closeBtn = document.getElementById('chatbotWidgetCloseBtn');
    const conversation = document.getElementById('chatConversation');
    const chatForm = document.getElementById('chatInputForm');
    const chatInput = document.getElementById('chatInput');
    const suggestionCards = document.querySelector('.suggestion-cards');
    const BASE_URL = window.APP_BASE_URL || '';
    // Bot avatars render via pure CSS (assets/css/chatbot-widget.css's
    // .chat-avatar-sm:not(.user-avatar) background-image, cropped/zoomed
    // to just the mascot's face) — no <img> needed, so these divs are
    // left empty below.

    if (!panel || !fabBtn) return; // widget markup not present on this page for some reason — nothing to wire up

    /* ---------- Panel open/close ---------- */
    let isOpen = false;

    function openPanel() {
        isOpen = true;
        panel.classList.add('is-open');
        fabWrap.classList.add('is-open');
        fabBtn.setAttribute('aria-expanded', 'true');
        stopIdlePhrases();
        window.setTimeout(function () { if (chatInput) chatInput.focus(); }, 150);
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove('is-open');
        fabWrap.classList.remove('is-open');
        fabBtn.setAttribute('aria-expanded', 'false');
        startIdlePhrases();
    }

    fabBtn.addEventListener('click', function () {
        if (isOpen) {
            closePanel();
        } else {
            openPanel();
        }
    });
    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    // Any element anywhere on the site can open this same panel by
    // carrying data-open-chatbot — see e.g. patient/announcements.php's
    // "Report a Concern" button, which used to link to the now-deleted
    // patient/chatbot.php page.
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-open-chatbot]');
        if (!trigger) return;
        e.preventDefault();
        openPanel();
    });

    /* ---------- Idle "cute phrase" bubble (only while the panel is closed) ---------- */
    const CUTE_PHRASES = [
        'Hi there! Need help? 👋',
        'Got a health question? Ask me! 💬',
        'Your health buddy is here! 💙',
        'Ask about medicines or vaccines! 💊',
        'Kumusta! How can I help? 🌼',
        'Need info? I\'m just a click away! ✨',
        'Here if you need anything! 🩺',
    ];
    let idleTimer = null;
    let idleIndex = Math.floor(Math.random() * CUTE_PHRASES.length);

    function showIdlePhrase() {
        if (!idleBubble || isOpen) return;
        idleBubble.textContent = CUTE_PHRASES[idleIndex];
        idleBubble.classList.add('is-visible');
        idleIndex = (idleIndex + 1) % CUTE_PHRASES.length;
    }

    function hideIdlePhrase() {
        if (idleBubble) idleBubble.classList.remove('is-visible');
    }

    function startIdlePhrases() {
        if (!idleBubble || window.matchMedia('(max-width: 575.98px)').matches) return;
        window.setTimeout(showIdlePhrase, 2500); // first one, a beat after page load
        idleTimer = window.setInterval(function () {
            hideIdlePhrase();
            window.setTimeout(showIdlePhrase, 400); // brief gap so the fade-out/in don't overlap
        }, 7000);
    }

    function stopIdlePhrases() {
        if (idleTimer) window.clearInterval(idleTimer);
        idleTimer = null;
        hideIdlePhrase();
    }

    startIdlePhrases();

    /* ---------- Message rendering (carried over from the old chatbot.js) ---------- */

    function renderBubbleContent(bubble, text) {
        bubble.textContent = '';
        const urlRegex = /https?:\/\/[^\s]+/g;
        let lastIndex = 0;
        let match;
        while ((match = urlRegex.exec(text)) !== null) {
            let url = match[0];
            let trailing = '';
            while (url.length > 0) {
                const last = url[url.length - 1];
                if (last === ')') {
                    const opens = (url.match(/\(/g) || []).length;
                    const closes = (url.match(/\)/g) || []).length;
                    if (closes > opens) {
                        trailing = last + trailing;
                        url = url.slice(0, -1);
                        continue;
                    }
                    break;
                }
                if ('.,;:!?'.indexOf(last) !== -1) {
                    trailing = last + trailing;
                    url = url.slice(0, -1);
                    continue;
                }
                break;
            }
            if (match.index > lastIndex) {
                bubble.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
            }
            const link = document.createElement('a');
            link.href = url;
            link.textContent = url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'chat-link';
            bubble.appendChild(link);
            if (trailing) bubble.appendChild(document.createTextNode(trailing));
            lastIndex = match.index + match[0].length;
        }
        if (lastIndex < text.length) {
            bubble.appendChild(document.createTextNode(text.slice(lastIndex)));
        }
    }

    function appendMessage(text, from, persist) {
        if (!conversation) return null;
        if (persist === undefined) persist = true;

        const row = document.createElement('div');
        row.className = 'chat-message-row' + (from === 'user' ? ' from-user' : '');

        const avatar = document.createElement('div');
        avatar.className = 'chat-avatar-sm' + (from === 'user' ? ' user-avatar' : '');
        if (from === 'user') avatar.innerHTML = '<i class="bi bi-person-fill"></i>';

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        renderBubbleContent(bubble, text);

        row.appendChild(avatar);
        row.appendChild(bubble);

        const blankArea = document.querySelector('.chat-blank-area');
        if (blankArea) {
            blankArea.insertAdjacentElement('beforebegin', row);
        } else {
            conversation.appendChild(row);
        }

        conversation.scrollTop = conversation.scrollHeight;

        if (persist && window.HCChatHistory) window.HCChatHistory.save(text, from);

        return row;
    }

    function appendTypingIndicator() {
        if (!conversation) return null;

        const row = document.createElement('div');
        row.className = 'chat-message-row is-typing';

        const avatar = document.createElement('div');
        avatar.className = 'chat-avatar-sm';

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.innerHTML = '<span class="chat-typing-dots"><span></span><span></span><span></span></span>';

        row.appendChild(avatar);
        row.appendChild(bubble);

        const blankArea = document.querySelector('.chat-blank-area');
        if (blankArea) {
            blankArea.insertAdjacentElement('beforebegin', row);
        } else {
            conversation.appendChild(row);
        }
        conversation.scrollTop = conversation.scrollHeight;

        return row;
    }

    // "👎 This didn't answer my question" — flags the chatbot_logs doc
    // behind this exact reply (api/chatbot-feedback.php) so it surfaces
    // in subadmin/chatbot_reports.php's "Needs Your Answer" queue even
    // though it technically matched something — the only way a
    // CONFIDENTLY wrong answer (as opposed to a total miss) ever gets
    // surfaced for a subadmin to correct.
    function appendFeedbackControl(bubbleRow, logId) {
        if (!logId) return;

        const wrap = document.createElement('div');
        wrap.className = 'chat-feedback';

        const link = document.createElement('button');
        link.type = 'button';
        link.className = 'chat-feedback-link';
        link.innerHTML = '<i class="bi bi-hand-thumbs-down"></i> This didn\'t answer my question';

        link.addEventListener('click', function () {
            link.disabled = true;
            link.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending…';

            fetch(BASE_URL + '/api/chatbot-feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ logId: logId }),
            })
                .then(function () {
                    link.innerHTML = '<i class="bi bi-check2"></i> Thanks — we\'ll review this.';
                })
                .catch(function () {
                    link.disabled = false;
                    link.innerHTML = '<i class="bi bi-hand-thumbs-down"></i> This didn\'t answer my question';
                });
        });

        wrap.appendChild(link);
        bubbleRow.appendChild(wrap);
    }

    function appendAnimatedMessage(text, from, logId) {
        if (!conversation) return null;

        const row = document.createElement('div');
        row.className = 'chat-message-row' + (from === 'user' ? ' from-user' : '');

        const avatar = document.createElement('div');
        avatar.className = 'chat-avatar-sm' + (from === 'user' ? ' user-avatar' : '');
        if (from === 'user') avatar.innerHTML = '<i class="bi bi-person-fill"></i>';

        const bubbleCol = document.createElement('div');
        bubbleCol.className = 'chat-bubble-col';

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        bubbleCol.appendChild(bubble);
        row.appendChild(avatar);
        row.appendChild(bubbleCol);

        if (from === 'assistant') appendFeedbackControl(bubbleCol, logId);

        const blankArea = document.querySelector('.chat-blank-area');
        if (blankArea) {
            blankArea.insertAdjacentElement('beforebegin', row);
        } else {
            conversation.appendChild(row);
        }

        if (window.HCChatHistory) window.HCChatHistory.save(text, from);

        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion || !text) {
            renderBubbleContent(bubble, text);
            conversation.scrollTop = conversation.scrollHeight;
            return row;
        }

        let shown = 0;
        const step = Math.max(1, Math.ceil(text.length / 60));
        const timer = setInterval(function () {
            shown += step;
            if (shown >= text.length) {
                renderBubbleContent(bubble, text);
                clearInterval(timer);
            } else {
                bubble.textContent = text.slice(0, shown);
            }
            conversation.scrollTop = conversation.scrollHeight;
        }, 16);

        return row;
    }

    // Restore any conversation from a previous visit (same browser only
    // — see chatbot-history.js). Shown instantly, never re-animated.
    if (window.HCChatHistory) {
        const restored = window.HCChatHistory.load();
        if (restored.length > 0) {
            if (suggestionCards) suggestionCards.classList.add('is-collapsed');
            restored.forEach(function (entry) {
                appendMessage(entry.text, entry.from, false);
            });
        }
    }

    function sendMessage(text) {
        const message = text.trim();
        if (!message) return;

        if (suggestionCards) suggestionCards.classList.add('is-collapsed');
        appendMessage(message, 'user');

        const typingRow = appendTypingIndicator();

        fetch(BASE_URL + '/api/chatbot-ask.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (typingRow) typingRow.remove();
                appendAnimatedMessage(data.answer || data.error || 'Sorry, something went wrong. Please try again.', 'assistant', data.logId);
            })
            .catch(function () {
                if (typingRow) typingRow.remove();
                appendAnimatedMessage('Sorry, I couldn\'t reach the server just now. Please try again.', 'assistant');
            });
    }

    /* ---------- Suggestion cards: send their question directly ---------- */
    document.querySelectorAll('.suggestion-card').forEach(function (card) {
        card.addEventListener('click', function () {
            const question = card.getAttribute('data-question');
            if (question) sendMessage(question);
        });
    });

    /* ---------- Message form submit ---------- */
    if (chatForm) {
        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();
            sendMessage(chatInput.value);
            chatInput.value = '';
        });
    }

    /* ---------- "Contact NSHC Directly" modal: Email Us button ----------
       Opens the visitor's own email client (mailto:), prefilled with
       whatever they typed in the textarea. ---------- */
    const emailUsBtn = document.getElementById('emailUsBtn');
    const contactMessageBox = document.getElementById('contactMessage');

    if (emailUsBtn) {
        emailUsBtn.addEventListener('click', function () {
            // Real office email (includes/shared/homepage_settings.php),
            // rendered into data-office-email server-side — never a
            // hardcoded address here that could drift out of sync.
            const officeEmail = emailUsBtn.dataset.officeEmail || '';
            const message = contactMessageBox ? contactMessageBox.value.trim() : '';
            const subject = encodeURIComponent('Message from NSHC Assistant');
            const body = encodeURIComponent(message || 'Hello NSHC team,\n\n');
            window.location.href = 'mailto:' + officeEmail + '?subject=' + subject + '&body=' + body;
        });
    }

});
