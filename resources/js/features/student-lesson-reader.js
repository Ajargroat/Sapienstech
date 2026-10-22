/*
 * Student lesson reader — table-of-contents scroll-spy and reading meter.
 *
 * Two behaviours, both read-only on the DOM and both harmless if the API call
 * fails:
 *   - the TOC highlights the section the reader is currently inside;
 *   - the meter fills from 0 to 100% across the article's scrollable height.
 *
 * Progress is POSTed at most once per section, and only forward — the server
 * also refuses to move the marker backwards, so a refresh cannot undo reading.
 */

const reader = document.querySelector('[data-lesson-reader]')

if (reader) {
    const sections = Array.from(reader.querySelectorAll('[data-lesson-section]'))
    const links = Array.from(reader.querySelectorAll('[data-lesson-toc]'))
    const meter = reader.querySelector('[data-lesson-meter]')
    const meterLabel = reader.querySelector('[data-lesson-meter-label]')
    const progressUrl = reader.dataset.progressUrl

    let reported = Number(reader.dataset.progressIndex || 0)

    const toPersian = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)])

    function currentIndex() {
        // The LAST section whose top has passed the reading line is the one
        // being read. Using `<=` (not `find` from the top) avoids a flicker to
        // section 0 every time a tall heading scrolls past.
        let index = 0

        sections.forEach((section) => {
            if (section.getBoundingClientRect().top <= 120) {
                index = Number(section.dataset.lessonSection || 0)
            }
        })

        return index
    }

    function paint() {
        const doc = document.documentElement
        const scrollable = (doc.scrollHeight - doc.clientHeight) || 1
        const ratio = Math.min(1, Math.max(0, doc.scrollTop / scrollable))
        const percent = Math.round(ratio * 100)

        if (meter) meter.style.inlineSize = percent + '%'
        if (meterLabel) meterLabel.textContent = toPersian(percent) + '٪ خوانده‌اید'

        const active = currentIndex()

        links.forEach((link) => {
            link.classList.toggle('is-active', Number(link.dataset.lessonToc) === active)
        })

        report(active)
    }

    function report(index) {
        if (!progressUrl || index <= reported) return

        reported = index

        // The token is rendered onto the reader element: the student layouts
        // carry no csrf <meta>, and sending an empty header earns a 419 (the
        // same trap documented in student-dashboard.js).
        fetch(progressUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': reader.dataset.csrf || '',
            },
            body: JSON.stringify({ index }),
            keepalive: true,
        }).catch(() => {
            // Reading must never break because a progress ping failed.
        })
    }

    let queued = false
    window.addEventListener('scroll', () => {
        if (queued) return
        queued = true
        window.requestAnimationFrame(() => {
            queued = false
            paint()
        })
    }, { passive: true })

    window.addEventListener('resize', paint, { passive: true })
    paint()
}

/*
 * The in-page quiz.
 *
 * The answer key is NOT in the markup: the demo this reader came from printed
 * the correct option because the demo was a static page. Here the client posts
 * the option it picked and the server answers `correct` + `correct_answer` +
 * `explanation`, so a student cannot read the answer off the page source.
 *
 * Failure is inert on purpose — a dead endpoint must leave the lesson readable
 * and the question answerable again, never block the article.
 */

const quiz = document.querySelector('[data-lesson-reader]')

if (quiz) {
    const answerUrl = quiz.dataset.answerUrl
    const csrf = quiz.dataset.csrf || ''

    const toPersian = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)])

    quiz.querySelectorAll('[data-lesson-question]').forEach((card) => {
        const options = Array.from(card.querySelectorAll('.lesson-q-opt'))
        const submit = card.querySelector('[data-lesson-q-submit]')
        const note = card.querySelector('[data-lesson-q-note]')
        const feedback = card.querySelector('[data-lesson-q-feedback]')
        let answered = false

        function select(option) {
            if (answered) return

            options.forEach((candidate) => {
                const on = candidate === option
                candidate.classList.toggle('is-selected', on)
                const dot = candidate.querySelector('.lesson-q-dot')
                if (dot) dot.textContent = on ? '✓' : ''
            })

            if (note) note.textContent = 'یک گزینه را انتخاب کرده‌اید.'
        }

        options.forEach((option) => {
            const input = option.querySelector('input')
            if (input) {
                input.addEventListener('change', () => select(option))
            }
        })

        function settle(data) {
            answered = true

            options.forEach((option) => {
                const input = option.querySelector('input')
                const id = Number(input ? input.value : 0)
                const dot = option.querySelector('.lesson-q-dot')

                if (id === Number(data.correct_answer)) {
                    option.classList.add('is-correct')
                    if (dot) dot.textContent = '✓'
                }

                if (id !== Number(data.correct_answer) && option.classList.contains('is-selected')) {
                    option.classList.remove('is-selected')
                    option.classList.add('is-wrong')
                    if (dot) dot.textContent = '✕'
                }

                if (input) input.disabled = true
            })

            if (submit) submit.disabled = true
            if (note) note.textContent = ''

            if (feedback) {
                feedback.hidden = false
                feedback.classList.add(data.correct ? 'is-correct' : 'is-wrong')
                feedback.textContent = data.correct
                    ? 'پاسخ شما درست است.' + (data.explanation ? ' ' + data.explanation : '')
                    : 'پاسخ شما نادرست بود.' + (data.explanation ? ' ' + data.explanation : '')
            }
        }

        if (!submit) return

        submit.addEventListener('click', () => {
            if (answered) return

            const chosen = card.querySelector('input[type="radio"]:checked')

            if (!chosen) {
                if (note) note.textContent = 'اول یک گزینه را انتخاب کنید.'
                return
            }

            if (!answerUrl) return

            submit.disabled = true
            if (note) note.textContent = 'در حال بررسی…'

            fetch(answerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    question: Number(card.dataset.question),
                    answer: Number(chosen.value),
                }),
            })
                .then((response) => (response.ok ? response.json() : Promise.reject(new Error('bad status'))))
                .then(settle)
                .catch(() => {
                    submit.disabled = false
                    if (note) note.textContent = 'بررسی پاسخ ممکن نشد؛ دوباره تلاش کنید.'
                })
        })
    })
}
