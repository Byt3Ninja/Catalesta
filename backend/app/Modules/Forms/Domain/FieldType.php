<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain;

/**
 * The 8 enumerated P1a application-form field types (FR-020). A form definition
 * may use only these — anything else fails validation (declarative-only, NFR-005).
 */
enum FieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';
    case Number = 'number';
    case Date = 'date';
    case FileUpload = 'file_upload';
    case Consent = 'consent';
}
