<?php

namespace App\UserDocument\Export;

final class UserDocumentExportRenderer
{
    public function text(UserDocumentExportData $document): string
    {
        return trim($this->renderNode($document->content, 'txt'))."\n";
    }

    public function markdown(UserDocumentExportData $document): string
    {
        return trim($this->renderNode($document->content, 'md'))."\n";
    }

    public function html(UserDocumentExportData $document): string
    {
        $title = htmlspecialchars($document->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = $this->renderNode($document->content, 'html');

        return "<!doctype html>\n<html lang=\"pt-BR\">\n<head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>{$title}</title><style>body{font-family:system-ui,sans-serif;line-height:1.65;max-width:760px;margin:2rem auto;padding:0 1rem}blockquote{border-left:3px solid #64748b;padding-left:1rem;color:#475569}li{margin:.25rem 0}</style></head>\n<body>{$body}</body>\n</html>\n";
    }

    /** @param array<string,mixed> $node */
    private function renderNode(array $node, string $format, int $depth = 0): string
    {
        $type = $node['type'] ?? '';
        $children = '';
        foreach (($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $children .= $this->renderNode($child, $format, $depth + 1);
            }
        }
        if ($type === 'text') {
            return $this->mark((string) ($node['text'] ?? ''), $node['marks'] ?? [], $format);
        }
        if ($type === 'hardBreak') {
            return $format === 'html' ? '<br>' : ($format === 'md' ? "  \n" : "\n");
        }

        return match ($type) {
            'doc' => $children,
            'paragraph' => $format === 'html' ? "<p>{$children}</p>\n" : "{$children}\n\n",
            'heading' => $format === 'html'
                ? '<h'.(int) ($node['attrs']['level'] ?? 2).">{$children}</h".(int) ($node['attrs']['level'] ?? 2).">\n"
                : ($format === 'md' ? str_repeat('#', (int) ($node['attrs']['level'] ?? 2))." {$children}\n\n" : "{$children}\n\n"),
            'blockquote' => $format === 'html' ? "<blockquote>{$children}</blockquote>\n" : $this->prefixLines($children, '> ', $format),
            'bulletList' => $this->list($children, false, $format),
            'orderedList' => $this->list($children, true, $format),
            'listItem' => $format === 'html' ? "<li>{$children}</li>\n" : $children,
            default => '',
        };
    }

    private function list(string $children, bool $ordered, string $format): string
    {
        if ($format === 'html') {
            return '<'.($ordered ? 'ol' : 'ul').">{$children}</".($ordered ? 'ol' : 'ul').">\n";
        }
        $lines = preg_split('/\R/', trim($children)) ?: [];
        $result = '';
        $index = 1;
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $result .= ($ordered ? $index++.'. ' : '- ').$line."\n";
        }

        return $result."\n";
    }

    private function prefixLines(string $value, string $prefix, string $format): string
    {
        if ($format === 'html') {
            return $value;
        }

        return implode("\n", array_map(fn ($line) => $prefix.$line, preg_split('/\R/', trim($value)) ?: []))."\n\n";
    }

    /** @param array<int, array<string, mixed>> $marks */
    private function mark(string $text, array $marks, string $format): string
    {
        if ($format === 'html') {
            $value = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif ($format === 'md') {
            $value = str_replace(['\\', '`', '*', '_', '#', '>', '[', ']'], ['\\\\', '\\`', '\\*', '\\_', '\\#', '\\>', '\\[', '\\]'], $text);
        } else {
            $value = $text;
        }
        $types = array_values(array_filter(array_map(fn (array $mark) => $mark['type'] ?? null, $marks)));
        if ($format === 'html') {
            if (in_array('bold', $types, true)) {
                $value = '<strong>'.$value.'</strong>';
            } if (in_array('italic', $types, true)) {
                $value = '<em>'.$value.'</em>';
            }
        } elseif ($format === 'md') {
            $bold = in_array('bold', $types, true);
            $italic = in_array('italic', $types, true);
            if ($bold && $italic) {
                $value = '***'.$value.'***';
            } elseif ($bold) {
                $value = '**'.$value.'**';
            } elseif ($italic) {
                $value = '*'.$value.'*';
            }
        }

        return $value;
    }
}
