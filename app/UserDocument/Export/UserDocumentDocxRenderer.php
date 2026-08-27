<?php

namespace App\UserDocument\Export;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\ListItem;

final class UserDocumentDocxRenderer
{
    public function render(UserDocumentExportData $document): string
    {
        $word = new PhpWord;
        $word->getDocInfo()->setTitle($document->title);
        $section = $word->addSection(['marginTop' => 1_440, 'marginBottom' => 1_440, 'marginLeft' => 1_440, 'marginRight' => 1_440]);
        $section->addTitle($document->title, 1);
        $this->children($section, $document->content['content'] ?? []);
        $path = tempnam(sys_get_temp_dir(), 'transcrev-docx-');
        if ($path === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo DOCX temporário.');
        }
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new \RuntimeException('Não foi possível ler o arquivo DOCX.');
            }

            return $bytes;
        } finally {
            @unlink($path);
        }
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function children(mixed $parent, array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->node($parent, $node);
        }
    }

    /** @param array<string, mixed> $node */
    private function node(mixed $parent, array $node): void
    {
        $type = $node['type'] ?? '';
        if (in_array($type, ['paragraph', 'heading', 'blockquote', 'listItem'], true)) {
            $style = $type === 'heading' ? 'Heading'.((int) ($node['attrs']['level'] ?? 2) - 1) : null;
            $paragraph = $parent->addTextRun($style ? ['styleName' => $style] : ($type === 'blockquote' ? ['indentLeft' => 720] : []));
            foreach (($node['content'] ?? []) as $child) {
                if (is_array($child)) {
                    $this->inline($paragraph, $child);
                }
            }
            if ($type === 'listItem') {
                $paragraph->getParagraphStyle()->setIndentation(['left' => 720]);
            }

            return;
        }
        if (in_array($type, ['bulletList', 'orderedList'], true)) {
            foreach (($node['content'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $paragraph = $parent->addListItemRun($type === 'orderedList' ? ListItem::TYPE_NUMBER : ListItem::TYPE_BULLET_FILLED);
                foreach (($item['content'][0]['content'] ?? []) as $child) {
                    if (is_array($child)) {
                        $this->inline($paragraph, $child);
                    }
                }
            }

            return;
        }
        if ($type === 'doc') {
            $this->children($parent, $node['content'] ?? []);
        }
    }

    /** @param array<string, mixed> $node */
    private function inline(mixed $run, array $node): void
    {
        if (($node['type'] ?? '') === 'text') {
            $types = array_map(fn (array $mark): mixed => $mark['type'] ?? null, $node['marks'] ?? []);
            $run->addText((string) ($node['text'] ?? ''), ['bold' => in_array('bold', $types, true), 'italic' => in_array('italic', $types, true)]);
        } elseif (($node['type'] ?? '') === 'hardBreak') {
            $run->addTextBreak();
        }
    }
}
