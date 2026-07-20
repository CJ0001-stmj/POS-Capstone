// ============ Staff Inbox — Suggested Replies ============
// Keyword-matched canned resolution text, picked from the concern's
// own subject + message. Admin still reviews/edits before Update —
// this only pre-fills the notes field, never submits on its own.

document.addEventListener('DOMContentLoaded', () => {

    // Ordered rules — first keyword match wins. Keep specific ones
    // (pay, safety) ahead of generic ones (schedule) since a message
    // can touch more than one topic.
    const SUGGESTION_RULES = [
        {
            keywords: ['unsafe', 'safety', 'hazard', 'injury', 'injured', 'accident'],
            reply: 'Thanks for flagging this — safety comes first. We are looking into the hazard right away and will follow up with the fix and a timeline.',
        },
        {
            keywords: ['pay', 'salary', 'wage', 'payroll', 'underpaid', 'overtime'],
            reply: 'Thanks for raising this — we are checking the payroll records against your logged hours and will get back to you with the correction or an explanation.',
        },
        {
            keywords: ['harass', 'bully', 'disrespect', 'rude', 'threat'],
            reply: 'Thank you for trusting us with this. We take this seriously and are looking into it directly — you will hear from a manager one-on-one shortly.',
        },
        {
            keywords: ['schedule', 'shift', 'roster', 'time-in', 'time in', 'time out', 'late'],
            reply: 'Thanks for flagging the schedule issue — we are reviewing the roster and will confirm the correction with you shortly.',
        },
        {
            keywords: ['equipment', 'broken', 'machine', 'pos', 'printer', 'system down'],
            reply: 'Thanks for the report — we are checking the equipment now and will update you once it is fixed or replaced.',
        },
        {
            keywords: ['stock', 'inventory', 'supply', 'shortage', 'out of stock'],
            reply: 'Thanks for the heads up — we are checking stock levels and will coordinate a restock or reassignment.',
        },
    ];

    const FALLBACK_REPLY = 'Thanks for bringing this up — we are reviewing it and will follow up with you soon.';

    function suggestReply(text) {
        const lower = text.toLowerCase();
        for (const rule of SUGGESTION_RULES) {
            if (rule.keywords.some((kw) => lower.includes(kw))) {
                return rule.reply;
            }
        }
        return FALLBACK_REPLY;
    }

    document.querySelectorAll('.suggest-reply-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const subject = btn.dataset.subject || '';
            const message = btn.dataset.message || '';
            const notesInput = btn.closest('.concern-form')?.querySelector('.resolution-notes-input');
            if (!notesInput) return;

            notesInput.value = suggestReply(`${subject} ${message}`);
            notesInput.focus();
        });
    });
});
