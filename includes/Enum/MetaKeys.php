<?php

namespace Dazamate\S3ImageSync\Enum;

enum MetaKeys: string {
    case S3_OBJECT_KEY_META_KEY = 's3_object_key';
    case S3_URL_META_KEY = 's3_url';
    case S3_SYNC_ERROR_META_KEY = 's3_sync_error';
    case S3_TRANSFORM_VARIANTS_META_KEY = 's3_transform_variants';
}
