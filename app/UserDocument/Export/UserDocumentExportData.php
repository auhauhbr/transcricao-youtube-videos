<?php

namespace App\UserDocument\Export;

use App\Models\UserDocument;

final readonly class UserDocumentExportData
{
    /** @param array<string, mixed> $content */
    public function __construct(
        public string $title,
        public array $content,
    ) {}

    public static function fromDocument(UserDocument $document): self
    {
        return new self($document->title, $document->content);
    }

    /** @param array{title: string, content: array<string, mixed>} $seed */
    public static function fromSeed(array $seed): self
    {
        return new self($seed['title'], $seed['content']);
    }
}
