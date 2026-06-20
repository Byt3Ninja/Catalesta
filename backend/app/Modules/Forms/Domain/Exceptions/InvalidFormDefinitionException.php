<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain\Exceptions;

use RuntimeException;

/**
 * The form definition is not valid declarative data: an unknown field type, a
 * malformed field, or an embedded code/expression node (NFR-005).
 */
final class InvalidFormDefinitionException extends RuntimeException {}
