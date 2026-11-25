<?php

namespace App\Modules\Media\Rules;

use App\Modules\Media\Services\MediaStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Type + size validation for library uploads.
 *
 * A flat `mimetypes:` rule is not enough: some libmagic builds report mp4
 * payloads as application/mp4 (browsers send video/mp4), so the sniffed and
 * client mime types are both considered. Size limits differ per kind —
 * images obey cms.media.max_upload_bytes, videos the larger
 * cms.media.max_video_bytes.
 */
class ValidMediaUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $kind = MediaStorage::detectKind($value);

        if ($kind === null) {
            $fail(__('media.error_unsupported_type'));

            return;
        }

        $bytes = (int) $value->getSize();
        $max = (int) ($kind === 'video'
            ? config('cms.media.max_video_bytes', 100 * 1024 * 1024)
            : config('cms.media.max_upload_bytes', 10 * 1024 * 1024));

        if ($max > 0 && $bytes > $max) {
            $fail(__('media.error_too_large', [
                'type' => $kind === 'video' ? __('media.kind_video') : __('media.kind_image'),
                'max' => max(1, (int) floor($max / 1048576)),
            ]));
        }
    }
}
