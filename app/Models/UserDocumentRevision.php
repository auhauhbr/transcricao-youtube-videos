<?php

namespace App\Models;

use App\Enums\UserDocumentRevisionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $user_document_id
 * @property int $revision_number
 * @property UserDocumentRevisionKind $kind
 * @property string $title
 * @property array<string, mixed> $content
 * @property int $document_lock_version
 */
#[Fillable(['user_document_id', 'revision_number', 'kind', 'title', 'content', 'document_lock_version'])]
class UserDocumentRevision extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (UserDocumentRevision $revision): void {
            $revision->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => UserDocumentRevisionKind::class,
            'content' => 'array',
            'revision_number' => 'integer',
            'document_lock_version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<UserDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(UserDocument::class, 'user_document_id');
    }
}
