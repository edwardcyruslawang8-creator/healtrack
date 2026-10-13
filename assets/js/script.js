/* =============================================================
   NORTH SUPER HEALTH CENTER — Dashboard Interactions
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Off-canvas sidebar (tablet/mobile) ---------- */
    const sidebar         = document.getElementById('sidebar');
    const sidebarToggle   = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        sidebar.classList.add('show');
        sidebarBackdrop.classList.add('show');
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
    }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    /* ---------- Chatbot: suggestion chips fill the input ---------- */
    const chatbotInput = document.getElementById('chatbotInput');
    document.querySelectorAll('.suggestion-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const question = chip.getAttribute('data-question');
            if (chatbotInput) {
                chatbotInput.value = question;
                chatbotInput.focus();
            }
        });
    });

    /* ---------- Chatbot: submit handler (placeholder for chatbot.php) ---------- */
    const chatbotForm = document.getElementById('chatbotForm');
    if (chatbotForm) {
        chatbotForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const message = chatbotInput.value.trim();
            if (!message) return;

            // TODO: replace with a real request once chatbot.php is wired to the AI backend:
            // fetch('chatbot.php', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ message })
            // }).then(res => res.json()).then(data => { /* render data.reply */ });

            console.log('Chatbot message submitted:', message);
            chatbotInput.value = '';
        });
    }

    /* ---------- Hero search: currently a normal GET submit to search.php ---------- */
    // No extra JS needed — the <form> submits natively. Hook AJAX here later
    // if live-search suggestions are wanted before the results page loads.

});
