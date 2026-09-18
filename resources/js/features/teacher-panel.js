/**
 * Teacher panel bundle: shared page behaviors for every /teacher screen.
 *
 * - Renders Jalali dates and Persian weekday names over the server's
 *   Gregorian fallback (<time.fa-date> / [data-fa-weekday]), with the same
 *   pinned-UTC Intl approach as student-dashboard.js — stored datetimes are
 *   wall-clock, so formatting them as UTC can never shift the day.
 * - Confirms destructive forms (data-confirm) before submitting.
 * - Drives the schedule dialog: opens for create, and when an edit button
 *   carries per-item data attributes, rewrites the form to the PUT update
 *   route and pre-fills every field.
 */

const STATUS_DIALOG_METHOD = 'PUT';

function initDates(region) {
    const displayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: '2-digit', day: '2-digit',
    });
    const weekdayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', weekday: 'long',
    });

    (region || document).querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    (region || document).querySelectorAll('[data-fa-weekday]').forEach((el) => {
        const date = new Date(el.getAttribute('data-fa-weekday'));
        if (!Number.isNaN(date.getTime())) el.textContent = weekdayFmt.format(date);
    });
}

function initConfirms(region) {
    (region || document).querySelectorAll('form[data-confirm]').forEach((form) => {
        if (form.dataset.confirmBound === '1') return;
        form.dataset.confirmBound = '1';
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
}

function setMethod(form, method) {
    let input = form.querySelector('input[name="_method"]');

    if (method) {
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_method';
            form.prepend(input);
        }
        input.value = method;
    } else if (input) {
        input.remove();
    }
}

function initScheduleDialog(region) {
    const scope = region || document;
    const dialog = scope.querySelector('[data-schedule-dialog]');
    if (!dialog || dialog.dataset.scheduleBound === '1') return;
    dialog.dataset.scheduleBound = '1';

    const form = dialog.querySelector('[data-schedule-form]');
    const title = dialog.querySelector('[data-schedule-title]');
    const fields = ['title', 'subject', 'grade', 'day_of_week', 'start_time', 'end_time', 'room', 'color', 'description'];
    const createRoute = form.action;
    const updateRouteTemplate = form.dataset.updateRoute;

    const fillField = (name, value) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) input.value = value ?? '';
    };

    const resetForCreate = () => {
        form.reset();
        form.action = createRoute;
        setMethod(form, null);
        if (title) title.textContent = 'زنگ جدید';
        fillField('color', '#06B6D4');
    };

    const fillForEdit = (button) => {
        const id = button.dataset.scheduleEdit;
        form.action = updateRouteTemplate.replace('__ID__', id);
        setMethod(form, STATUS_DIALOG_METHOD);
        if (title) title.textContent = 'ویرایش زنگ';
        fields.forEach((name) => fillField(name, button.dataset[`edit${name.charAt(0).toUpperCase()}${name.slice(1)}`]));
        const published = form.querySelector('[name="is_published"][value="1"]');
        if (published) published.checked = button.dataset.editPublished === '1';
    };

    // Create buttons may live anywhere on the page (the panel header).
    scope.querySelectorAll('[data-schedule-open]').forEach((button) => {
        button.addEventListener('click', () => {
            resetForCreate();
            dialog.showModal();
        });
    });

    scope.querySelectorAll('[data-schedule-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            fillForEdit(button);
            dialog.showModal();
        });
    });

    const cancel = dialog.querySelector('[data-schedule-cancel]');
    if (cancel) cancel.addEventListener('click', () => dialog.close());

    // Clicking the backdrop closes, matching the app's shared dialog UX.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
}

export default function init(region = document) {
    initDates(region);
    initConfirms(region);
    initScheduleDialog(region);
}
