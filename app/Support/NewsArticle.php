<?php

namespace App\Support;

/**
 * A "News & updates" entry.
 *
 * Content lives in code, not the database: these posts are product changelog and
 * policy notes that ship with a release, they are the same for every tenant, and
 * putting them in a table would mean a CMS for six paragraphs.
 */
final class NewsArticle
{
    /**
     * @param  list<array{type: string, text: string}>  $body
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $tag,
        public readonly string $date,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly string $image,
        public readonly string $alt,
        public readonly array $body,
    ) {}

    public function url(): string
    {
        return route('news.show', $this->slug);
    }

    /**
     * Body blocks as ['paragraph'|'heading', text] pairs for the article view.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function blocks(): array
    {
        return array_map(
            fn (array $block) => [$block['type'] === 'h2' ? 'heading' : 'paragraph', $block['text']],
            $this->body,
        );
    }
}
