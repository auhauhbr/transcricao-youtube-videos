<?php

namespace App\Support\YouTube;

use InvalidArgumentException;

final class InvalidYouTubeUrlException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('The value must be a valid YouTube video URL.');
    }
}
