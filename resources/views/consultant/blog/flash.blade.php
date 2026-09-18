{{--
    Shared feedback strip for the blog pages: the settings-hub layout used
    to render these for its tabs; blog is a top-level section now, so it
    shows them itself.
--}}
@if(session('success'))
    <div class="settings-flash settings-flash--success" role="status">
        <i class="fas fa-check-circle" aria-hidden="true"></i> {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="settings-flash settings-flash--error" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i> {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="settings-flash settings-flash--error" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i> {{ $errors->first() }}
    </div>
@endif
