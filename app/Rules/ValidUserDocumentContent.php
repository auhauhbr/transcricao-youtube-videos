<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidUserDocumentContent implements ValidationRule
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_DEPTH = 20;

    private const MAX_NODES = 50_000;

    private const MAX_TEXT_CHARACTERS = 5_000_000;

    private int $nodes = 0;

    private int $textCharacters = 0;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $encoded = json_encode($value);

        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            $fail('O documento não pode exceder 5 MiB.');

            return;
        }

        try {
            $this->validateNode($value, 0, 'doc');
        } catch (\UnexpectedValueException $exception) {
            $fail($exception->getMessage());
        }
    }

    /** @param array<string, mixed> $node */
    private function validateNode(array $node, int $depth, ?string $expectedType = null): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \UnexpectedValueException('O documento excede a profundidade permitida.');
        }

        if (++$this->nodes > self::MAX_NODES) {
            throw new \UnexpectedValueException('O documento contém elementos demais.');
        }

        $type = $node['type'] ?? null;

        if (! is_string($type) || ($expectedType !== null && $type !== $expectedType)) {
            throw new \UnexpectedValueException('A estrutura do documento é inválida.');
        }

        match ($type) {
            'doc' => $this->validateContainer($node, $depth, ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote']),
            'paragraph' => $this->validateInlineContainer($node, $depth),
            'heading' => $this->validateHeading($node, $depth),
            'bulletList' => $this->validateContainer($node, $depth, ['listItem'], requireContent: true),
            'orderedList' => $this->validateOrderedList($node, $depth),
            'listItem' => $this->validateContainer($node, $depth, ['paragraph', 'bulletList', 'orderedList'], requireContent: true),
            'blockquote' => $this->validateContainer($node, $depth, ['paragraph', 'heading', 'bulletList', 'orderedList'], requireContent: true),
            'text' => $this->validateText($node),
            'hardBreak' => $this->validateLeaf($node, ['type']),
            default => throw new \UnexpectedValueException("O tipo de elemento '{$type}' não é permitido."),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $childTypes
     */
    private function validateContainer(array $node, int $depth, array $childTypes, bool $requireContent = false): void
    {
        $this->assertKeys($node, ['type', 'content']);
        $this->validateChildren($node, $depth, $childTypes, $requireContent);
    }

    /** @param array<string, mixed> $node */
    private function validateInlineContainer(array $node, int $depth): void
    {
        $this->assertKeys($node, ['type', 'content']);
        $content = $node['content'] ?? [];

        if (! is_array($content) || ! array_is_list($content)) {
            throw new \UnexpectedValueException('O conteúdo de texto é inválido.');
        }

        foreach ($content as $child) {
            if (! is_array($child) || ! in_array($child['type'] ?? null, ['text', 'hardBreak'], true)) {
                throw new \UnexpectedValueException('Parágrafos e títulos aceitam somente texto e quebras de linha.');
            }

            $this->validateNode($child, $depth + 1);
        }
    }

    /** @param array<string, mixed> $node */
    private function validateHeading(array $node, int $depth): void
    {
        $this->assertKeys($node, ['type', 'attrs', 'content']);
        $attrs = $node['attrs'] ?? null;

        if (! is_array($attrs) || array_keys($attrs) !== ['level'] || ! in_array($attrs['level'], [2, 3], true)) {
            throw new \UnexpectedValueException('Somente títulos de nível 2 ou 3 são permitidos.');
        }

        $content = $node['content'] ?? [];

        if (! is_array($content) || ! array_is_list($content)) {
            throw new \UnexpectedValueException('O conteúdo do título é inválido.');
        }

        foreach ($content as $child) {
            if (! is_array($child) || ! in_array($child['type'] ?? null, ['text', 'hardBreak'], true)) {
                throw new \UnexpectedValueException('Títulos aceitam somente texto e quebras de linha.');
            }

            $this->validateNode($child, $depth + 1);
        }
    }

    /** @param array<string, mixed> $node */
    private function validateOrderedList(array $node, int $depth): void
    {
        $this->assertKeys($node, ['type', 'attrs', 'content']);
        $attrs = $node['attrs'] ?? null;

        if (
            ! is_array($attrs)
            || array_keys($attrs) !== ['start', 'type']
            || ! is_int($attrs['start'])
            || $attrs['start'] < 1
            || $attrs['start'] > 1_000_000
            || $attrs['type'] !== null
        ) {
            throw new \UnexpectedValueException('Os atributos da lista numerada são inválidos.');
        }

        $this->validateChildren($node, $depth, ['listItem'], true);
    }

    /** @param array<string, mixed> $node */
    private function validateText(array $node): void
    {
        $this->assertKeys($node, ['type', 'text', 'marks']);

        if (! isset($node['text']) || ! is_string($node['text'])) {
            throw new \UnexpectedValueException('Um elemento de texto é inválido.');
        }

        $this->textCharacters += mb_strlen($node['text']);

        if ($this->textCharacters > self::MAX_TEXT_CHARACTERS) {
            throw new \UnexpectedValueException('O documento contém texto demais.');
        }

        $marks = $node['marks'] ?? [];

        if (! is_array($marks) || ! array_is_list($marks)) {
            throw new \UnexpectedValueException('A formatação do texto é inválida.');
        }

        foreach ($marks as $mark) {
            if (! is_array($mark) || array_keys($mark) !== ['type'] || ! in_array($mark['type'], ['bold', 'italic'], true)) {
                throw new \UnexpectedValueException('O documento contém uma formatação não permitida.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $allowedKeys
     */
    private function validateLeaf(array $node, array $allowedKeys): void
    {
        $this->assertKeys($node, $allowedKeys);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $childTypes
     */
    private function validateChildren(array $node, int $depth, array $childTypes, bool $requireContent): void
    {
        $content = $node['content'] ?? [];

        if (! is_array($content) || ! array_is_list($content) || ($requireContent && $content === [])) {
            throw new \UnexpectedValueException('O conteúdo de um elemento do documento é inválido.');
        }

        foreach ($content as $child) {
            if (! is_array($child) || ! isset($child['type']) || ! in_array($child['type'], $childTypes, true)) {
                throw new \UnexpectedValueException('Um elemento está em uma posição não permitida no documento.');
            }

            $this->validateNode($child, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $allowedKeys
     */
    private function assertKeys(array $node, array $allowedKeys): void
    {
        if (array_diff(array_keys($node), $allowedKeys) !== []) {
            throw new \UnexpectedValueException('O documento contém atributos inesperados.');
        }
    }
}
