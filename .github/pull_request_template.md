<!--
  Short on purpose. BRAND_PLAN §5.3: `public/assets/app.css` is a single file that
  every screen shares, so a brand change is always a wide change — the one thing
  worth requiring is a look at the result.
-->

## What this changes

<!-- One or two sentences. If it changes copy or a figure a customer reads, say which page. -->

## If it touches layout, colour or type

- [ ] I have included a screenshot of the affected pages (light **and** dark, and
      the printed claim packet if the print stylesheet changed).

## Checks

- [ ] `node bridge/test.mjs`
- [ ] `pint --test` (`node /tmp/phpcli.mjs vendor/laravel/pint/builds/pint --test` in the sandbox)
- [ ] `node bridge/overflow-sim.mjs` — required if `app.css` or a view's grid changed

## If it adds a colour, a figure or a claim

- [ ] New colours are tokens, declared in `App\Brand\Palette` with their contrast
      floor, and `BrandPaletteTest` passes. No hex in a Blade template.
- [ ] A figure a customer reads comes from `App\Support\Proof` or from config —
      never typed into a view. `AuthenticityTest` lists the claims that may not come back.
