/*
 * Contract pin for the lesson block editor React island (PLAN react-02).
 *
 * The editor used to be imperative DOM code inside
 * resources/js/features/consultant-lesson-editor.js. It now renders the same
 * three panes from one server payload, so this suite freezes:
 *   - the exact `blocks[INDEX][...]` form names the Blade fallback posts;
 *   - the seven per-type field sets, page toggle, figure shelf, points and
 *     add/remove/move/focus interactions;
 *   - the page-router init(region) + cleanup contract and idempotent mounting;
 *   - the AI-draft banner poll moved over from consultant-lesson-import.js;
 *   - the "token classes only" rule (no inline style, no literal colour).
 */

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from 'jsdom'
import { act, createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'

const dom = new JSDOM('<!doctype html><html dir="rtl"><body></body></html>', {
    url: 'https://tenant.test',
})
const { window } = dom

// Production ordering: page-router.js defines this before any body bundle
// runs, which is how a feature entry knows not to self-initialize.
window.sapienstechRouter = { shared: true }

Object.assign(globalThis, {
    window,
    document: window.document,
    HTMLElement: window.HTMLElement,
    Node: window.Node,
    Element: window.Element,
    Event: window.Event,
    MouseEvent: window.MouseEvent,
    IS_REACT_ACT_ENVIRONMENT: true,
})

const { default: init, LessonEditor, pollDraftStatus, startDraftPolls } = await import(
    '../../resources/js/features/consultant-lesson-editor.js'
)
const { mountIsland } = await import('../../resources/js/react/mount.js')

const PAYLOAD = {
    saveUrl: '/consultant/lessons/editor/7/blocks',
    csrfToken: 'csrf-token',
    pages: [
        { number: 4, url: '/pages/4.png' },
        { number: 5, url: '/pages/5.png' },
    ],
    figures: [
        { id: 11, page: 4, url: '/figures/11.png', confidence: 'high' },
        { id: 12, page: 5, url: '/figures/12.png', confidence: 'low' },
    ],
    blocks: [
        { id: 1, type: 'heading', data: { text: 'تیتر', level: 2 }, sourcePage: 4, isDirty: false },
        { id: 2, type: 'paragraph', data: { text: 'متن پاراگراف' }, sourcePage: 4 },
        { id: 3, type: 'points', data: { items: [{ icon: '☀️', lead: 'lead', text: 'نکته' }] }, sourcePage: 5 },
        { id: 4, type: 'equation', data: { expr: 'x^2', caption: 'توضیح' } },
        { id: 5, type: 'tip', data: { title: 'کادر', text: 'متن نکته' } },
        { id: 6, type: 'figure', data: { figure_id: 11, placement: 'right', caption: 'زیرنویس' }, sourcePage: 4, figureUrl: '/figures/11.png' },
        { id: 7, type: 'figure_duo', data: { figure_ids: [11, 12], caption: '' }, sourcePage: 5, isDirty: true },
    ],
    errors: {},
}

const payload = (overrides = {}) => ({ ...PAYLOAD, ...overrides })
const render = (overrides = {}) => renderToStaticMarkup(
    createElement(LessonEditor, { payload: payload(overrides) }),
)

const assertTokenClassesOnly = (markup) => {
    assert.doesNotMatch(markup, /\sstyle=/, 'an island must not carry inline styling')
    assert.doesNotMatch(markup, /#[0-9a-f]{3,8}\b/i, 'an island must not carry a literal colour')
}

const flush = async () => {
    await act(async () => {})
}

const mount = async (overrides = {}) => {
    const container = window.document.createElement('div')
    window.document.body.appendChild(container)

    let island = null
    await act(async () => {
        island = mountIsland(container, LessonEditor, { payload: payload(overrides) })
    })

    return {
        container,
        island,
        rows: () => [...container.querySelectorAll('[data-lesson-block]')],
        async unmount() {
            await act(async () => {
                island?.unmount()
                container.remove()
            })
        },
    }
}

const click = async (element) => {
    await act(async () => {
        element.dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
    })
}

const change = async (element, value) => {
    await act(async () => {
        const proto = element.tagName === 'SELECT'
            ? window.HTMLSelectElement.prototype
            : element.tagName === 'TEXTAREA'
                ? window.HTMLTextAreaElement.prototype
                : window.HTMLInputElement.prototype
        // React tracks the previous value: go through the native setter so
        // the `input` event is not swallowed by its value tracker.
        Object.getOwnPropertyDescriptor(proto, 'value').set.call(element, value)
        const eventName = element.tagName === 'SELECT' ? 'change' : 'input'
        element.dispatchEvent(new window.Event(eventName, { bubbles: true }))
    })
}

/* ---------------------------------------------------- router contract --- */

test('the entry keeps the page-router init(region) + cleanup contract', async () => {
    assert.equal(typeof init, 'function', 'default export is still the page initializer')

    let cleanup = null
    await act(async () => {
        cleanup = init(window.document)
    })
    assert.equal(typeof cleanup, 'function', 'init() returns the router teardown')
    cleanup()
})

/* ---------------------------------------------------------- static form --- */

test('all seven block types render the exact server field names', () => {
    const html = render()

    assert.match(html, /<form[^>]*method="POST"/)
    assert.match(html, /action="\/consultant\/lessons\/editor\/7\/blocks"/)
    assert.match(html, /name="_token"/)
    assert.match(html, /id="lesson-blocks-form"/)

    const rows = (html.match(/data-lesson-block=/g) || []).length
    assert.equal(rows, 7, 'one server row per payload block')

    for (const type of ['heading', 'paragraph', 'points', 'equation', 'tip', 'figure', 'figure_duo']) {
        assert.match(html, new RegExp(`lesson-block--${type}`), `${type} row class`)
    }

    // hidden contract fields
    assert.match(html, /name="blocks\[0\]\[id\]" value="1"/)
    assert.match(html, /name="blocks\[0\]\[type\]" value="heading"/)
    assert.match(html, /name="blocks\[0\]\[data\]\[__keep\]"/)

    // heading + paragraph + tip text
    assert.match(html, /name="blocks\[0\]\[data\]\[text\]" value="تیتر"/)
    assert.match(html, /name="blocks\[0\]\[data\]\[level\]"/)
    assert.match(html, /name="blocks\[1\]\[data\]\[text\]"/)
    assert.match(html, /name="blocks\[4\]\[data\]\[title\]"/)
    assert.match(html, /name="blocks\[4\]\[data\]\[text\]"/)

    // points: one row per item, all three inputs
    assert.match(html, /name="blocks\[2\]\[data\]\[items\]\[0\]\[icon\]"/)
    assert.match(html, /name="blocks\[2\]\[data\]\[items\]\[0\]\[lead\]"/)
    assert.match(html, /name="blocks\[2\]\[data\]\[items\]\[0\]\[text\]"/)
    assert.match(html, /data-lesson-add-point/)

    // equation
    assert.match(html, /name="blocks\[3\]\[data\]\[expr\]"/)
    assert.match(html, /name="blocks\[3\]\[data\]\[caption\]"/)

    // figure + figure_duo
    assert.match(html, /name="blocks\[5\]\[data\]\[figure_id\]"/)
    assert.match(html, /name="blocks\[5\]\[data\]\[placement\]"/)
    assert.match(html, /data-lesson-figure-preview/)
    assert.match(html, /src="\/figures\/11.png"/)
    const duoSelects = (html.match(/name="blocks\[6\]\[data\]\[figure_ids\]\[\]"/g) || []).length
    assert.equal(duoSelects, 2, 'two selects post a figure_ids[] list')
    assert.match(html, /شکل صفحهٔ ۴/)
    assert.match(html, /شکل صفحهٔ ۵/)

    // source page belongs to every row, and the shelf/pages get their hooks
    assert.match(html, /name="blocks\[0\]\[source_page\]"/)
    assert.match(html, /data-lesson-figure-id="11"/)
    assert.match(html, /data-lesson-figure-id="12"/)
    assert.match(html, /data-page-number="4"/)
    assert.match(html, /data-page-number="5"/)
    assert.match(html, /data-lesson-page-jump="4"/)
    assert.match(html, /data-lesson-page-jump="5"/)
    assert.match(html, /class="count-badge">۷</)
    assertTokenClassesOnly(html)
})

test('the first source page is the active one and the rest start hidden', () => {
    const html = render()

    assert.match(html, /data-page-number="4"(?![^>]*hidden)/)
    assert.match(html, /data-page-number="5"[^>]*hidden/)
    assert.match(html, /lesson-chip is-active/)
})

test('an empty lesson renders the empty note and a zero count', () => {
    const html = render({ blocks: [] })

    assert.match(html, /data-lesson-blocks-empty/)
    assert.match(html, /class="count-badge">۰</)
    assert.match(html, /این درس هنوز بلوکی ندارد/)
})

test('keyed validation errors render next to their field', () => {
    const html = render({ errors: { 'blocks.0.data.text': 'متن تیتر لازم است.' } })

    assert.match(html, /class="lesson-field-error">متن تیتر لازم است\./)
    assertTokenClassesOnly(html)
})

/* --------------------------------------------------------- interactions --- */

test('add, remove and move keep the posted indices contiguous', async () => {
    const view = await mount({ blocks: PAYLOAD.blocks.slice(0, 2) })

    try {
        assert.equal(view.rows().length, 2)

        const select = view.container.querySelector('[data-lesson-add-type]')
        await change(select, 'tip')
        await click(view.container.querySelector('[data-lesson-add-block]'))

        assert.equal(view.rows().length, 3)
        assert.match(view.rows()[2].className, /lesson-block--tip/)
        assert.match(view.rows()[2].innerHTML, /name="blocks\[2\]\[data\]\[title\]"/)
        assert.match(view.container.querySelector('.count-badge').textContent, /۳/)

        await click(view.rows()[0].querySelector('[data-lesson-remove]'))
        assert.equal(view.rows().length, 2)
        assert.match(view.rows()[0].className, /lesson-block--paragraph/)
        assert.match(view.rows()[0].innerHTML, /name="blocks\[0\]\[data\]\[text\]"/)
        assert.match(view.rows()[1].innerHTML, /name="blocks\[1\]\[data\]\[title\]"/)

        await click(view.rows()[0].querySelector('[data-lesson-move="down"]'))
        assert.match(view.rows()[0].className, /lesson-block--tip/)
        assert.match(view.rows()[1].className, /lesson-block--paragraph/)
        assert.match(view.rows()[0].innerHTML, /name="blocks\[0\]\[data\]\[title\]"/)
        assert.equal(window.document.activeElement?.dataset?.lessonMove, 'down', 'focus follows the moved row')
    } finally {
        await view.unmount()
    }
})

test('points clone, clear and reindex, and the last row is guarded', async () => {
    const view = await mount({ blocks: [PAYLOAD.blocks[2]] })

    try {
        const pointRow = () => view.container.querySelectorAll('[data-lesson-point]')

        await click(view.container.querySelector('[data-lesson-add-point]'))
        assert.equal(pointRow().length, 2)
        assert.match(pointRow()[1].innerHTML, /name="blocks\[0\]\[data\]\[items\]\[1\]\[icon\]"/)
        assert.equal(window.document.activeElement?.name, 'blocks[0][data][items][1][icon]', 'new point takes focus')

        // Removing the last of two reindexes the remainder to items[0].
        await click(pointRow()[1].querySelector('[data-lesson-remove-point]'))
        assert.equal(pointRow().length, 1)
        assert.match(pointRow()[0].innerHTML, /name="blocks\[0\]\[data\]\[items\]\[0\]\[text\]"/)

        // The last remaining row is cleared, never removed.
        const textInput = pointRow()[0].querySelector('[name$="[text]"]')
        await change(textInput, 'چیزی')
        await click(pointRow()[0].querySelector('[data-lesson-remove-point]'))
        assert.equal(pointRow().length, 1)
        assert.equal(pointRow()[0].querySelector('[name$="[text]"]').value, '')
    } finally {
        await view.unmount()
    }
})

test('a figure pick appends a figure block with preview, page and hidden id', async () => {
    const view = await mount({ blocks: [] })

    try {
        await click(view.container.querySelector('[data-lesson-figure-id="11"]'))

        assert.equal(view.rows().length, 1)
        const row = view.rows()[0]
        assert.match(row.className, /lesson-block--figure/)
        assert.equal(row.querySelector('[data-lesson-figure-input]').value, '11')
        assert.equal(row.querySelector('[name$="[source_page]"]').value, '4')
        assert.match(row.querySelector('[data-lesson-figure-preview] img').getAttribute('src'), /\/figures\/11\.png/)
        assert.match(view.container.querySelector('.count-badge').textContent, /۱/)
    } finally {
        await view.unmount()
    }
})

test('page chips toggle the source pane without reloading', async () => {
    const view = await mount()

    try {
        const pageFigures = () => [...view.container.querySelectorAll('[data-page-number]')]
        const switcher = view.container.querySelector('.lesson-source-switch')

        assert.equal(pageFigures()[0].hasAttribute('hidden'), false)
        assert.equal(pageFigures()[1].hasAttribute('hidden'), true)

        await click(switcher.querySelector('[data-lesson-page-jump="5"]'))
        assert.equal(pageFigures()[0].hasAttribute('hidden'), true)
        assert.equal(pageFigures()[1].hasAttribute('hidden'), false)
        assert.match(switcher.querySelector('[data-lesson-page-jump="5"]').className, /is-active/)

        // A block's page chip drives the same state.
        await click(view.rows()[0].querySelector('[data-lesson-page-jump="4"]'))
        assert.equal(pageFigures()[0].hasAttribute('hidden'), false)
        assert.equal(pageFigures()[1].hasAttribute('hidden'), true)
    } finally {
        await view.unmount()
    }
})

test('a controlled text edit updates state and survives a reorder', async () => {
    const view = await mount({ blocks: PAYLOAD.blocks.slice(0, 2) })

    try {
        const input = view.rows()[0].querySelector('[name="blocks[0][data][text]"]')
        await change(input, 'ویرایش')

        await click(view.rows()[0].querySelector('[data-lesson-move="down"]'))
        assert.equal(
            view.rows()[1].querySelector('[name="blocks[1][data][text]"]').value,
            'ویرایش',
            'state follows the row when indices change',
        )
    } finally {
        await view.unmount()
    }
})

/* ------------------------------------------------------ mount lifecycle --- */

test('init is idempotent and cleanup restores the server fallback', async () => {
    const host = window.document.createElement('div')
    host.className = 'lesson-editor-grid'
    host.setAttribute('data-lesson-editor-island', '')
    host.innerHTML = '<script type="application/json" data-lesson-editor-payload>'
        + JSON.stringify(payload())
        + '</script><p id="server-fallback">fallback</p>'
    window.document.body.appendChild(host)

    let cleanup = null
    await act(async () => {
        cleanup = init(window.document)
    })

    assert.equal(window.document.querySelectorAll('[data-lesson-editor-island]').length, 1)
    assert.equal(host.querySelectorAll('.lesson-blocks').length, 1)
    assert.equal(host.querySelector('#server-fallback'), null, 'React owns the host after mount')
    assert.equal(host.querySelectorAll('form#lesson-blocks-form').length, 1)

    // A second init on the same DOM must not create a second root.
    let second = null
    await act(async () => {
        second = init(window.document)
    })
    assert.equal(host.querySelectorAll('.lesson-blocks').length, 1)
    assert.equal(host.querySelectorAll('form#lesson-blocks-form').length, 1)
    await act(async () => second())

    await act(async () => cleanup())
    assert.equal(host.querySelector('#server-fallback')?.textContent, 'fallback')
    assert.ok(host.querySelector('[data-lesson-editor-payload]'), 'payload is back with the fallback')
    await act(async () => cleanup()) // double teardown is a no-op

    let again = null
    await act(async () => {
        again = init(window.document)
    })
    assert.equal(host.querySelectorAll('.lesson-blocks').length, 1, 'the host can mount again after teardown')
    await act(async () => again())
    host.remove()
})

test('a host without a valid payload keeps the server rows', async () => {
    const host = window.document.createElement('div')
    host.setAttribute('data-lesson-editor-island', '')
    host.innerHTML = '<p id="server-fallback">fallback</p>'
    window.document.body.appendChild(host)

    let cleanup = null
    await act(async () => {
        cleanup = init(window.document)
    })

    assert.equal(host.querySelector('#server-fallback')?.textContent, 'fallback')
    await act(async () => cleanup())
    host.remove()
})

/* ------------------------------------------------------------ AI draft --- */

test('the draft poll only reloads once the draft leaves the live states', async () => {
    const banner = window.document.createElement('div')
    banner.dataset.lessonDraftUrl = '/consultant/lessons/editor/7/ai/9'
    let reloads = 0
    const reload = () => { reloads += 1 }

    const running = async () => ({ ok: true, json: async () => ({ status: 'running', usage: { used: 1 } }) })
    assert.equal(await pollDraftStatus(banner, { fetchImpl: running, reload }), true)
    assert.equal(reloads, 0)

    const queued = async () => ({ ok: true, json: async () => ({ status: 'queued' }) })
    assert.equal(await pollDraftStatus(banner, { fetchImpl: queued, reload }), true)

    const ready = async () => ({ ok: true, json: async () => ({ status: 'ready' }) })
    assert.equal(await pollDraftStatus(banner, { fetchImpl: ready, reload }), false)
    assert.equal(reloads, 1, 'settling reloads the page exactly once')

    const refused = async () => ({ ok: true, json: async () => ({ status: 'refused' }) })
    assert.equal(await pollDraftStatus(banner, { fetchImpl: refused, reload }), false)
    assert.equal(reloads, 2)

    const offline = async () => { throw new Error('offline') }
    assert.equal(await pollDraftStatus(banner, { fetchImpl: offline, reload }), true)
    assert.equal(reloads, 2, 'a failed poll is not a failed job')

    const noUrl = window.document.createElement('div')
    assert.equal(await pollDraftStatus(noUrl, { fetchImpl: running, reload }), false)
})

test('startDraftPolls stops itself when the draft settles', async () => {
    const banner = window.document.createElement('div')
    banner.dataset.lessonDraftUrl = '/consultant/lessons/editor/7/ai/9'
    window.document.body.appendChild(banner)

    let calls = 0
    let reloads = 0
    const fetchImpl = async () => {
        calls += 1
        return { ok: true, json: async () => ({ status: calls < 3 ? 'running' : 'ready' }) }
    }

    const stop = startDraftPolls(window.document, {
        fetchImpl,
        reload: () => { reloads += 1 },
        intervalMs: 5,
        maxAttempts: 20,
    })

    await new Promise((resolve) => setTimeout(resolve, 80))
    stop()

    const settledCalls = calls

    assert.ok(calls >= 3, `expected the interval to poll, saw ${calls} calls`)
    assert.equal(reloads, 1, 'settling reloads once and the interval stops')

    await new Promise((resolve) => setTimeout(resolve, 30))
    assert.equal(calls, settledCalls, 'teardown stops the interval')

    banner.remove()
})