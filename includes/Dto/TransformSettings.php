<?php

namespace Dazamate\S3ImageSync\Dto;

use Dazamate\S3ImageSync\Enum\TransformMethod;

class TransformSettings {
    public function __construct(
        public readonly TransformMethod $method,
        public readonly int $quality,
    ) {}

    public static function from_array(array $settings): self {
        $quality = (int) ($settings['transform_quality'] ?? 82);
        $quality = max(1, min(100, $quality));

        return new self(
            method: TransformMethod::from_value($settings['transform_method'] ?? null),
            quality: $quality,
        );
    }

    public function is_enabled(): bool {
        return $this->method->is_enabled();
    }
}
