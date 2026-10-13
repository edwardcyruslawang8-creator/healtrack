/* =============================================================
   CHATBOT REPORTS (subadmin/chatbot_reports.php)
   ---------------------------------------------------------------
   "Needs Your Answer" → clicking Answer opens #cbrAnswerModal,
   pre-filled with the real question (via the button's own
   data-question-normalized/data-question-sample attributes). Save
   Answer posts to api/answer-chatbot-question.php — the only thing
   that ever writes to the chatbot_custom_answers collection;
   includes/public/chatbot_engine.php's hc_chatbot_find_custom_answer()
   is what reads it back on every future chatbot message from then on
   (see that API's own header comment for the full loop). On success
   the page reloads so the row moves out of "Needs Your Answer" from a
   fresh server-side read, same confirm-then-reload pattern this app's
   other admin-side actions use (e.g. assets/js/admin-announcements.js).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('cbrAnswerModal');
    if (!modalEl) return; // nothing to wire up if "Needs Your Answer" is empty (no .cbr-answer-btn either, but the modal markup itself is always present)

    const modal = new bootstrap.Modal(modalEl);
    const questionEl = document.getElementById('cbrAnswerModalQuestion');
    const answerText = document.getElementById('cbrAnswerText');
    const errorEl = document.getElementById('cbrAnswerModalError');
    const saveBtn = document.getElementById('cbrSaveAnswerBtn');

    let currentQuestionNormalized = '';
    let currentQuestionSample = '';

    document.querySelectorAll('.cbr-answer-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentQuestionNormalized = btn.dataset.questionNormalized || '';
            currentQuestionSample = btn.dataset.questionSample || '';
            questionEl.textContent = currentQuestionSample;
            answerText.value = '';
            errorEl.textContent = '';
            modal.show();
        });
    });

    saveBtn.addEventListener('click', function () {
        const answer = answerText.value.trim();
        if (!answer) {
            errorEl.textContent = 'Please type an answer.';
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        fetch('../api/answer-chatbot-question.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                questionNormalized: currentQuestionNormalized,
                questionSample: currentQuestionSample,
                answer: answer,
            }),
        })
            .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Could not save the answer. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                errorEl.textContent = err.message;
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Answer';
            });
    });
});
