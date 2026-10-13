/* =============================================================
   CHATBOT CONVERSATION HISTORY (shared: home.js + chatbot.js)
   ---------------------------------------------------------------
   Persists the chat conversation in this browser's own localStorage
   so an accidental page reload or browser restart doesn't lose it.
   Never touches a server — the conversation is automatically private
   to this one browser/device and never visible to any other visitor,
   the same way any other site's localStorage-based data works. Shared
   between the homepage widget (home.js) and the full "Talk with NSHC"
   page (chatbot.js) so a conversation continues seamlessly between
   the two, since both already call the same real backend.

   Expires after TTL_MS of inactivity, matching this app's own
   30-minute idle-session convention (see assets/js/idle-timeout.js) —
   an old, abandoned conversation shouldn't silently resurface days
   later.
   ============================================================= */
(function () {
    var STORAGE_KEY = 'hc_chatbot_history_v1';
    var TTL_MS = 30 * 60 * 1000; // 30 minutes

    function load() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed) || parsed.length === 0) return [];
            var last = parsed[parsed.length - 1];
            if (!last || typeof last.ts !== 'number' || (Date.now() - last.ts) > TTL_MS) {
                localStorage.removeItem(STORAGE_KEY);
                return [];
            }
            return parsed;
        } catch (e) {
            return [];
        }
    }

    function save(text, from) {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            var arr = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(arr)) arr = [];
            arr.push({ text: text, from: from, ts: Date.now() });
            // Caps growth in a very long-running tab; an abandoned
            // conversation still fully expires via the TTL check above.
            while (arr.length > 50) arr.shift();
            localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
        } catch (e) {
            // Private browsing / blocked storage — the conversation
            // just won't persist, same as before this feature existed.
        }
    }

    window.HCChatHistory = { load: load, save: save };
})();
