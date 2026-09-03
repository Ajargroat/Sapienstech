// Exam runner: scroll-reveal cards, countdown, navigator state,
// localStorage autosave, submit with elapsed time.
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

    // --- Restore a previous in-progress attempt ---
    const key = `exam-attempt-${root.dataset.assignment}`;
    const saved = JSON.parse(localStorage.getItem(key) || '{}');
    const started = saved.started || Date.now();

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
        localStorage.setItem(key, JSON.stringify({ started, answers, flags }));
    };

    // --- Progress + navigator state ---
    const refresh = () => {
        const answered = root.querySelectorAll('.quiz-option input:checked').length;
        fill.style.width = `${(answered / total) * 100}%`;
        progressText.textContent = new Intl.NumberFormat('fa-IR').format(answered) + '/' + new Intl.NumberFormat('fa-IR').format(total);

        chips.forEach((chip, i) => {
            const card = cards[i];
            const done = card.querySelector('input:checked');
            chip.classList.toggle('is-answered', !!done);
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

    // --- Countdown ---
    let timer = null;
    const duration = +root.dataset.duration;
    if (duration > 0) {
        const timerEl = document.getElementById('runner-timer');
        const timerText = document.getElementById('timer-text');
        timerEl.hidden = false;
        const tick = () => {
            const left = Math.max(0, duration - Math.floor((Date.now() - started) / 1000));
            const m = String(Math.floor(left / 60)).padStart(2, '0');
            const s = String(left % 60).padStart(2, '0');
            timerText.textContent = `${m}:${s}`;
            timerEl.classList.toggle('is-danger', left <= 60);
            if (left === 0) {
                clearInterval(timer);
                document.getElementById('time-taken').value = duration;
                document.getElementById('attempt-form').submit();
            }
        };
        timer = setInterval(tick, 1000);
        tick();
    }

    // --- Submit ---
    document.getElementById('finish-exam')?.addEventListener('click', () => {
        const answered = root.querySelectorAll('.quiz-option input:checked').length;
        const unanswered = total - answered;
        const msg = unanswered > 0
            ? `${new Intl.NumberFormat('fa-IR').format(unanswered)} سوال بی‌پاسخ است. پایان آزمون؟`
            : 'پایان آزمون و ثبت پاسخ‌ها؟';
        if (!confirm(msg)) return;
        document.getElementById('time-taken').value = Math.floor((Date.now() - started) / 1000);
        localStorage.removeItem(key);
        document.getElementById('attempt-form').submit();
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
