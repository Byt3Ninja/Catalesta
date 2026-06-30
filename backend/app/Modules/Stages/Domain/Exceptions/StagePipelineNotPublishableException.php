<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Exceptions;

use RuntimeException;

/** Raised when a program's stage graph cannot be snapshotted (a stage lacks a published version, or no stages). →422. */
final class StagePipelineNotPublishableException extends RuntimeException {}
