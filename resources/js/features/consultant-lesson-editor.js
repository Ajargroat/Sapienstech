/*
 * Lesson block editor — the "contractor" layer (PLAN react-02).
 *
 * The three-pane grid is a React island mounted inside the server-rendered
 * `.lesson-editor-grid` host. The Blade rows stay in the HTML as the
 * pre-mount / JS-off fallback: they post the same `blocks[INDEX][...]` field
 * names, so a browser without JavaScript still saves a valid ordered list.
 *
 * Boundaries, on purpose:
 *   - The server owns validation and ordering. This file only makes editing
 *     pleasant; the DOM order IS the order, and names are rendered from the
 *     array index, so a drag can never leave duplicate `blocks[7]` keys.
 *   - The page router contract is `export default function init(region)` and
 *     an optional cleanup. There is no top-level `document.querySelectorAll`
 *     binding and no document-level click delegation: React owns its events,
 *     and `init()` is idempotent per host so a hard load that also boots the
 *     router cannot mount the same tree twice.
 *   - Components stay .js + createElement (the resources/js/react/primitives.js
 *     idiom) because `node --test` cannot import JSX.
 *   - No literal styling: only the semantic lesson classes, which resolve the
 *     tenant token sheet in resources/css/features/lessons.css.
 */

import { createElement, Fragment, useEffect, useRef, useState } from 'react'
import { mountIsland } from '../react/mount.js'

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
const POLL_MS = 4000
const POLL_MAX_ATTEMPTS = 75
const LIVE_DRAFT_STATES = ['queued', 'running']
const EDITOR_SELECTOR = '[data-lesson-editor-island]'
const PAYLOAD_SELECTOR = '[data-lesson-editor-payload]'

const DEFAULT_TYPES = [
    { value: 'heading', label: 'تیتر' },
    { value: 'paragraph', label: 'پاراگراف' },
    { value: 'points', label: 'نکته‌های فهرستی' },
    { value: 'equation', label: 'معادله' },
    { value: 'tip', label: 'کادر نکته' },
    { value: 'figure', label: 'تصویر' },
    { value: 'figure_duo', label: 'دو تصویر کنار هم' },
]

const DEFAULT_PLACEMENTS = [
    { value: 'wide', label: 'تمام‌عرض' },
    { value: 'right', label: 'کنار متن (راست)' },
    { value: 'left', label: 'کنار متن (چپ)' },
]

const STRINGS = {
    sourceTitle: 'صفحهٔ کتاب',
    sourceEmpty: 'تصویری برای این صفحه‌ها ذخیره نشده است.',
    blocksTitle: 'بلوک‌های درس',
    blocksEmpty: 'این درس هنوز بلوکی ندارد. با «پیش‌نویس هوش مصنوعی» شروع کنید یا یک بلوک دستی اضافه کنید.',
    shelfTitle: 'شکل‌های برش‌خورده',
    shelfEmpty: 'شکلی در این محدودهٔ صفحه پیدا نشد.',
    shelfHint: 'روی یک شکل بزنید تا به انتهای درس اضافه شود.',
    addBlock: 'افزودن بلوک',
    add: 'افزودن',
    save: 'ثبت تغییرات',
    up: 'بالا',
    down: 'پایین',
    remove: 'حذف بلوک',
    dragTitle: 'برای جابه‌جایی بکشید',
    dirtyTitle: 'دست انسان روی این بلوک بوده',
    edited: 'ویرایش‌شده',
    fresh: 'جدید',
    headingText: 'متن تیتر',
    level: 'سطح',
    paragraphText: 'متن پاراگراف',
    icon: 'نماد',
    iconPlaceholder: '☀️',
    lead: 'عنوان کوتاه (اختیاری)',
    pointText: 'متن نکته',
    removePoint: 'حذف نکته',
    addPoint: 'افزودن نکته',
    equation: 'عبارت ریاضی (بدون تغییر از کتاب)',
    equationCaption: 'توضیح زیر معادله (اختیاری)',
    tipTitle: 'عنوان کادر (اختیاری)',
    tipPlaceholder: 'آیا می‌دانستید؟',
    tipText: 'متن نکته',
    figureEmpty: 'شکلی انتخاب نشده است. از ستون شکل‌ها یکی را بزنید.',
    placement: 'جای تصویر',
    caption: 'زیرنویس (اختیاری)',
    image: 'تصویر',
    chooseFigure: '— انتخاب کنید —',
    figurePage: 'شکل صفحهٔ',
    sourcePage: 'صفحهٔ منبع',
    pageChip: 'صفحهٔ',
    pageChipTitle: 'صفحهٔ کتاب را نشان بده',
    pageJumpTitle: 'برای افزودن به انتهای درس کلیک کنید',
}

/* ------------------------------------------------------------------ helpers */

/** Render a Latin numeral as the Persian digit the Blade fallback uses. */
export function persianDigits(value) {
    return String(value ?? '').replace(/[0-9]/g, (digit) => PERSIAN_DIGITS[Number(digit)])
}

/** `blocks[INDEX][a][b]` — the exact server contract from block-row.blade.php. */
function inputName(index, ...parts) {
    return `blocks[${index}][${parts.join('][')}]`
}

function fieldError(errors, index, field) {
    return errors[`blocks.${index}.data.${field}`] || ''
}

function fieldClass(extra) {
    return extra ? `settings-field ${extra}` : 'settings-field'
}

function normaliseBlock(block, index) {
    const id = block?.id ?? null
    const data = block?.data && typeof block.data === 'object' ? { ...block.data } : {}

    return {
        key: id !== null && id !== undefined ? `id-${id}` : `new-${index + 1}`,
        id,
        type: String(block?.type || 'paragraph'),
        data,
        sourcePage: block?.sourcePage ?? null,
        isDirty: Boolean(block?.isDirty),
        isNew: block?.isNew ?? (id === null || id === undefined),
        figureUrl: block?.figureUrl || null,
    }
}

function normalisePayload(payload = {}) {
    const types = Array.isArray(payload.types) && payload.types.length ? payload.types : DEFAULT_TYPES
    const placements = Array.isArray(payload.placements) && payload.placements.length
        ? payload.placements
        : DEFAULT_PLACEMENTS

    return {
        saveUrl: payload.saveUrl || '',
        csrfToken: payload.csrfToken || '',
        types,
        placements,
        blocks: Array.isArray(payload.blocks) ? payload.blocks.map(normaliseBlock) : [],
        figures: Array.isArray(payload.figures) ? payload.figures : [],
        pages: Array.isArray(payload.pages) ? payload.pages : [],
        errors: payload.errors && typeof payload.errors === 'object' ? payload.errors : {},
    }
}

function blankBlock(type, key) {
    const data = {
        heading: { text: '', level: 2 },
        paragraph: { text: '' },
        points: { items: [{ icon: '', lead: '', text: '' }] },
        equation: { expr: '', caption: '' },
        tip: { title: '', text: '' },
        figure: { figure_id: '', placement: 'wide', caption: '' },
        figure_duo: { figure_ids: ['', ''], caption: '' },
    }[type] || { text: '' }

    return {
        key,
        id: null,
        type,
        data,
        sourcePage: null,
        isDirty: false,
        isNew: true,
        figureUrl: null,
    }
}/* ------------------------------------------------------------- source pane */

export function SourcePane({ pages = [], activePage = null, onJump }) {
    const hasPages = pages.length > 0
    const jump = (page) => {
        if (typeof onJump === 'function') onJump(page)
    }

    return createElement(
        'aside',
        { className: 'panel student-panel lesson-source-pane' },
        createElement(
            'header',
            { className: 'student-panel-head' },
            createElement('h2', null, createElement('i', { className: 'fas fa-image' }), ' ', STRINGS.sourceTitle),
        ),
        hasPages
            ? createElement(
                Fragment,
                null,
                createElement(
                    'div',
                    { className: 'lesson-source-pages', 'data-lesson-pages': '' },
                    pages.map((page) => createElement(
                        'figure',
                        {
                            key: page.number,
                            className: 'lesson-source-page',
                            'data-page-number': String(page.number),
                            hidden: activePage !== null && Number(activePage) !== Number(page.number),
                        },
                        createElement('img', {
                            src: page.url,
                            alt: `صفحهٔ ${persianDigits(page.number)}`,
                            loading: 'lazy',
                        }),
                        createElement('figcaption', null, `صفحهٔ ${persianDigits(page.number)}`),
                    )),
                ),
                pages.length > 1
                    ? createElement(
                        'div',
                        { className: 'lesson-source-switch' },
                        pages.map((page) => createElement(
                            'button',
                            {
                                key: page.number,
                                type: 'button',
                                className: `lesson-chip${Number(activePage) === Number(page.number) ? ' is-active' : ''}`,
                                'data-lesson-page-jump': String(page.number),
                                onClick: () => jump(page.number),
                            },
                            persianDigits(page.number),
                        )),
                    )
                    : null,
            )
            : createElement('p', { className: 'lesson-empty-note' }, STRINGS.sourceEmpty),
    )
}

/* ------------------------------------------------------------ figure shelf */

export function FigureShelf({ figures = [], onPick }) {
    if (figures.length === 0) {
        return createElement(
            'aside',
            { className: 'panel student-panel lesson-figure-pane' },
            createElement(
                'header',
                { className: 'student-panel-head' },
                createElement('h2', null, createElement('i', { className: 'fas fa-crop-simple' }), ' ', STRINGS.shelfTitle),
            ),
            createElement('p', { className: 'lesson-empty-note' }, STRINGS.shelfEmpty),
        )
    }

    return createElement(
        'aside',
        { className: 'panel student-panel lesson-figure-pane' },
        createElement(
            'header',
            { className: 'student-panel-head' },
            createElement('h2', null, createElement('i', { className: 'fas fa-crop-simple' }), ' ', STRINGS.shelfTitle),
        ),
        createElement(
            'div',
            { className: 'lesson-figure-shelf' },
            figures.map((figure) => createElement(
                'button',
                {
                    key: figure.id,
                    type: 'button',
                    className: `lesson-figure-pick lesson-figure-card--${figure.confidence || 'high'}`,
                    'data-lesson-figure-id': String(figure.id),
                    'data-lesson-figure-url': figure.url || '',
                    'data-lesson-figure-page': String(figure.page ?? ''),
                    title: `صفحهٔ ${persianDigits(figure.page)} — ${STRINGS.pageJumpTitle}`,
                    onClick: () => (typeof onPick === 'function' ? onPick(figure) : undefined),
                },
                createElement('img', {
                    src: figure.url,
                    alt: `شکل صفحهٔ ${persianDigits(figure.page)}`,
                    loading: 'lazy',
                }),
            )),
        ),
        createElement('p', { className: 'lesson-empty-note' }, STRINGS.shelfHint),
    )
}/* --------------------------------------------------------------- block row */

export function BlockRow({
    block,
    index,
    types = DEFAULT_TYPES,
    placements = DEFAULT_PLACEMENTS,
    figures = [],
    errors = {},
    dragging = false,
    onPatch,
    onSourcePage,
    onMove,
    onRemove,
    onAddPoint,
    onRemovePoint,
    onPointChange,
    onPageJump,
    onDragStart,
    onDragEnd,
}) {
    const data = block.data || {}
    const call = (fn, ...args) => {
        if (typeof fn === 'function') fn(...args)
    }
    const patch = (next) => call(onPatch, index, next)
    const label = (types.find((entry) => entry.value === block.type) || {}).label || block.type

    const field = (labelText, control, extra = '', errorText = '') => createElement(
        'label',
        { className: fieldClass(extra) },
        createElement('span', { className: 'settings-field-label' }, labelText),
        control,
        errorText ? createElement('span', { className: 'lesson-field-error' }, errorText) : null,
    )

    let body = null

    switch (block.type) {
        case 'heading':
            body = createElement(
                Fragment,
                null,
                field(
                    STRINGS.headingText,
                    createElement('input', {
                        type: 'text',
                        className: 'settings-input',
                        name: inputName(index, 'data', 'text'),
                        value: data.text ?? '',
                        required: true,
                        onChange: (event) => patch({ text: event.target.value }),
                    }),
                    '',
                    fieldError(errors, index, 'text'),
                ),
                field(
                    STRINGS.level,
                    createElement(
                        'select',
                        {
                            className: 'settings-input',
                            name: inputName(index, 'data', 'level'),
                            value: String(data.level ?? 2),
                            onChange: (event) => patch({ level: Number(event.target.value) }),
                        },
                        [2, 3].map((level) => createElement('option', { key: level, value: String(level) }, `H${level}`)),
                    ),
                    'settings-field--narrow',
                ),
            )
            break

        case 'paragraph':
            body = field(
                STRINGS.paragraphText,
                createElement('textarea', {
                    className: 'settings-input lesson-textarea',
                    rows: 5,
                    name: inputName(index, 'data', 'text'),
                    required: true,
                    value: data.text ?? '',
                    onChange: (event) => patch({ text: event.target.value }),
                }),
                '',
                fieldError(errors, index, 'text'),
            )
            break

        case 'points': {
            const items = Array.isArray(data.items) ? data.items : []
            body = createElement(
                Fragment,
                null,
                createElement(
                    'div',
                    { className: 'lesson-points', 'data-lesson-points': '' },
                    items.map((item, itemIndex) => createElement(
                        'div',
                        { className: 'lesson-point-row', 'data-lesson-point': '', key: itemIndex },
                        createElement('input', {
                            type: 'text',
                            className: 'settings-input lesson-point-icon',
                            name: inputName(index, 'data', 'items', itemIndex, 'icon'),
                            value: item.icon ?? '',
                            placeholder: STRINGS.iconPlaceholder,
                            maxLength: 8,
                            'aria-label': STRINGS.icon,
                            onChange: (event) => call(onPointChange, index, itemIndex, 'icon', event.target.value),
                        }),
                        createElement('input', {
                            type: 'text',
                            className: 'settings-input',
                            name: inputName(index, 'data', 'items', itemIndex, 'lead'),
                            value: item.lead ?? '',
                            placeholder: STRINGS.lead,
                            onChange: (event) => call(onPointChange, index, itemIndex, 'lead', event.target.value),
                        }),
                        createElement('input', {
                            type: 'text',
                            className: 'settings-input',
                            name: inputName(index, 'data', 'items', itemIndex, 'text'),
                            value: item.text ?? '',
                            placeholder: STRINGS.pointText,
                            required: true,
                            onChange: (event) => call(onPointChange, index, itemIndex, 'text', event.target.value),
                        }),
                        createElement(
                            'button',
                            {
                                type: 'button',
                                className: 'lesson-icon-button lesson-icon-button--danger',
                                'data-lesson-remove-point': '',
                                title: STRINGS.removePoint,
                                onClick: () => call(onRemovePoint, index, itemIndex),
                            },
                            createElement('i', { className: 'fas fa-xmark' }),
                        ),
                    )),
                ),
                fieldError(errors, index, 'items')
                    ? createElement('span', { className: 'lesson-field-error' }, fieldError(errors, index, 'items'))
                    : null,
                createElement(
                    'button',
                    {
                        type: 'button',
                        className: 'secondary-button',
                        'data-lesson-add-point': '',
                        onClick: () => call(onAddPoint, index),
                    },
                    createElement('i', { className: 'fas fa-plus' }),
                    ' ',
                    STRINGS.addPoint,
                ),
            )
            break
        }

        case 'equation':
            body = createElement(
                Fragment,
                null,
                field(
                    STRINGS.equation,
                    createElement('textarea', {
                        className: 'settings-input lesson-textarea lesson-textarea--math',
                        rows: 2,
                        dir: 'ltr',
                        name: inputName(index, 'data', 'expr'),
                        required: true,
                        value: data.expr ?? '',
                        onChange: (event) => patch({ expr: event.target.value }),
                    }),
                    '',
                    fieldError(errors, index, 'expr'),
                ),
                field(
                    STRINGS.equationCaption,
                    createElement('input', {
                        type: 'text',
                        className: 'settings-input',
                        name: inputName(index, 'data', 'caption'),
                        value: data.caption ?? '',
                        onChange: (event) => patch({ caption: event.target.value }),
                    }),
                ),
            )
            break

        case 'tip':
            body = createElement(
                Fragment,
                null,
                field(
                    STRINGS.tipTitle,
                    createElement('input', {
                        type: 'text',
                        className: 'settings-input',
                        name: inputName(index, 'data', 'title'),
                        value: data.title ?? '',
                        placeholder: STRINGS.tipPlaceholder,
                        onChange: (event) => patch({ title: event.target.value }),
                    }),
                ),
                field(
                    STRINGS.tipText,
                    createElement('textarea', {
                        className: 'settings-input lesson-textarea',
                        rows: 3,
                        name: inputName(index, 'data', 'text'),
                        required: true,
                        value: data.text ?? '',
                        onChange: (event) => patch({ text: event.target.value }),
                    }),
                    '',
                    fieldError(errors, index, 'text'),
                ),
            )
            break

        case 'figure': {
            const figureId = data.figure_id ?? ''
            body = createElement(
                Fragment,
                null,
                createElement('input', {
                    type: 'hidden',
                    name: inputName(index, 'data', 'figure_id'),
                    value: figureId,
                    readOnly: true,
                    'data-lesson-figure-input': '',
                }),
                createElement(
                    'div',
                    { className: 'lesson-figure-preview', 'data-lesson-figure-preview': '' },
                    figureId && block.figureUrl
                        ? createElement('img', { src: block.figureUrl, alt: '' })
                        : createElement('span', { className: 'lesson-empty-note' }, STRINGS.figureEmpty),
                ),
                field(
                    STRINGS.placement,
                    createElement(
                        'select',
                        {
                            className: 'settings-input',
                            name: inputName(index, 'data', 'placement'),
                            value: data.placement || 'wide',
                            onChange: (event) => patch({ placement: event.target.value }),
                        },
                        placements.map((placement) => createElement(
                            'option',
                            { key: placement.value, value: placement.value },
                            placement.label,
                        )),
                    ),
                    'settings-field--narrow',
                ),
                field(
                    STRINGS.caption,
                    createElement('input', {
                        type: 'text',
                        className: 'settings-input',
                        name: inputName(index, 'data', 'caption'),
                        value: data.caption ?? '',
                        onChange: (event) => patch({ caption: event.target.value }),
                    }),
                ),
            )
            break
        }

        case 'figure_duo': {
            const ids = Array.isArray(data.figure_ids) ? data.figure_ids : []
            body = createElement(
                Fragment,
                null,
                [0, 1].map((slot) => createElement(Fragment, { key: slot }, field(
                    `${STRINGS.image} ${persianDigits(slot + 1)}`,
                    createElement(
                        'select',
                        {
                            key: slot,
                            className: 'settings-input',
                            name: `${inputName(index, 'data', 'figure_ids')}[]`,
                            value: String(ids[slot] ?? ''),
                            onChange: (event) => {
                                const next = [String(ids[0] ?? ''), String(ids[1] ?? '')]
                                next[slot] = event.target.value
                                patch({ figure_ids: next })
                            },
                        },
                        createElement('option', { key: 'none', value: '' }, STRINGS.chooseFigure),
                        figures.map((figure) => createElement(
                            'option',
                            { key: figure.id, value: String(figure.id) },
                            `${STRINGS.figurePage} ${persianDigits(figure.page)}`,
                        )),
                    ),
                ))),
                field(
                    STRINGS.caption,
                    createElement('input', {
                        type: 'text',
                        className: 'settings-input',
                        name: inputName(index, 'data', 'caption'),
                        value: data.caption ?? '',
                        onChange: (event) => patch({ caption: event.target.value }),
                    }),
                ),
                fieldError(errors, index, 'figure_ids')
                    ? createElement('span', { className: 'lesson-field-error' }, fieldError(errors, index, 'figure_ids'))
                    : null,
            )
            break
        }

        default:
            body = createElement('p', { className: 'lesson-empty-note' }, block.type)
    }

    return createElement(
        'article',
        {
            className: `lesson-block lesson-block--${block.type}${dragging ? ' is-dragging' : ''}`,
            'data-lesson-block': '',
            'data-type': block.type,
            'data-lesson-block-key': block.key,
            onDragStart: onDragStart ? (event) => onDragStart(event, block.key) : undefined,
            onDragEnd: onDragEnd || undefined,
        },
        createElement('input', {
            type: 'hidden',
            name: inputName(index, 'id'),
            value: block.id ?? '',
            readOnly: true,
        }),
        createElement('input', {
            type: 'hidden',
            name: inputName(index, 'type'),
            value: block.type,
            readOnly: true,
        }),
        createElement('input', {
            type: 'hidden',
            name: inputName(index, 'data', '__keep'),
            value: '1',
            readOnly: true,
        }),
        createElement(
            'header',
            { className: 'lesson-block-head' },
            createElement(
                'span',
                { className: 'lesson-block-type' },
                createElement('i', {
                    className: 'fas fa-grip-vertical',
                    'data-lesson-drag-handle': '',
                    title: STRINGS.dragTitle,
                    draggable: true,
                }),
                ' ',
                label,
            ),
            createElement(
                'span',
                { className: 'lesson-block-tools' },
                block.isNew
                    ? createElement('span', { className: 'lesson-chip lesson-chip--dirty' }, STRINGS.fresh)
                    : block.isDirty
                        ? createElement(
                            'span',
                            { className: 'lesson-chip lesson-chip--dirty', title: STRINGS.dirtyTitle },
                            STRINGS.edited,
                        )
                        : null,
                block.sourcePage
                    ? createElement(
                        'button',
                        {
                            type: 'button',
                            className: 'lesson-chip',
                            'data-lesson-page-jump': String(block.sourcePage),
                            title: STRINGS.pageChipTitle,
                            onClick: () => call(onPageJump, block.sourcePage),
                        },
                        `${STRINGS.pageChip} ${persianDigits(block.sourcePage)}`,
                    )
                    : null,
                createElement(
                    'button',
                    {
                        type: 'button',
                        className: 'lesson-icon-button',
                        'data-lesson-move': 'up',
                        title: STRINGS.up,
                        onClick: () => call(onMove, index, 'up'),
                    },
                    createElement('i', { className: 'fas fa-arrow-up' }),
                ),
                createElement(
                    'button',
                    {
                        type: 'button',
                        className: 'lesson-icon-button',
                        'data-lesson-move': 'down',
                        title: STRINGS.down,
                        onClick: () => call(onMove, index, 'down'),
                    },
                    createElement('i', { className: 'fas fa-arrow-down' }),
                ),
                createElement(
                    'button',
                    {
                        type: 'button',
                        className: 'lesson-icon-button lesson-icon-button--danger',
                        'data-lesson-remove': '',
                        title: STRINGS.remove,
                        onClick: () => call(onRemove, index),
                    },
                    createElement('i', { className: 'fas fa-trash' }),
                ),
            ),
        ),
        createElement(
            'div',
            { className: 'lesson-block-body' },
            body,
            field(
                STRINGS.sourcePage,
                createElement('input', {
                    type: 'number',
                    min: 1,
                    className: 'settings-input',
                    name: inputName(index, 'source_page'),
                    value: block.sourcePage ?? '',
                    onChange: (event) => {
                        const raw = event.target.value
                        call(onSourcePage, index, raw === '' ? null : Number(raw))
                    },
                }),
                'settings-field--narrow lesson-source-field',
            ),
        ),
    )
}/* ------------------------------------------------------------- the island */

export function LessonEditor({ payload }) {
    const modelRef = useRef(null)
    if (modelRef.current === null) modelRef.current = normalisePayload(payload)
    const model = modelRef.current

    const [blocks, setBlocks] = useState(() => model.blocks)
    const [activePage, setActivePage] = useState(() => (model.pages[0] ? model.pages[0].number : null))
    const [addType, setAddType] = useState(() => (model.types[0] ? model.types[0].value : 'heading'))
    const [dragKey, setDragKey] = useState(null)
    const [focusRequest, setFocusRequest] = useState(null)
    const listRef = useRef(null)
    const nextKey = useRef(model.blocks.length + 1)
    const scrollNext = useRef(false)

    const makeKey = () => `new-${nextKey.current++}`

    const patchData = (index, patch) => {
        setBlocks((previous) => previous.map((block, i) => (
            i === index ? { ...block, data: { ...block.data, ...patch } } : block
        )))
    }

    const setSourcePage = (index, value) => {
        setBlocks((previous) => previous.map((block, i) => (
            i === index ? { ...block, sourcePage: value } : block
        )))
    }

    const removeBlock = (index) => {
        setBlocks((previous) => previous.filter((_, i) => i !== index))
    }

    const moveBlock = (index, direction) => {
        const target = direction === 'up' ? index - 1 : index + 1
        if (target < 0 || target >= blocks.length) return

        const key = blocks[index].key

        setBlocks((previous) => {
            const next = [...previous]
            const [row] = next.splice(index, 1)
            next.splice(target, 0, row)
            return next
        })

        setFocusRequest({ kind: 'move', key, direction })
    }

    const addBlock = () => {
        const type = model.types.some((entry) => entry.value === addType)
            ? addType
            : (model.types[0] ? model.types[0].value : 'paragraph')
        const block = blankBlock(type, makeKey())
        setBlocks((previous) => [...previous, block])
    }

    const pickFigure = (figure) => {
        const block = {
            key: makeKey(),
            id: null,
            type: 'figure',
            data: { figure_id: figure.id, placement: 'wide', caption: '' },
            sourcePage: figure.page ?? null,
            isDirty: false,
            isNew: true,
            figureUrl: figure.url || null,
        }

        setBlocks((previous) => [...previous, block])
        scrollNext.current = true
    }

    const addPoint = (index) => {
        const block = blocks[index]
        if (!block) return

        const items = Array.isArray(block.data.items) ? [...block.data.items] : []
        const pointIndex = items.length
        items.push({ icon: '', lead: '', text: '' })

        setBlocks((previous) => previous.map((row, i) => (
            i === index ? { ...row, data: { ...row.data, items } } : row
        )))
        setFocusRequest({ kind: 'point', key: block.key, pointIndex })
    }

    const removePoint = (index, pointIndex) => {
        setBlocks((previous) => previous.map((block, i) => {
            if (i !== index) return block

            const items = Array.isArray(block.data.items) ? [...block.data.items] : []

            // A `points` block with zero items fails validation server-side;
            // keep the last row and clear it so the user sees why.
            if (items.length <= 1) {
                return {
                    ...block,
                    data: {
                        ...block.data,
                        items: items.map((item) => ({ ...item, icon: '', lead: '', text: '' })),
                    },
                }
            }

            items.splice(pointIndex, 1)
            return { ...block, data: { ...block.data, items } }
        }))
    }

    const patchPoint = (index, pointIndex, field, value) => {
        setBlocks((previous) => previous.map((block, i) => {
            if (i !== index) return block

            const items = (Array.isArray(block.data.items) ? block.data.items : []).map((item, j) => (
                j === pointIndex ? { ...item, [field]: value } : item
            ))

            return { ...block, data: { ...block.data, items } }
        }))
    }

    const startDrag = (event, key) => {
        if (!event.target.closest('[data-lesson-drag-handle]')) {
            event.preventDefault()
            return
        }

        setDragKey(key)

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move'
            // Firefox refuses to start a drag without payload.
            event.dataTransfer.setData('text/plain', 'block')
        }
    }

    const dragOver = (event) => {
        if (!dragKey || !listRef.current) return

        event.preventDefault()

        const from = blocks.findIndex((block) => block.key === dragKey)
        if (from < 0) return

        const rows = Array.from(listRef.current.querySelectorAll(':scope > [data-lesson-block]'))
        let target = blocks.length

        rows.forEach((row, i) => {
            if (i === from || target !== blocks.length) return
            const box = row.getBoundingClientRect()
            if (event.clientY < box.top + box.height / 2) target = i
        })

        if (target > from) target -= 1
        if (target === from) return

        setBlocks((previous) => {
            const next = [...previous]
            const [moved] = next.splice(from, 1)
            next.splice(target, 0, moved)
            return next
        })
    }

    const endDrag = () => setDragKey(null)

    const jumpToPage = (page) => {
        setActivePage(page === '' || page === null || page === undefined ? null : Number(page))
    }

    useEffect(() => {
        if (!focusRequest) return

        const list = listRef.current
        if (!list) return

        const row = Array.from(list.children).find((element) => (
            element.dataset && element.dataset.lessonBlockKey === focusRequest.key
        ))
        if (!row) return

        if (focusRequest.kind === 'move') {
            row.querySelector(`[data-lesson-move="${focusRequest.direction}"]`)?.focus()
        }

        if (focusRequest.kind === 'point') {
            const point = row.querySelectorAll('[data-lesson-point]')[focusRequest.pointIndex]
            point?.querySelector('input')?.focus()
        }

        // One-shot: a later keystroke must not yank focus back here.
        setFocusRequest(null)
    }, [focusRequest, blocks])

    useEffect(() => {
        if (!scrollNext.current) return
        scrollNext.current = false

        const last = listRef.current?.lastElementChild
        // jsdom has no layout engine and no scrollIntoView; never let the
        // convenience scroll break the island there.
        if (last && typeof last.scrollIntoView === 'function') {
            try {
                last.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
            } catch {
                // A browser without smooth scrolling still saves fine.
            }
        }
    })

    const count = persianDigits(blocks.length)

    return createElement(
        Fragment,
        null,
        createElement(SourcePane, { pages: model.pages, activePage, onJump: jumpToPage }),
        createElement(
            'section',
            { className: 'panel student-panel lesson-blocks-pane' },
            createElement(
                'header',
                { className: 'student-panel-head' },
                createElement('h2', null, createElement('i', { className: 'fas fa-layer-group' }), ' ', STRINGS.blocksTitle),
                createElement('span', { className: 'count-badge' }, count),
            ),
            createElement(
                'form',
                { method: 'POST', action: model.saveUrl, id: 'lesson-blocks-form' },
                model.csrfToken
                    ? createElement('input', {
                        key: 'csrf',
                        type: 'hidden',
                        name: '_token',
                        defaultValue: model.csrfToken,
                    })
                    : null,
                createElement(
                    'div',
                    {
                        className: 'lesson-blocks',
                        'data-lesson-blocks': '',
                        ref: listRef,
                        onDragOver: dragOver,
                    },
                    blocks.length === 0
                        ? createElement('p', { className: 'lesson-empty-note', 'data-lesson-blocks-empty': '' }, STRINGS.blocksEmpty)
                        : blocks.map((block, index) => createElement(BlockRow, {
                            key: block.key,
                            block,
                            index,
                            types: model.types,
                            placements: model.placements,
                            figures: model.figures,
                            errors: model.errors,
                            dragging: dragKey === block.key,
                            onPatch: patchData,
                            onSourcePage: setSourcePage,
                            onMove: moveBlock,
                            onRemove: removeBlock,
                            onAddPoint: addPoint,
                            onRemovePoint: removePoint,
                            onPointChange: patchPoint,
                            onPageJump: jumpToPage,
                            onDragStart: startDrag,
                            onDragEnd: endDrag,
                        })),
                ),
                createElement(
                    'div',
                    { className: 'lesson-add-row' },
                    createElement(
                        'label',
                        { className: 'settings-field' },
                        createElement('span', { className: 'settings-field-label' }, STRINGS.addBlock),
                        createElement(
                            'select',
                            {
                                className: 'settings-input',
                                'data-lesson-add-type': '',
                                value: addType,
                                onChange: (event) => setAddType(event.target.value),
                            },
                            model.types.map((type) => createElement(
                                'option',
                                { key: type.value, value: type.value },
                                type.label,
                            )),
                        ),
                    ),
                    createElement(
                        'button',
                        {
                            type: 'button',
                            className: 'secondary-button',
                            'data-lesson-add-block': '',
                            onClick: addBlock,
                        },
                        createElement('i', { className: 'fas fa-plus' }),
                        ' ',
                        STRINGS.add,
                    ),
                ),
                createElement(
                    'div',
                    { className: 'teacher-form-actions' },
                    createElement(
                        'button',
                        { type: 'submit', className: 'primary-button' },
                        createElement('i', { className: 'fas fa-floppy-disk' }),
                        ' ',
                        STRINGS.save,
                    ),
                ),
            ),
        ),
        createElement(FigureShelf, { figures: model.figures, onPick: pickFigure }),
    )
}

/* ------------------------------------------------------- AI draft banner --- */

function defaultReload() {
    if (typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
        window.location.reload()
    }
}

/**
 * One poll tick for a `[data-lesson-draft-url]` banner (the poller used to
 * live in consultant-lesson-import.js and never ran on the editor page).
 * Returns false when the draft left the live states and the caller should
 * stop; true means "still live, poll again". A failed request is still true:
 * a failed poll is not a failed job.
 */
export async function pollDraftStatus(banner, options = {}) {
    const url = banner?.dataset?.lessonDraftUrl
    if (!url) return false

    const fetchImpl = options.fetchImpl || (typeof fetch === 'function' ? fetch : null)
    if (!fetchImpl) return true

    const reload = options.reload || defaultReload
    const live = Array.isArray(options.live) ? options.live : LIVE_DRAFT_STATES

    try {
        const response = await fetchImpl(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })

        if (!response || !response.ok) return true

        const data = await response.json()

        if (!live.includes(data?.status)) {
            reload()
            return false
        }

        return true
    } catch {
        return true
    }
}

/** Start the editor's draft pollers; returns the router teardown. */
export function startDraftPolls(region = document, options = {}) {
    const scope = region || document
    const banners = Array.from(scope.querySelectorAll('[data-lesson-draft-url]'))
    if (banners.length === 0) return () => undefined

    const intervalMs = options.intervalMs || POLL_MS
    const maxAttempts = options.maxAttempts || POLL_MAX_ATTEMPTS
    const stops = []

    banners.forEach((banner) => {
        let attempts = 0
        let timer = null

        const stop = () => {
            if (timer !== null) {
                clearInterval(timer)
                timer = null
            }
        }

        const run = () => {
            attempts += 1
            Promise.resolve(pollDraftStatus(banner, options))
                .then((alive) => {
                    if (!alive || attempts >= maxAttempts) stop()
                })
                .catch(() => {
                    if (attempts >= maxAttempts) stop()
                })
        }

        timer = setInterval(run, intervalMs)
        stops.push(stop)
        run()
    })

    return () => {
        while (stops.length) stops.pop()()
    }
}

/* ---------------------------------------------------------------- mounting */

const mountedHosts = new WeakMap()

function mountHost(container) {
    if (mountedHosts.has(container)) return null

    const payloadScript = container.querySelector(PAYLOAD_SELECTOR)
    if (!payloadScript) return null

    let payload = null
    try {
        payload = JSON.parse(payloadScript.textContent || '{}')
    } catch {
        // A malformed payload leaves the server-rendered fallback in place.
        return null
    }

    const fallback = container.innerHTML
    const island = mountIsland(container, LessonEditor, { payload })
    if (!island) return null

    const record = {
        active: true,
        stop() {
            if (!this.active) return
            this.active = false
            island.unmount()
            // Put the server rows back so a teardown that is not followed by
            // an innerHTML swap still leaves a valid, postable form behind.
            container.innerHTML = fallback
            mountedHosts.delete(container)
        },
    }

    mountedHosts.set(container, record)
    return record
}

/**
 * Page-router entry. Idempotent per host; returns the teardown that unmounts
 * the island, restores the server fallback and stops the draft pollers.
 */
export default function init(region = document) {
    const scope = region || document
    const stops = []

    scope.querySelectorAll(EDITOR_SELECTOR).forEach((container) => {
        const record = mountHost(container)
        if (record) stops.push(() => record.stop())
    })

    stops.push(startDraftPolls(scope))

    return () => {
        while (stops.length) {
            const stop = stops.pop()
            try {
                stop()
            } catch {
                // Teardown must never block a router navigation.
            }
        }
    }
}

// The router calls init() on every render of this page. This fallback only
// boots when app.js never made it (so a JS error cannot leave inert server
// rows behind); it must not double-mount on a normal hard load.
if (typeof window !== 'undefined' && !window.sapienstechRouter) {
    const boot = () => init(document)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true })
    } else {
        boot()
    }
}