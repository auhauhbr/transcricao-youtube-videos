<?php

namespace App\Support;

use Illuminate\Support\Str;

final class UserDocumentSnapshot
{
    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    public function matches(string $firstTitle, array $first, string $secondTitle, array $second): bool
    {
        return $firstTitle === $secondTitle
            && $this->normalize($first) === $this->normalize($second);
    }

    /** @param array<string, mixed> $content */
    public function preview(array $content, int $limit = 160): string
    {
        return Str::limit(Str::squish($this->extractText($content)), $limit);
    }

    /** @param array<string, mixed> $value */
    private function normalize(array $value): string
    {
        $normalized = $this->sortKeys($value);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
    }

    /** @param array<string, mixed> $node */
    private function extractText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return is_string($node['text'] ?? null) ? $node['text'] : '';
        }

        if (($node['type'] ?? null) === 'hardBreak') {
            return "\n";
        }

        $text = '';
        $content = $node['content'] ?? [];

        if (is_array($content)) {
            foreach ($content as $child) {
                if (is_array($child)) {
                    $text .= $this->extractText($child);
                }
            }
        }

        if (in_array($node['type'] ?? null, ['paragraph', 'heading', 'listItem', 'blockquote'], true)) {
            $text .= "\n";
        }

        return $text;
    }
}
