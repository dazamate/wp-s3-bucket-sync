<?php

namespace Dazamate\S3ImageSync\Dto;

class S3Settings {
    public function __construct(
        public readonly string $bucket,
        public readonly string $region,
        public readonly string $access_key,
        public readonly string $secret_key,
        public readonly string $endpoint,
        public readonly string $prefix,
        public readonly string $cdn_url,
    ) {}

    public static function from_array(array $settings): self {
        return new self(
            bucket: (string) ($settings['bucket'] ?? ''),
            region: (string) ($settings['region'] ?? ''),
            access_key: (string) ($settings['access_key'] ?? ''),
            secret_key: (string) ($settings['secret_key'] ?? ''),
            endpoint: (string) ($settings['endpoint'] ?? ''),
            prefix: (string) ($settings['prefix'] ?? ''),
            cdn_url: (string) ($settings['cdn_url'] ?? ''),
        );
    }

    public function is_configured(): bool {
        return $this->bucket !== ''
            && $this->region !== ''
            && $this->access_key !== ''
            && $this->secret_key !== '';
    }
}
