<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkMoveLibraryItemsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_public_ids.*' => ['required', 'ulid', 'distinct'],
            'folder_public_id' => ['nullable', 'ulid'],
        ];
    }
}
