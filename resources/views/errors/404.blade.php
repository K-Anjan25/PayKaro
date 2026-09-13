<x-layouts.public title="Page not found" active="" mode="page">
    <section class="sec">
        <div class="container" style="max-width:44rem;">
            <p class="eyebrow">404</p>
            <h1 class="h1" style="margin-top:.6rem;">Nothing here</h1>
            <p class="lead" style="margin-top:.8rem;">
                The page — or the invoice — you asked for doesn't exist in this workspace. Invoices from another
                account are reported the same way, so nothing leaks across tenants.
            </p>
            <div class="row" style="margin-top:1.4rem;gap:.6rem;flex-wrap:wrap;">
                <a class="pbtn pbtn-blue" href="{{ route('landing') }}">Back to home</a>
                <a class="pbtn pbtn-ghost" href="{{ route('invoices.index') }}">My invoices</a>
            </div>
        </div>
    </section>
</x-layouts.public>
