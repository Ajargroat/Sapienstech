{{--
    The way into one lesson's controls, shared by the book page and the
    lessons table so the two lists cannot drift apart.

    It is a separate file because the link carries Persian text and both host
    views are edited in place; one definition means one label to keep honest.
--}}
<a class="ops-button ops-button--ghost ops-button--sm"
   href="{{ route('ops.lessons.lesson', $lesson) }}">مدیریت درس</a>
