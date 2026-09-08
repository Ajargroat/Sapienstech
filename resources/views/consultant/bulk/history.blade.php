@extends('consultant.bulk.layout')

@section('bulk-content')
<div class="settings-cards">
    <section class="settings-card">
        <h3 class="settings-card-title">تاریخچهٔ اقدامات گروهی</h3>

        @if($actions->count())
            <div class="bulk-history-list">
                @foreach($actions as $action)
                    @php($s = $action->summary)
                    <div class="bulk-history-row {{ $action->isReverted() ? 'is-reverted' : '' }}">
                        <div class="bulk-history-icon bulk-history-icon--{{ $action->kind }}">
                            <i class="fas {{ $action->kind === 'exam' ? 'fa-tasks' : 'fa-calendar-alt' }}" aria-hidden="true"></i>
                        </div>
                        <div class="bulk-history-main">
                            <span class="bulk-history-title">
                                @if($action->kind === 'exam')
                                    آزمون «{{ $s['test_title'] ?? '—' }}»
                                @else
                                    بلوک برنامه «{{ $s['title'] ?? '—' }}»
                                @endif
                            </span>
                            <span class="bulk-history-meta">
                                {{ persian_digits($action->affected_count) }} دانش‌آموز
                                @if(($s['skipped'] ?? 0) > 0)· {{ persian_digits($s['skipped']) }} رد شد@endif
                                · {{ $action->user?->name ?? '—' }}
                                · <time datetime="{{ $action->created_at->toAtomString() }}">{{ persian_digits($action->created_at->format('Y/m/d H:i')) }}</time>
                            </span>
                        </div>
                        <div class="bulk-history-actions">
                            @if($action->isReverted())
                                <span class="blog-status blog-status--draft">واگرد شده</span>
                            @elseif($action->affected_count > 0)
                                <form method="POST" action="{{ route('consultant.bulk.history.revert', $action) }}" data-router="off"
                                      onsubmit="return confirm('همهٔ موارد این دسته حذف شوند؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-action icon-action--danger" title="واگرد">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="blog-pager">{{ $actions->links() }}</div>
        @else
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <h3>هنوز اقدام گروهی انجام نشده</h3>
                <p>با «آزمون گروهی» یا «برنامه گروهی» اولین دسته را بسازید.</p>
            </div>
        @endif
    </section>
</div>
@endsection
