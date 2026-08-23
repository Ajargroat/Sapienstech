<aside id="sidebar" class="consultant-sidebar">
    <div class="sidebar-header">
        <a href="{{ route('consultant.dashboard') }}" class="brand">
            <span class="brand-mark"></span>
            <span>
                <strong>{{ $tenant['name'] }}</strong>
                <small>{{ $tenant['role_label'] }}</small>
            </span>
        </a>
        <button id="close-sidebar-btn" class="icon-button mobile-only" type="button">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-group">
            @foreach($sidebar as $item)
                @if(config("consultant.features.{$item['key']}", false))
                    <a 
                        href="{{ route($item['route']) }}" 
                        class="sidebar-link {{ request()->routeIs($item['route']) ? 'active' : '' }}"
                    >
                        <span class="sidebar-icon">
                            <i class="fas {{ $item['icon'] }}"></i>
                        </span>
                        <span>{{ $labels[$item['label_key']] ?? $item['label_key'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    <div class="sidebar-bottom">
        <div class="user-card">
            <span class="user-avatar">
                <i class="fas fa-user"></i>
            </span>
            <span>
                <strong>{{ session('username', 'مدیر سیستم') }}</strong>
                <small>Administrator</small>
            </span>
        </div>
        
        <a href="#" class="sidebar-link logout-link">
            <span class="sidebar-icon">
                <i class="fas fa-sign-out-alt"></i>
            </span>
            <span>{{ $labels['logout'] }}</span>
        </a>
    </div>
</aside>