<?php

namespace Dazamate\S3ImageSync\Dto;

class S3UploadJob {
    public function __construct(
        public readonly string $local_path,
        public readonly string $object_key,
        public readonly string $mime,
    ) {}
}
