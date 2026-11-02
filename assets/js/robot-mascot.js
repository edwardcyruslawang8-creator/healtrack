/**
 * =====================================================================
 * Animated Robot Mascot — Controller
 * ---------------------------------------------------------------------
 * Drives every .robot-mascot[data-robot] element on the page (there
 * are two: the FAB button's and the panel header's — see
 * includes/shared/chatbot_widget.php). Deliberately minimal:
 *
 *   - FAB instance: bounces (.is-bouncing) while the chatbot is
 *     closed, stops the instant it opens (it's hidden then anyway —
 *     see assets/css/chatbot-widget.css), resumes when it closes
 *     again.
 *   - Header instance: never bounces, never waves — the forearm/hand
 *     wave-on-open was removed by request. Stays completely
 *     stationary in every state.
 *   - Both instances: blink on an independent randomized 3-5s loop,
 *     in every state, all the time. For the header instance this is
 *     the only animation it ever runs.
 *
 * Driven off one source of truth: a MutationObserver on
 * #chatbotFabWrap's own "is-open" class, already toggled by
 * assets/js/chatbot-widget.js's openPanel()/closePanel() — not edited
 * here, no new listeners added to that file.
 * =====================================================================
 */
(function () {
    'use strict';

    function randomBetween(min, max) {
        return min + Math.random() * (max - min);
    }

    function initBlink(el) {
        var blinkTimer = null;
        function scheduleBlink() {
            blinkTimer = setTimeout(function () {
                el.classList.add('is-blinking');
                // 220ms matches the robot-blink keyframes' own duration
                // (assets/css/robot-mascot.css) exactly, so the class
                // comes off right as the open->half->closed->half->open
                // animation finishes, not mid-sequence.
                setTimeout(function () {
                    el.classList.remove('is-blinking');
                    scheduleBlink();
                }, 220);
            }, randomBetween(3000, 5000));
        }
        scheduleBlink();
        return function destroy() { clearTimeout(blinkTimer); };
    }

    var fabRobots = [];
    document.querySelectorAll('.robot-mascot[data-robot]').forEach(function (el) {
        initBlink(el);
        if (el.getAttribute('data-robot-size') === 'fab') fabRobots.push(el);
    });

    // -- Bounce while closed, stop while open — one class, one source
    //    of truth (#chatbotFabWrap's existing "is-open" class). --
    var fabWrap = document.getElementById('chatbotFabWrap');
    if (fabWrap) {
        function syncBounce() {
            var isOpen = fabWrap.classList.contains('is-open');
            fabRobots.forEach(function (el) {
                el.classList.toggle('is-bouncing', !isOpen);
            });
        }
        syncBounce();
        new MutationObserver(syncBounce).observe(fabWrap, { attributes: true, attributeFilter: ['class'] });
    }
})();
