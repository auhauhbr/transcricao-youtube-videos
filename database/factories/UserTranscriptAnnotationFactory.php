<?php

namespace Database\Factories;

use App\Enums\UserTranscriptAnnotationType;
use App\Models\UserTranscriptAnnotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserTranscriptAnnotation> */
class UserTranscriptAnnotationFactory extends Factory
{
    protected $model = UserTranscriptAnnotation::class;

    public function definition(): array
    {
        return [
            'user_transcript_id' => null,
            'start_ms' => 0,
            'type' => UserTranscriptAnnotationType::Note,
            'text' => fake()->sentence(),
        ];
    }

    public function bookmark(): static
    {
        return $this->state(fn (): array => ['type' => UserTranscriptAnnotationType::Bookmark, 'text' => null]);
    }
}
