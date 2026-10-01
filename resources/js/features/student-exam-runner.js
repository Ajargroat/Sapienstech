// Student exam runner: scroll-reveal cards, countdown, navigator state,
// localStorage answer draft and submit.
//
// Unlike the consultant's old runner this one never owns the clock: the
// remaining seconds arrive from the server in data-remaining (computed from the
// attempt's own started_at) and elapsed time is measured server-side at submit,
// so refreshing, closing the tab or reopening the exam always resumes the same
// countdown and the same score. localStorage only remembers which options were
// ticked, so an accidental refresh does not lose the student's work.
export default function init() {
    const root = document.getElementById('exam-runner');
    if (!root) return;

    const cards = [...root.querySelectorAll('.quiz-card')];
    const chips = [...root.querySelectorAll('.nav-chip')];
    const fill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const total = cards.length;

    // --- Scroll reveal: cards "scroll out" as they enter the viewport ---
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                e.target.classList.add('is-in');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.15 });
    cards.forEach((c) => io.observe(c));

    // --- Restore the answer draft left by a previous visit ---
    const key = `exam-attempt-${root.dataset.assignment}`;
    const saved = JSON.parse(localStorage.getItem(key) || '{}');

    if (saved.answers) {
        for (const [qid, aid] of Object.entries(saved.answers)) {
            const input = root.querySelector(`input[name="answers[${qid}]"][value="${aid}"]`);
            if (input) input.checked = true;
        }
    }
    if (saved.flags) {
        saved.flags.forEach((i) => cards[i]?.classList.add('is-flagged'));
    }

    const persist = () => {
        const answers = {};
        root.querySelectorAll('.quiz-option input:checked').forEach((i) => {
            const qid = i.name.match(/\[(\d+)\]/)[1];
            answers[qid] = i.value;
        });
        const flags = cards
            .map((c, i) => (c.classList.contains('is-flagged') ? i : null))
            .filter((v) => v !== null);
        localStorage.setItem(key, JSON.stringify({ answers, flags }));
    };

    // --- Progress + navigator state ---
    const refresh = () => {
        const answered = root.querySelectorAll('.quiz-option input:checked').length;
        if (fill) fill.style.width = `${(answered / total) * 100}%`;
        if (progressText) {
            progressText.textContent = `${new Intl.NumberFormat('fa-IR').format(answered)}/${new Intl.NumberFormat('fa-IR').format(total)}`;
        }

        chips.forEach((chip, i) => {
            const card = cards[i];
            if (!card) return;
            chip.classList.toggle('is-answered', !!card.querySelector('input:checked'));
            chip.classList.toggle('is-flagged', card.classList.contains('is-flagged'));
            chip.classList.toggle('is-current', card.classList.contains('is-current'));
        });
    };

    root.addEventListener('change', (e) => {
        if (e.target.matches('.quiz-option input')) {
            e.target.closest('.quiz-card').classList.add('is-answered');
            persist();
            refresh();
        }
    });

    cards.forEach((card) => {
        card.querySelector('.quiz-flag')?.addEventListener('click', () => {
            card.classList.toggle('is-flagged');
            persist();
            refresh();
        });
    });

    chips.forEach((chip) => chip.addEventListener('click', () => {
        cards[+chip.dataset.index]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));

    // Keep the navigator in sync with whichever card is on screen.
    const spy = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            e.target.classList.toggle('is-current', e.isIntersecting);
        });
        refresh();
    }, { rootMargin: '-40% 0px -50% 0px' });
    cards.forEach((c) => spy.observe(c));

    const form = document.getElementById('attempt-form');

    // --- Countdown: server-supplied remaining seconds, local drift only ---
    let timer = null;
    let left = +root.dataset.remaining;

    if (Number.isFinite(left) && left > 0) {
        const timerEl = document.getElementById('runner-timer');
        const timerText = document.getElementById('timer-text');
        if (timerEl) timerEl.hidden = false;

        const render = () => {
            const m = String(Math.floor(left / 60)).padStart(2, '0');
            const s = String(left % 60).padStart(2, '0');
            if (timerText) timerText.textContent = `${m}:${s}`;
            timerEl?.classList.toggle('is-danger', left <= 60);
        };

        const tick = () => {
            left -= 1;
            if (left > 0) {
                render();
                return;
            }
            clearInterval(timer);
            timer = null;
            render();
            // The server already knows when the exam started, so the time is
            // capped there; the client only has to hand the answers back.
            localStorage.removeItem(key);
            form?.submit();
        };

        render();
        timer = setInterval(tick, 1000);
    }

    // --- Submit ---
    document.getElementById('finish-exam')?.addEventListener('click', () => {
        const answered = root.querySelectorAll('.quiz-option input:checked').length;
        const unanswered = total - answered;
        const msg = unanswered > 0
            ? `${new Intl.NumberFormat('fa-IR').format(unanswered)} سوال بی‌پاسخ است. پایان آزمون؟`
            : 'پایان آزمون و ثبت پاسخ‌ها؟';
        if (!confirm(msg)) return;
        localStorage.removeItem(key);
        form?.submit();
    });

    refresh();

    // Leaving the runner mid-exam must not keep ticking or observing cards.
    return () => {
        if (timer !== null) clearInterval(timer);
        io.disconnect();
        spy.disconnect();
    };
}

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
