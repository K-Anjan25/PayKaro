<x-layouts.public title="Not available" active="" mode="page">
    <section class="sec">
        <div class="container" style="max-width:44rem;">
            <p class="eyebrow">{{ $status ?? 403 }}</p>
            <h1 class="h1" style="margin-top:.6rem;">This workspace isn't yours</h1>
            <p class="lead" style="margin-top:.8rem;">
                Every business's invoices, buyers and claims are kept apart, and a request from another account
                simply isn't found. If you believe you should have access, sign in with the workspace owner's
                account or ask them to add you.
            </p>
            <div class="row" style="margin-top:1.4rem;gap:.6rem;flex-wrap:wrap;">
                <a class="pbtn pbtn-blue" href="{{ route('dashboard') }}">My workspace</a>
                <a class="pbtn pbtn-ghost" href="{{ route('landing') }}">Back to home</a>
            </div>
        </div>
    </section>
</x-layouts.public>
