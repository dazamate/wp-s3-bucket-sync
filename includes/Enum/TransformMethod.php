<?php

namespace Dazamate\S3ImageSync\Enum;

// The image optimisation strategy applied to generated variants before they are
// written to the uploads directory and synced to S3. NONE leaves WordPress's
// own files untouched; the others produce optimised copies via the configured
// image library.
enum TransformMethod: string {
    case NONE = 'none';
    case JPEG = 'jpeg';
    case WEBP = 'webp';
    case AVIF = 'avif';

    public static function from_value(?string $value): self {
        return self::tryFrom((string) $value) ?? self::NONE;
    }

    // File extension used for the optimised copy.
    public function extension(): string {
        return match ($this) {
            self::WEBP => 'webp',
            self::AVIF => 'avif',
            default    => 'jpg',
        };
    }

    public function mime(): string {
        return match ($this) {
            self::WEBP => 'image/webp',
            self::AVIF => 'image/avif',
            default    => 'image/jpeg',
        };
    }

    public function label(): string {
        return match ($this) {
            self::NONE => 'None (passthrough)',
            self::JPEG => 'JPEG optimise',
            self::WEBP => 'WebP convert',
            self::AVIF => 'AVIF convert',
        };
    }

    public function is_enabled(): bool {
        return $this !== self::NONE;
    }
}
