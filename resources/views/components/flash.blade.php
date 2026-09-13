{{--
    One place for both kinds of feedback the workspace gives: a saved-state
    notice and field errors. The legacy app reported failures as `?err=invalid`
    on the next page load, which lost what was typed and said nothing specific.
--}}
@if (session('status'))
    <div class="pkg-callout pkg-callout--sage" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="pkg-callout pkg-callout--coral" role="alert">
        <strong>{{ $errors->count() === 1 ? 'Not saved — check the field below.' : 'Not saved — check these fields.' }}</strong>
        <ul style="margin:.4rem 0 0;padding-left:1.1rem;">
            @foreach ($errors->unique() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
