<?php

declare(strict_types=1);

namespace App\Actions\Import;

use RuntimeException;

class ImportFailedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Import contains row errors; transaction rolled back.');
    }
}
