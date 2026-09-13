{{--
    Hidden mirror of the full filter set for one popover form.

    Every filter form (GET panel forms and the POST assignment forms) carries
    the complete current filter stack, so "apply" on any page never drops the
    filters set on another page and a bulk assignment re-derives exactly the
    displayed student set. Names this form owns as real controls ($visible)
    are not mirrored; app.js refreshes all mirrors from the live panel state
    right before submit, because the popover markup survives the router's
    partial swaps and would otherwise keep stale server-rendered values.
--}}
@php($mirror = array_diff(
    [
        'search', 'grade', 'gender', 'major', 'sort',
        'exam_status', 'exam_lesson', 'exam_type',
        'report_source', 'report_status',
        'schedule_day', 'schedule_done',
    ],
    $visible ?? [],
))
@foreach($mirror as $name)
    <input type="hidden" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}">
@endforeach
