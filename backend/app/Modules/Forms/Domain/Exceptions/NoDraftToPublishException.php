<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain\Exceptions;

use RuntimeException;

/** Raised when a form has no editable draft version (nothing to save or publish). */
final class NoDraftToPublishException extends RuntimeException {}
