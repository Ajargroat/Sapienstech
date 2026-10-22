/*
 * Lesson import / AI-draft page — polls while work is queued.
 *
 * The extraction and the AI draft both run on the queue (never inline in a
 * request), so the page has to find out when they are done. Two sources:
 *   - a page reload banner (`data-lesson-poll`) while the PDF extractor runs;
 *   - a small JSON endpoint (`data-lesson-draft-url`) while an AI draft runs.
 *
 * Polling is conservative: it stops as soon as the work leaves a live state,
 * and it gives up after MAX_ATTEMPTS so a dead queue cannot leave a browser
 * hammering the server forever.
 */

const POLL_MS = 4000
const MAX_ATTEMPTS = 75

function poll(url, onEach) {
    let attempts = 0
    let timer = null

    const tick = async () => {
        attempts += 1
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })

            if (response.ok) {
                const data = await response.json()
                if (onEach(data) === false) {
                    clearInterval(timer)
                    return
                }
            }
        } catch {
            // A failed poll is not a failed job; try again next tick.
        }

        if (attempts >= MAX_ATTEMPTS) {
            clearInterval(timer)
        }
    }

    timer = setInterval(tick, POLL_MS)
    tick()
}

// --- extraction still running: reload the page when it is not --------------

document.querySelectorAll('[data-lesson-poll]').forEach((banner) => {
    const url = banner.dataset.lessonPoll
    if (!url) return

    let attempts = 0
    const timer = setInterval(async () => {
        attempts += 1
        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            })

            if (response.ok) {
                const html = await response.text()
                // The server stops rendering the running banner once the
                // import leaves `queued`/`running`.
                if (!html.includes('data-lesson-poll=')) {
                    clearInterval(timer)
                    window.location.reload()
                }
            }
        } catch {
            // keep waiting
        }

        if (attempts >= MAX_ATTEMPTS) clearInterval(timer)
    }, POLL_MS)
})

// --- AI draft still running: report progress, reload when it settles ------

document.querySelectorAll('[data-lesson-draft-url]').forEach((banner) => {
    const url = banner.dataset.lessonDraftUrl
    if (!url) return

    const live = ['queued', 'running']

    poll(url, (data) => {
        if (!live.includes(data.status)) {
            window.location.reload()
            return false
        }

        const label = banner.querySelector('[data-lesson-draft-status]')
        if (label && data.usage) {
            label.textContent = ''
        }

        return true
    })
})
