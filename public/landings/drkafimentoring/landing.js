/**
 * Bespoke landing behaviour — drkafimentoring.
 *
 * The membership request form is no longer a page section: each plan's button
 * opens the shared join partial in a <dialog>, preselecting that plan. This
 * file owns the dialog's open/close plumbing only — it never touches the form
 * payload, the price or the tenant, which stay the shared partial's contract.
 *
 * The page ships data-animations="off" as a tenant preference; that does not
 * gate this behaviour (the dialog must work in every motion mode), and with JS
 * disabled the plans still render, only their buttons do nothing.
 */
(function () {
    'use strict';

    var dialog = document.getElementById('dl-join-dialog');

    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var scrollLock = null;

    var lockScroll = function () {
        if (scrollLock !== null) {
            return;
        }
        scrollLock = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    };

    var releaseScroll = function () {
        if (scrollLock === null) {
            return;
        }
        document.body.style.overflow = scrollLock;
        scrollLock = null;
    };

    var selectPlan = function (slug) {
        if (!slug) {
            return;
        }

        var radios = dialog.querySelectorAll('input[name="plan_slug"]');

        for (var i = 0; i < radios.length; i += 1) {
            radios[i].checked = radios[i].value === slug;
        }
    };

    var openDialog = function (slug) {
        selectPlan(slug);
        lockScroll();
        dialog.showModal();

        var first = dialog.querySelector('input[name="name"]') || dialog.querySelector('input');

        if (first) {
            window.setTimeout(function () {
                first.focus({ preventScroll: true });
            }, 0);
        }
    };

    var closeDialog = function () {
        if (dialog.open) {
            dialog.close();
        }
    };

    var triggers = document.querySelectorAll('[data-dl-join]');

    for (var i = 0; i < triggers.length; i += 1) {
        triggers[i].addEventListener('click', function (event) {
            event.preventDefault();
            openDialog(this.getAttribute('data-plan') || '');
        });
    }

    var closers = dialog.querySelectorAll('[data-dl-join-close]');

    for (var j = 0; j < closers.length; j += 1) {
        closers[j].addEventListener('click', closeDialog);
    }

    // A click on the backdrop reports the dialog itself as the target, so a
    // hit inside the box's own padding would close it too. Compare the point
    // against the box rect to close only on the area outside it.
    dialog.addEventListener('click', function (event) {
        if (event.target !== dialog) {
            return;
        }

        var rect = dialog.getBoundingClientRect();
        var outside = event.clientX < rect.left || event.clientX > rect.right
            || event.clientY < rect.top || event.clientY > rect.bottom;

        if (outside) {
            closeDialog();
        }
    });

    dialog.addEventListener('close', releaseScroll);
})();
