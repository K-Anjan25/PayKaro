<?php

namespace App\Support;

/**
 * The legal documents, as data rather than three hand-cut pages.
 *
 * Deliberately *not* boilerplate: every claim here is checkable against this
 * repository — a rule in a FormRequest, a middleware, a config key — and the
 * honest gaps (no CSP, no encryption at rest, demo data on by default) are
 * stated as plainly as the strengths. A hosted product's terms pasted onto a
 * self-hosted tool would be the one thing worse than no legal page: a wrong one.
 *
 * Rendered by `<x-legal-layout>` through `{{ }}`, so a `<` or `&` in the prose is
 * literal text, never markup.
 */
final class Legal
{
    /**
     * @return array{title: string, lede: string, sections: list<array<string, mixed>>}
     */
    public static function terms(): array
    {
        return [
            'title' => 'Terms of use',
            'lede' => 'PayKaro is software you run, not a service you subscribe to. These terms describe what the tool does, what it does not do, and where responsibility sits.',
            'sections' => [
                [
                    'heading' => 'What PayKaro is',
                    'body' => "PayKaro is an invoice and receivables tracker for MSME suppliers: a pipeline of raised → accepted → financed → settled, an evidence checklist per invoice, a finance queue, and a claim packet you can hand to a forum.\n\nIt is not a payment gateway, it is not a TReDS exchange, and it is not an escrow. No rupee moves through this software. Nothing here initiates, settles, reverses or reconciles a bank transaction; PayKaro records the fact that you say money arrived, and computes what is outstanding from that.",
                ],
                [
                    'heading' => 'Accounts, roles and your own terms',
                    'body' => "Every user belongs to exactly one business and carries one of three roles: an owner, an accountant, or a viewer. Owners are the only role that may edit business identity — GSTIN, PAN, Udyam, bank account and IFSC — because those fields end up printed on documents.\n\nPayKaro is software, not a platform: if you operate a workspace for other people, these terms apply to the code, and *you* set the terms with your users. The role model gives you the levers to enforce them.",
                    'list' => [
                        'Owner: full read and write, including business identity and roles.',
                        'Accountant: full read and write on the book, not on identity.',
                        'Viewer: reads everything in their own workspace, changes nothing.',
                    ],
                ],
                [
                    'heading' => 'Your obligations',
                    'body' => "You are responsible for the accuracy of what you record, for keeping access to your deployment controlled, and for filing on time. In particular: do not use PayKaro to record invoices you do not intend to honour, do not enter another business's GSTIN or bank details to impersonate them, and do not use this software to breach the terms of any exchange or lender you submit paper to.",
                ],
                [
                    'heading' => 'Your data, and taking it with you',
                    'body' => "The rows in your database are yours. There is no lock-in mechanism, no export format only we can read, and no remote state: the whole book is one SQLite file at `database/paykaro.sqlite`, or whatever tables you point `DB_*` at, and every field in it was typed by you or derived from what you typed by a documented rule.\n\nBecause the derivation rules are in the open, you can recompute due dates, interest, ageing and readiness anywhere else and get the same answers.",
                ],
                [
                    'heading' => 'Limits of the tool — please read this one',
                    'body' => "PayKaro computes statutory interest, the MSMED due window, and the MSEFC filing deadline arithmetically, from the numbers in `config/paykaro.php`. Those figures are configuration, not counsel: a rate change, a notification, or a contract term that differs from the default will not announce itself here.\n\nTreat every date and rupee the software shows as a working figure to verify against the Act and your purchase order before it goes into a filing. To the maximum extent the law allows, the authors are not liable for financing decisions, claim outcomes, penalties or lost interest arising from use of this software. It is provided as is, without warranty of any kind.",
                    'note' => [
                        'No promise about systems you do not run.',
                        'TReDS submissions, lender approvals and buyer acceptance are all performed outside PayKaro. Its finance queue reflects what you record about them, not what they actually decided.',
                    ],
                ],
                [
                    'heading' => 'Licence',
                    'body' => 'The code is released under the MIT licence, as declared in `LICENSE` and `composer.json`. You may use it commercially, fork it, rebrand a private deployment, or sell hosting around it. Attribution is not required, and no licence is granted to the name PayKaro beyond what the copyright notice implies.',
                ],
                [
                    'heading' => 'Third-party sign-in and third-party text',
                    'body' => "Google sign-in is optional and inactive until you configure `GOOGLE_*` in `.env`. When you do, that authentication is Google's product under Google's terms and privacy policy, and the profile fields you approve — name, email, avatar, Google ID — are stored against your user row.\n\nMarketing copy, pricing tiers and news articles in this repository are illustrative content shipped for the demo. They are yours to rewrite before any public deployment.",
                ],
                [
                    'heading' => 'The demo workspace',
                    'body' => 'With `PAYKARO_DEMO=true` — the default — `migrate --seed` writes two fictional businesses with eighteen fictional invoices so the product is immediately legible. Those rows are not real counterparties and must never be mistaken for them: for a live workspace, set `PAYKARO_DEMO=false`, start from an empty database, and replace the sample identities outright.',
                ],
                [
                    'heading' => 'Changes, availability and ending this',
                    'body' => 'There is no unilateral amendment: changes to these terms arrive as commits to this file, and your deployment runs whatever you checked out. No uptime promise is made, because nobody is running your server. And since nothing holds your data, ending your use of PayKaro is a filesystem operation — stop serving the app, and delete the database file.',
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, lede: string, sections: list<array<string, mixed>>}
     */
    public static function privacy(): array
    {
        return [
            'title' => 'Privacy',
            'lede' => 'This page describes what the software puts in your database and what it never does. There is no company behind it reading over your shoulder — if you deploy PayKaro, you are the data fiduciary and the operator.',
            'sections' => [
                [
                    'heading' => 'What is stored, in your own database',
                    'body' => 'PayKaro needs to remember a receivable well enough to prove it. That means names and identifiers, and it is worth being specific rather than vague about them.',
                    'list' => [
                        'People and business: name, email address, role, bcrypt password hash, an optional avatar URL, and for business identity — GSTIN, PAN, Udyam number, bank name, account number, IFSC, plus a TReDS-registered flag.',
                        'Buyers: name, GSTIN, type (CPSU, PSU, private) and TReDS onboarding status.',
                        'Invoices: number, issue, approval, due and paid dates, base, GST and total amounts, status history, free-text notes.',
                        'Evidence, payments, financings and disputes: document checklist state, amounts, dates, method and reference, financier and discount rate, forum and filing deadlines.',
                        'Alerts and audit-adjacent rows: the message, its type, and whether it was dismissed.',
                    ],
                    'note' => [
                        'Bank account numbers are stored in plain columns.',
                        'The zero-config design favours one inspectable file over encrypted blobs; SQLite at rest is only as private as the filesystem under it. Treat `database/` and `.env` as secrets, and restrict them (see the filesystem line in Security).',
                    ],
                ],
                [
                    'heading' => 'What is never collected',
                    'body' => "There is no analytics package, no advertising or marketing cookie, no heatmap, no beacon, no crash reporter, no fingerprinting and no phone-home in this codebase. The only outbound request any page makes is for Google Fonts; the login screen's Google button goes to Google only when a user clicks it.",
                    'note' => [
                        'Logs are the exception to watch.',
                        '`storage/logs/laravel.log` follows your `LOG_LEVEL`. At `debug` it can contain request metadata and stack traces, which can contain email addresses. Keep production at `info` or above, and rotate or mount it on an encrypted volume.',
                    ],
                ],
                [
                    'heading' => 'Who else could see it',
                    'body' => "No one, unless you make them a sub-processor. The code embeds no third-party data flow: the operator's VPS, hosting provider, CDN or backup target are yours to know about and yours to paper. If you host PayKaro for others, your privacy notice must cover those recipients — this page cannot cover them for you, and does not pretend to.",
                ],
                [
                    'heading' => 'Cookies, sessions and logins',
                    'body' => "Two cookies, both first-party and both functional: the session cookie and, when a user ticks Keep me signed in for a week, the encrypted remember-me cookie. Both are `HttpOnly` with SameSite lax, scoped to the site root. The lifetime is `SESSION_LIFETIME` (10080 minutes by default, which is what the promise of a week means here).\n\nLogin attempts are rate limited to five per email-plus-IP before a cool-down, and a failed attempt says only that the credentials do not match. There is no user enumeration on this path: the response for an unknown address and a wrong password is the same sentence.",
                ],
                [
                    'heading' => "Your users' rights, and this Act",
                    'body' => "If you run PayKaro for real businesses in India, the DPDP Act 2023 obligations sit with you as the data fiduciary: notice at collection, a grievance channel, and a deletion route that works when a data principal asks. What the software contributes is the last one, honestly — a row's life is bounded by your `delete`, so erasure is a `DELETE` statement or an empty database file, with no shadow copy in someone else's warehouse.\n\nWhat it does not contribute is consent language, grievance-officer details, or a filing: add those to your own notice.",
                ],
                [
                    'heading' => 'Retention, export and deletion',
                    'body' => "Nothing is purged automatically, on purpose: an evidence ledger that quietly forgets is worse than no ledger. The app ships no export or self-service deletion screen, so today the mechanisms are at the database level — a full dump is `sqlite3 database/paykaro.sqlite '.dump'`, and per-workspace removal is deleting that `business_id`'s rows (invoices cascade to evidence, payments, financings and disputes through the foreign keys in the migrations).\n\nIf you need a governed deletion path for real users, build it as a command and tell them what it does.",
                ],
                [
                    'heading' => 'Google sign-in, specifically',
                    'body' => "When `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are set, the login page offers Google and Socialite handles the exchange. On success PayKaro stores the stable Google ID, the verified email, the display name and the avatar URL it was given; it asks for nothing else, and if Google returns no email address the sign-in is refused rather than guessed at.\n\nIf those keys are unset — the shipped default — the button does not render at all and the OAuth routes return 404. There is no hidden collection waiting for a misconfiguration.",
                ],
                [
                    'heading' => 'Changes to this page',
                    'body' => 'This page is part of the repository. If a future version stores something new, the diff to this file is where you will find out; a privacy notice that cannot be diffed is a marketing asset. As an operator, rewrite it to match your deployment before pointing real users at it.',
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, lede: string, sections: list<array<string, mixed>>}
     */
    public static function security(): array
    {
        return [
            'title' => 'Security',
            'lede' => "What is enforced in code, what is left to your configuration, and where the real gaps are — the last of these being the part most 'security' pages omit.",
            'sections' => [
                [
                    'heading' => 'Tenancy is a scope, not a WHERE clause',
                    'body' => "Every tenant-owned model (invoice, buyer, alert) carries a global Eloquent scope keyed to the business resolved from the authenticated session, and `business_id` is stamped on write from that same source — never from the request. A forged `business_id` in a POST body therefore cannot redirect a write to another workspace.\n\nTwo consequences worth knowing: a lookup of another business's row raises `TenantNotResolved` and is answered 403 fail-closed, while a cross-tenant read by ID answers 404 rather than 403 — a 403 would confirm the record exists, and that is an enumeration primitive.",
                    'note' => [
                        'One exception, deliberately.',
                        '`User` is not tenant-scoped: sign-in and Google linking must be able to find an account by email before any tenant is known. It is the only model that can read across businesses, and its reads are by credential, never by listing.',
                    ],
                ],
                [
                    'heading' => 'Authorization on every write',
                    'body' => "Policies guard each mutation, and the FormRequest's `authorize()` is the single place a write is permitted — there is no route that mutates without passing through one. Owners and accountants write; viewers are refused; business identity (GSTIN, PAN, Udyam, bank account, IFSC) and role changes are owner-only.\n\nUnauthenticated requests are redirected to sign-in, and authenticated users are redirected away from the auth pages, so there is no signed-out path onto tenant data.",
                ],
                [
                    'heading' => 'Input, output and injection',
                    'body' => "All input arrives through FormRequest rules — types, lengths, enum membership, exact date formats, and format validators for GSTIN, PAN, IFSC and Udyam. Reads are Eloquent with bound parameters; the two places raw SQL is unavoidable (a grouped pluck of buyer IDs, and an ordering of evidence rows) interpolate placeholders built from a fixed list, never user strings, and still bind their values.\n\nOutput escapes by default: Blade's `{{ }}` everywhere, with the only `{!! !!}` uses being SVG path strings that come from this repository's own view files — not from a database row, and not from a request.",
                ],
                [
                    'heading' => 'Passwords, sessions and CSRF',
                    'body' => "Hashing is bcrypt at `BCRYPT_ROUNDS` (12 by default) via Laravel's hasher; passwords are never logged, never returned in a response, and are upgraded on login when a carried-over hash needs rehashing. A Google-only account stores `password = null` rather than an empty string, so an empty-password comparison has nothing to succeed against, and the sign-in form tells such a user to use the Google button instead.\n\nCSRF tokens are validated by the `web` middleware group on every state-changing route, and session cookies are `HttpOnly` with SameSite lax. Google OAuth keeps its `state` parameter in the session, so a callback that did not originate from this browser's redirect is rejected.",
                ],
                [
                    'heading' => 'What this application does NOT give you',
                    'body' => 'Be precise here, because the honest list is short and useful — each gap below is a decision about scope, not an oversight, and several are yours to close in your own deployment.',
                    'list' => [
                        'No content security policy, no HSTS, no `X-Frame-Options` or `X-Content-Type-Options` header, no permission policy: the app ships no response-hardening middleware of its own.',
                        'No encryption at rest — SQLite is a plain file, and there are no encrypted columns.',
                        'No MFA or TOTP, no device or IP allowlist, no IP-pinned sessions.',
                        'No rate limiting anywhere but the login path.',
                        'No file upload whatsoever: the evidence checklist records that a document exists, it does not store the PDF. That attack surface is absent by scope, not by hardening.',
                        'No key management service: `APP_KEY` is whatever string sits in `.env`.',
                    ],
                    'note' => [
                        'If you deploy past localhost, fix the first paragraph yourself.',
                        'Termination, HSTS, CSP and security headers belong at your reverse proxy; `SESSION_SECURE_COOKIE=true` and `APP_DEBUG=false` belong in `.env`. Those four lines close more real-world exposure than any code in this repository could.',
                    ],
                ],
                [
                    'heading' => 'Configuration you are responsible for',
                    'body' => 'The shipped `.env.example` is tuned for a zero-config local demo, which is precisely the wrong posture for a public deployment. Before serving this to anyone else:',
                    'list' => [
                        'Set `APP_ENV=production` and `APP_DEBUG=false` — debug pages echo environment values and stack frames to whoever can reach the app.',
                        'Generate a fresh `php artisan key:generate`; never reuse the key of another deployment.',
                        'Set `SESSION_SECURE_COOKIE=true` (and `SESSION_DOMAIN`) behind HTTPS, so the session cookie cannot travel in the clear.',
                        "Set `PAYKARO_DEMO=false` and start from an empty database, so nobody inherits a second workspace's seeded book.",
                        "Tighten filesystem permissions on `database/`, `storage/` and `.env`; the app's only secret store is a file on disk.",
                        'Do not leave `data/paykaro.sqlite` or a stale backup web-served next to `public/`; anything readable under the document root is published.',
                    ],
                ],
                [
                    'heading' => 'Dependencies',
                    'body' => 'The runtime surface is deliberately small — `laravel/framework`, `laravel/socialite`, `laravel/tinker`, plus dev-only faker, Pint, Mockery and PHPUnit — and versions are pinned by a committed `composer.lock`, so a build resolves to exact packages rather than a moving range. That is where the trust boundary sits: a dependency CVE is a real risk here, so run `composer audit` in your pipeline and patch Laravel and Socialite as you would patch PHP itself.',
                ],
                [
                    'heading' => 'How to verify any of this',
                    'body' => "Do not take this page's word for it. The rules above are asserted in `tests/` — tenant isolation, the role matrix, the login refusal for password-less accounts, the rate-limit message, the invoice pipeline and its money — and CI runs `php artisan test` alongside `pint --test`. Reading the code is easier than trusting a legal page about it, which is the point of shipping the tests.",
                ],
                [
                    'heading' => 'Reporting a vulnerability',
                    'body' => "Please disclose privately rather than opening a public issue: GitHub's private vulnerability reporting on the repository, or direct contact with the maintainer. There is no bounty programme, and no safe-harbour promise that goes beyond ordinary good faith — please do not scan a deployment you do not own, and do not publish findings before giving the operator a chance to fix them.",
                ],
            ],
        ];
    }
}
