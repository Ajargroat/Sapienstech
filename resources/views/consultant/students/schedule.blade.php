{{--
    Consultant weekly schedule editor for a single student.

    Behavioral/UX reference: consultant_schedule_editor.php +
    consultant_schedule_script.js. Ported to Blade + Laravel routes; the
    calendar/drag/modal logic itself lives in
    resources/js/features/consultant-schedule.js and is fed the URLs/CSRF
    token below via data-* attributes rather than hard-coded paths.

    NOTE: assumes `layouts.consultant` defines a `content` section and
    already loads the Vazirmatn font, Tailwind build, and RTL <html dir>
    the same way the other consultant pages do (dashboard, profile, etc).
--}}
@extends('layouts.consultant')

@section('title', 'برنامه هفتگی — ' . $student->name)

@section('content')
<div
    id="schedule-app"
    dir="rtl"
    class="flex flex-col h-full"
    data-student-id="{{ $student->id }}"
    data-student-name="{{ $student->name }}"
    data-csrf="{{ csrf_token() }}"
    data-url-dashboard="{{ route('consultant.dashboard') }}"
    data-url-items="{{ route('consultant.student.schedule.items.index', $student) }}"
    data-url-store="{{ route('consultant.student.schedule.items.store', $student) }}"
    data-url-update-template="{{ route('consultant.student.schedule.items.update', [$student, '__ITEM__']) }}"
    data-url-destroy-template="{{ route('consultant.student.schedule.items.destroy', [$student, '__ITEM__']) }}"
    data-url-comments-template="{{ route('consultant.student.schedule.items.comments', [$student, '__ITEM__']) }}"
>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        #schedule-app .calendar-scroll-area { height: calc(100vh - 260px); overflow-y: auto; overflow-x: hidden; position: relative; }
        #schedule-app .grid-bg-pattern {
            background-image:
                linear-gradient(to bottom, var(--c-border) 1px, transparent 1px),
                linear-gradient(to bottom, var(--c-surface) 30px, var(--c-surface-alt) 30px, var(--c-surface-alt) 31px, transparent 31px);
            background-size: 100% 60px;
        }
        #schedule-app .day-column { position: relative; height: 1440px; border-left: 1px solid var(--c-border); min-width: 140px; }
        #schedule-app .day-column:last-child { border-left: none; }
        #schedule-app .drag-ghost {
            position: absolute; left: 4px; right: 4px;
            background-color: color-mix(in srgb, var(--c-primary) 20%, transparent);
            border: 2px dashed var(--c-primary); border-radius: 6px;
            z-index: 40; pointer-events: none; display: flex; align-items: flex-start; padding: 4px;
            font-size: 0.75rem; color: var(--c-primary); font-weight: 600;
        }
        #schedule-app .event-card {
            position: absolute; left: 4px; right: 4px; border-radius: 6px; padding: 6px; font-size: 0.8rem;
            overflow: hidden; border-right-width: 4px; border-right-style: solid;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.1s ease, box-shadow 0.1s ease;
            cursor: pointer; z-index: 10;
        }
        #schedule-app .event-card:hover { z-index: 20; transform: scale(1.02); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        #schedule-app .hidden-mobile { display: none; }
        #schedule-app .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #schedule-app .hide-scrollbar::-webkit-scrollbar { display: none; }
        @media (min-width: 1024px) {
            #schedule-app .hidden-mobile { display: block; }
            #schedule-app .mobile-only { display: none !important; }
        }
    </style>

    <header class="bg-[var(--c-surface)] border-b border-[var(--c-border)] px-4 sm:px-6 py-3 sm:py-4 flex flex-col lg:flex-row justify-between items-center shadow-sm shrink-0 gap-3 sm:gap-4">
        <div class="flex justify-between w-full lg:w-auto items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="bg-primary/10 p-2 rounded-xl text-primary shrink-0">
                    <i data-lucide="calendar-days" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold text-[var(--c-text)] leading-tight">برنامه‌ریز هفتگی</h1>
                    <p class="text-xs sm:text-sm text-[var(--c-muted)]">مدیریت برنامه {{ $student->name }}</p>
                </div>
            </div>
            <div class="lg:hidden flex flex-col text-left text-xs sm:text-sm pl-2">
                <span class="font-bold text-[var(--c-text)]">{{ $student->name }}</span>
                <a href="{{ route('consultant.dashboard') }}" class="text-primary hover:underline">بازگشت &larr;</a>
            </div>
        </div>

        <div class="flex flex-wrap lg:flex-nowrap items-center justify-between lg:justify-end gap-2 sm:gap-3 w-full lg:w-auto">
            <div class="hidden lg:flex flex-col text-left text-sm ml-2 pl-4 border-l border-[var(--c-border)]">
                <span class="font-bold text-[var(--c-text)]">{{ $student->name }}</span>
                <a href="{{ route('consultant.dashboard') }}" class="text-xs text-primary hover:underline">بازگشت به داشبورد &larr;</a>
            </div>

            <div class="flex flex-1 sm:flex-none justify-center items-center bg-[var(--c-surface-alt)] rounded-lg p-1 border border-[var(--c-border)]">
                <button id="next-week-btn" type="button" class="p-1.5 hover:bg-[var(--c-surface)] rounded-md text-[var(--c-muted)] transition shadow-sm" title="هفته قبل">
                    <i data-lucide="chevron-right" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </button>
                <span id="week-date-display" class="px-2 sm:px-4 text-xs sm:text-sm font-medium whitespace-nowrap">در حال بارگذاری...</span>
                <button id="prev-week-btn" type="button" class="p-1.5 hover:bg-[var(--c-surface)] rounded-md text-[var(--c-muted)] transition shadow-sm" title="هفته بعد">
                    <i data-lucide="chevron-left" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </button>
            </div>

            <button id="add-event-button" type="button" class="bg-primary hover:bg-primary-hover text-black dark:text-white px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-medium transition flex items-center justify-center gap-2 shadow-sm shrink-0">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>افزودن</span>
            </button>
        </div>
    </header>

    <main class="flex-1 flex flex-col overflow-hidden px-2 sm:px-4 lg:px-6 py-3 sm:py-4">
        <div id="mobile-day-tabs" class="mobile-only flex overflow-x-auto gap-2 pb-2 mb-2 snap-x hide-scrollbar"></div>

        <div class="flex-1 bg-[var(--c-surface)] border border-[var(--c-border)] rounded-xl sm:rounded-2xl shadow-sm overflow-hidden flex flex-col relative">
            <div id="calendar-header" class="flex border-b border-[var(--c-border)] bg-[var(--c-surface-alt)] shrink-0">
                <div class="w-12 sm:w-16 lg:w-20 shrink-0 border-l border-[var(--c-border)] flex items-center justify-center bg-[var(--c-surface)] z-10 relative">
                    <i data-lucide="clock" class="w-4 h-4 text-[var(--c-muted)]"></i>
                </div>
                <div id="day-headers-container" class="flex flex-1"></div>
            </div>

            <div class="calendar-scroll-area bg-[var(--c-surface)]" id="calendar-scroll-area">
                <div class="flex relative">
                    <div class="w-12 sm:w-16 lg:w-20 shrink-0 border-l border-[var(--c-border)] relative bg-[var(--c-surface)] z-10">
                        <div id="time-axis" class="relative"></div>
                    </div>
                    <div id="grid-container" class="flex flex-1 grid-bg-pattern relative select-none cursor-crosshair"></div>
                </div>
            </div>
        </div>

        <div class="mt-2 text-center text-[10px] sm:text-xs text-[var(--c-muted)] flex items-center justify-center gap-1 sm:gap-2">
            <i data-lucide="mouse-pointer-click" class="w-3 h-3 sm:w-4 sm:h-4"></i>
            برای ایجاد برنامه جدید، روی فضای خالی تقویم کلیک کرده و درگ کنید.
        </div>
    </main>

    <div id="event-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-2 sm:p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-[var(--c-surface)] rounded-xl sm:rounded-2xl shadow-xl w-full max-w-lg transform scale-95 transition-transform duration-300 overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
            <div class="flex justify-between items-center p-4 sm:p-5 border-b border-[var(--c-border)] bg-[var(--c-surface-alt)]">
                <h3 class="text-base sm:text-lg font-bold text-[var(--c-text)]" id="modal-title">افزودن برنامه جدید</h3>
                <button type="button" onclick="ScheduleApp.closeModal()" class="text-[var(--c-muted)] hover:text-[var(--c-text)] hover:bg-[var(--c-surface-alt)] p-1.5 rounded-lg transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-4 sm:p-5 overflow-y-auto flex-1">
                <form id="event-form" class="space-y-4">
                    <input type="hidden" id="event_id" value="">

                    <div>
                        <label class="block text-sm font-medium text-[var(--c-muted)] mb-1">عنوان برنامه <span class="text-red-500">*</span></label>
                        <input type="text" id="title" required placeholder="مثال: مطالعه ریاضی دهم" class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 sm:px-4 sm:py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--c-muted)] mb-2">روز هفته</label>
                        <div class="flex flex-wrap gap-2" id="modal-day-pills"></div>
                        <input type="hidden" id="day_index" value="">
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--c-muted)] mb-1">ساعت شروع</label>
                            <input type="time" id="start_time" required class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 sm:px-4 sm:py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-left" dir="ltr">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--c-muted)] mb-1">ساعت پایان</label>
                            <input type="time" id="end_time" required class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 sm:px-4 sm:py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-left" dir="ltr">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--c-muted)] mb-2">رنگ دسته‌بندی</label>
                        <div class="flex gap-2 sm:gap-3" id="color-picker">
                            <button type="button" data-color="blue" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-100 border-2 border-transparent ring-2 ring-offset-2 ring-transparent focus:ring-blue-500 transition shadow-sm"></button>
                            <button type="button" data-color="green" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-green-100 border-2 border-transparent ring-2 ring-offset-2 ring-transparent focus:ring-green-500 transition shadow-sm"></button>
                            <button type="button" data-color="yellow" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-yellow-100 border-2 border-transparent ring-2 ring-offset-2 ring-transparent focus:ring-yellow-500 transition shadow-sm"></button>
                            <button type="button" data-color="red" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-red-100 border-2 border-transparent ring-2 ring-offset-2 ring-transparent focus:ring-red-500 transition shadow-sm"></button>
                            <button type="button" data-color="purple" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-purple-100 border-2 border-transparent ring-2 ring-offset-2 ring-transparent focus:ring-purple-500 transition shadow-sm"></button>
                            <button type="button" data-color="pink" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-pink-100 border-2 border-transparent ring-2 ring-offset-2 ring-transparent focus:ring-pink-500 transition shadow-sm"></button>
                        </div>
                        <input type="hidden" id="color_theme" value="blue">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 pt-2 border-t border-[var(--c-border)]">
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">نام کتاب (اختیاری)</label>
                            <input type="text" id="book_name" class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs">
                        </div>
                        <div class="grid grid-cols-2 sm:col-span-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">تعداد صفحه</label>
                                <input type="number" id="page_count" min="0" class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">تعداد تست</label>
                                <input type="number" id="test_count" min="0" class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs text-left" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">لینک آزمون/محتوا (اختیاری)</label>
                        <input type="url" id="link_url" placeholder="https://..." class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs text-left" dir="ltr">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">توضیحات مشاور</label>
                        <textarea id="description" rows="2" placeholder="نکات تکمیلی برای دانش‌آموز..." class="w-full border border-[var(--c-border-strong)] bg-[var(--c-surface-alt)] text-[var(--c-text)] rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs resize-none"></textarea>
                    </div>

                    <div id="status-container" class="hidden mt-4 pt-4 border-t border-[var(--c-border)] flex justify-between items-center">
                        <span class="text-sm font-medium text-[var(--c-muted)]">وضعیت انجام:</span>
                        <span id="completion-status" class="text-sm font-bold"></span>
                    </div>

                    <div id="comments-section" class="hidden mt-3">
                        <label class="block text-xs font-medium text-[var(--c-muted)] mb-2">نظرات دانش‌آموز</label>
                        <div id="comments-list" class="space-y-2 max-h-40 overflow-y-auto bg-[var(--c-surface-alt)] rounded-lg p-3 border border-[var(--c-border)]"></div>
                    </div>
                </form>
            </div>

            <div class="p-4 sm:p-5 border-t border-[var(--c-border)] bg-[var(--c-surface-alt)] flex flex-wrap sm:flex-nowrap justify-between items-center gap-3 shrink-0">
                <div class="w-full sm:w-auto flex justify-start order-2 sm:order-1">
                    <button type="button" id="btn-delete" onclick="ScheduleApp.deleteEvent()" class="hidden text-[var(--c-danger)] hover:bg-[var(--c-surface)] px-4 py-2 rounded-lg text-sm font-medium transition w-full sm:w-auto">حذف برنامه</button>
                </div>
                <div class="flex gap-2 w-full sm:w-auto order-1 sm:order-2">
                    <button type="button" onclick="ScheduleApp.closeModal()" class="flex-1 sm:flex-none text-[var(--c-text)] bg-[var(--c-surface)] border border-[var(--c-border-strong)] hover:bg-[var(--c-surface-alt)] px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">انصراف</button>
                    <button type="button" onclick="ScheduleApp.saveEvent()" class="flex-1 sm:flex-none bg-primary hover:bg-primary-hover text-black dark:text-white px-6 py-2 rounded-lg text-sm font-medium transition shadow-sm">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-[var(--c-surface-elevated)] text-[var(--c-text)] border border-[var(--c-border)] px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transition-all duration-300 transform translate-y-20 opacity-0 z-50">
        <i data-lucide="check-circle-2" class="w-5 h-5 text-green-400"></i>
        <span id="toast-msg" class="text-sm font-medium">عملیات با موفقیت انجام شد</span>
    </div>
</div>

@vite(['resources/js/features/consultant-schedule.js'])
@endsection
