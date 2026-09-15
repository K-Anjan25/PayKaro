<?php

namespace App\Support;

/**
 * The workspace's news feed: landing-page cards and the /news/{slug} articles
 * both read this, so an entry is written once.
 */
final class News
{
    /**
     * @return list<NewsArticle>
     */
    public static function all(): array
    {
        return [
            new NewsArticle(
                slug: 'evidence-checklist',
                tag: 'Product note',
                date: '02 Sep 2026',
                title: 'Every invoice, dated and dated twice.',
                excerpt: 'The new evidence checklist ties purchase order, delivery ack, GRN and GST copy to each invoice — and refuses to mark an invoice "ready" until the trail is complete.',
                image: '/assets/img/card-invoices.jpg',
                alt: 'Invoice paperwork on a desk',
                body: [
                    ['type' => 'p', 'text' => 'When a buyer delays a payment, almost every dispute comes down to one question: prove when. When was the invoice raised? When were the goods delivered? When did the buyer accept them? Those dates decide when the statutory interest clock starts — and whether a delayed-payment claim stands or collapses.'],
                    ['type' => 'p', 'text' => 'The heart of PayKaro has always been dates. Starting this week, the evidence behind those dates is a first-class part of every invoice.'],
                    ['type' => 'h2', 'text' => 'One checklist, four documents, zero arguments'],
                    ['type' => 'p', 'text' => 'Every invoice now carries an evidence checklist: purchase order, delivery acknowledgement, goods receipt note (GRN) and a GST-valid invoice copy — plus an optional contract. Each item is a dated fact you confirm as the paperwork arrives, not a folder you hope to find later.'],
                    ['type' => 'p', 'text' => 'The checklist feeds the invoice\'s readiness score directly. Evidence completeness carries 70% of the score; the buyer\'s TReDS onboarding status carries most of the rest. An invoice only reaches "ready to finance" when the trail is complete — so the finance queue can separate what can move today from what\'s held back by one missing GRN.'],
                    ['type' => 'h2', 'text' => 'Why it matters for claims'],
                    ['type' => 'p', 'text' => 'Under the MSMED framework, interest on delayed payments runs at three times the bank rate, calculated from the agreed date or deemed acceptance — but only if you can prove acceptance happened. A delivery acknowledgement with a date on it turns "they took the goods in July" into a number the forum accepts.'],
                    ['type' => 'p', 'text' => 'The claim packet builder assembles the checklist into a filing-ready summary: invoice, buyer, amounts, days overdue, interest due and the evidence trail — with the deadline for the MSEFC forum or arbitration computed from the due date.'],
                    ['type' => 'h2', 'text' => 'Try it in the demo'],
                    ['type' => 'p', 'text' => 'Sign in to the demo workspace, open any invoice and tick the evidence items. Watch the readiness score and the finance queue react immediately.'],
                ],
            ),
            new NewsArticle(
                slug: 'metrow-ceramics-financing',
                tag: 'Worked example',
                date: '27 Aug 2026',
                title: 'How MetRow Ceramics financed 60% of receivables in week one.',
                excerpt: 'When the buyer\'s TReDS onboarding cleared, four invoices were ready to discount the same afternoon. Here\'s the workflow that made it possible.',
                image: '/assets/img/card-team.jpg',
                alt: 'Team collaborating in a modern office',
                body: [
                    ['type' => 'p', 'text' => 'MetRow Ceramics supplies precision ceramic components to Delhi Metro\'s vendor chain and to private real-estate builders. Like most small manufacturers, they ran receivables from a spreadsheet, a WhatsApp folder and memory. In their first week on PayKaro, they converted their largest open invoice into cash.'],
                    ['type' => 'h2', 'text' => 'Day one: get the book in'],
                    ['type' => 'p', 'text' => 'Farhan raised three invoices — two against Delhi Metro Rail Corp, a PSU buyer already onboarded on TReDS, and one against a private builder. Each went in with its purchase order and delivery acknowledgement; the GRNs were confirmed the same evening. Total open receivables: a little over ₹9.2 lakh.'],
                    ['type' => 'h2', 'text' => 'The queue did the triage'],
                    ['type' => 'p', 'text' => 'The finance queue flagged the largest Delhi Metro invoice "ready to finance" — evidence complete, buyer onboarded. The builder invoice stayed out of the queue\'s ready pile: that buyer isn\'t on TReDS, so discounting it was never on the table this quarter. Instead, PayKaro kept accruing statutory interest on it as it aged past the 45-day window.'],
                    ['type' => 'h2', 'text' => 'Same week, cash in the bank'],
                    ['type' => 'p', 'text' => 'With the packet complete, their financier discounted the Delhi Metro invoice within days — no back-and-forth over documents, because every document was already attached, dated and checked. The disbursal was recorded against the invoice, moved it to "financed", and left the balance sheet honest.'],
                    ['type' => 'p', 'text' => 'The pattern is the point: complete evidence makes an invoice financeable the day it\'s raised. A TReDS-ready buyer makes it discountable. Everything else keeps earning interest.'],
                    ['type' => 'p', 'text' => 'MetRow Ceramics is one of the two demo businesses seeded in the PayKaro workspace — sign in with the MetRow login and this exact book is sitting in the data.'],
                ],
            ),
            new NewsArticle(
                slug: 'msme-rules-2026',
                tag: 'Explainer',
                date: '21 Aug 2026',
                title: 'The 2026 MSME rules: what changes for suppliers this quarter.',
                excerpt: 'TReDS mandates tighten for CPSE buyers and the delayed-payment forum gets teeth. We break down what it means for your invoices.',
                image: '/assets/img/impact-growth.jpg',
                alt: 'Analyst reviewing growth charts on a laptop',
                body: [
                    ['type' => 'p', 'text' => 'The 2026 MSME Development (Amendment) Bill has cleared committee, and it changes the arithmetic for every small supplier in India. Three shifts matter this quarter.'],
                    ['type' => 'h2', 'text' => '1. TReDS mandates tighten for CPSE buyers'],
                    ['type' => 'p', 'text' => 'Central public sector enterprises above the notification threshold are required to onboard and transact on TReDS. For suppliers that is good news with a catch: an invoice can only be discounted when both parties are onboarded. Knowing which of your buyers are ready is now a financing decision, not trivia.'],
                    ['type' => 'h2', 'text' => '2. The delayed-payment forum gets teeth'],
                    ['type' => 'p', 'text' => 'The MSEFC forum process is time-bound, and statutory interest on delayed payments — three times the prevailing bank rate, applied from the reference date — is enforced rather than theoretical. A supplier with dated evidence of delivery and acceptance walks in with leverage. One without it negotiates with hope.'],
                    ['type' => 'h2', 'text' => '3. The 45-day window is the default'],
                    ['type' => 'p', 'text' => 'Payments to MSMEs fall due within 45 days unless a written agreement says otherwise — and "otherwise" cannot be worse than the buyer\'s own standards for similar purchases. Ageing should be computed from the invoice itself, not reconstructed months later.'],
                    ['type' => 'h2', 'text' => 'What to do this quarter'],
                    ['type' => 'p', 'text' => 'One: audit your top buyers\' TReDS status before you plan financing around them. Two: close the evidence gaps on every open invoice — purchase order, delivery acknowledgement, GRN, GST-valid copy. Three: compute the interest you are owed from the invoice dates, so the number is ready the day you need it.'],
                    ['type' => 'p', 'text' => 'PayKaro bakes all three in: a 45-day due window on every invoice, daily interest accrual at three times the bank rate, a TReDS queue that separates ready from blocked, and a claim packet with the forum deadline computed for you.'],
                    ['type' => 'p', 'text' => 'This article is a plain-language summary written for the PayKaro demo workspace, not legal advice. The interest rate (3× a 6.5% bank rate) and windows shown in the demo follow the app\'s configuration.'],
                ],
            ),
        ];
    }

    public static function find(string $slug): ?NewsArticle
    {
        foreach (self::all() as $article) {
            if ($article->slug === $slug) {
                return $article;
            }
        }

        return null;
    }
}
