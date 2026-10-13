<?php
/**
 * =====================================================================
 * Global Chatbot Widget — Floating Launcher + Panel
 * ---------------------------------------------------------------------
 * Included once from includes/shared/footer.php, unconditionally, so
 * it appears on every public page (index.php + every patient/*.php
 * page) without each page opting in individually — replaces BOTH the
 * old embedded "Talk with NSHC" panel on index.php's dashboard grid AND
 * the separate patient/chatbot.php page (now deleted; its sidebar nav
 * entry is gone too — see includes/public/data.php's $navItems). One
 * chat surface, everywhere, instead of two different ones depending on
 * which page you happened to be on.
 *
 * Real conversation (api/chatbot-ask.php, includes/public/chatbot_engine.php)
 * is unchanged — only the container moved. assets/js/chatbot-widget.js
 * (itself carried over from the old assets/js/chatbot.js, same bubble/
 * suggestion-card/feedback-link rendering logic, same class names so
 * assets/css/chatbot-widget.css needed no new rules for those parts)
 * handles opening/closing the panel, the idle "cute phrase" bubble
 * rotation, and everything the old page already did (send message,
 * animate reply, restore history, the Contact modal's Email Us button).
 *
 * Any element anywhere on the site can open this panel without linking
 * to a page that no longer exists — just add data-open-chatbot (see
 * e.g. patient/announcements.php's "Report a Concern" button) and
 * assets/js/chatbot-widget.js's delegated click listener handles it.
 * =====================================================================
 */

require_once __DIR__ . '/../public/chatbot-data.php'; // $chatSuggestionCards, $otherActions
?>
<!-- ============ CHATBOT WIDGET: FLOATING LAUNCHER ============ -->
<div class="chatbot-fab-wrap" id="chatbotFabWrap">
    <div class="chatbot-idle-bubble" id="chatbotIdleBubble"></div>
    <button type="button" class="chatbot-fab" id="chatbotFabBtn" aria-label="Open chat with NSHC Assistant" aria-expanded="false">
        <img src="<?= asset_url('/assets/images/chatbot-mascot.png') ?>" alt="" class="chatbot-mascot-img">
        <i class="bi bi-x-lg"></i>
    </button>
</div>

<!-- ============ CHATBOT WIDGET: FLOATING PANEL ============ -->
<div class="chatbot-widget-panel" id="chatbotWidgetPanel" role="dialog" aria-modal="false" aria-label="Chat with NSHC Assistant">

    <div class="chatbot-widget-header">
        <div class="chat-avatar-sm"><img src="<?= asset_url('/assets/images/chatbot-mascot.png') ?>" alt="" class="chatbot-mascot-img chatbot-mascot-img-avatar"></div>
        <div class="chatbot-widget-header-text">
            <h2>NSHC Assistant</h2>
            <span class="chat-online-badge"><span class="dot-online"></span> Online</span>
        </div>
        <div class="chatbot-widget-header-actions">
            <button type="button" class="chatbot-widget-icon-btn" data-bs-toggle="modal" data-bs-target="#howItWorksModal" aria-label="How it works">
                <i class="bi bi-info-circle"></i>
            </button>
            <button type="button" class="chatbot-widget-icon-btn" data-bs-toggle="modal" data-bs-target="#contactModal" aria-label="Contact NSHC directly">
                <i class="bi bi-telephone"></i>
            </button>
            <button type="button" class="chatbot-widget-icon-btn" id="chatbotWidgetCloseBtn" aria-label="Close chat">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <div class="chatbot-widget-body" id="chatConversation">

        <!-- Welcome message -->
        <div class="chat-message-row">
            <div class="chat-avatar-sm"><img src="<?= asset_url('/assets/images/chatbot-mascot.png') ?>" alt="" class="chatbot-mascot-img chatbot-mascot-img-avatar"></div>
            <div class="chat-bubble">
                Hello! I'm <strong>NSHC Assistant</strong> 👋<br>How can I help you today?
            </div>
        </div>

        <!-- Suggestion cards -->
        <div class="suggestion-cards">
            <?php foreach ($chatSuggestionCards as $card): ?>
            <button type="button" class="suggestion-card" data-question="<?= htmlspecialchars($card['title']) ?>">
                <span class="sc-icon <?= htmlspecialchars($card['color']) ?>"><i class="bi <?= htmlspecialchars($card['icon']) ?>"></i></span>
                <span class="sc-body">
                    <span class="sc-title"><?= htmlspecialchars($card['title']) ?></span>
                    <span class="sc-desc"><?= htmlspecialchars($card['desc']) ?></span>
                </span>
                <i class="bi bi-chevron-right sc-chevron"></i>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Reserved space for the live conversation as it grows -->
        <div class="chat-blank-area"></div>
    </div>

    <div class="chatbot-widget-input">
        <form class="chat-input-row" id="chatInputForm">
            <input type="text" id="chatInput" placeholder="Type your question here...">
            <button type="submit" class="chat-send-btn" aria-label="Send message">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
        <p class="chat-disclaimer">
            NSHC Assistant is automated and may not cover all queries. For urgent matters, please contact us directly.
        </p>
    </div>

</div>

<!-- "How it works" modal -->
<div class="modal fade" id="howItWorksModal" tabindex="-1" aria-labelledby="howItWorksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="howItWorksModalLabel">How NSHC Assistant works</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>NSHC Assistant answers common questions about our services, medicine and vaccine availability, and general health tips.</p>
                <p>Tap a suggestion card for a quick answer, or type your own question in the box below and press send.</p>
                <p class="mb-0">For anything urgent or outside what the assistant can help with, please use <strong>Contact NSHC directly</strong> (the phone icon above the chat).</p>
            </div>
        </div>
    </div>
</div>

<!-- "Contact NSHC Directly" modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Contact NSHC Directly</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="contact-modal-phone"><i class="bi bi-telephone-fill"></i> (02) 8123-4567</p>
                <p class="contact-modal-hint">Or write your message below and we'll open it in your email app, addressed to our office.</p>
                <div class="mb-0">
                    <label for="contactMessage" class="form-label">Your message</label>
                    <textarea class="form-control" id="contactMessage" rows="5" placeholder="Type your concern or message here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-email-us" id="emailUsBtn">
                    <i class="bi bi-envelope-fill"></i> Email Us
                </button>
            </div>
        </div>
    </div>
</div>
