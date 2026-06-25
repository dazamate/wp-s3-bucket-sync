<?php

namespace Dazamate\S3ImageSync\Service;

use Aws\S3\S3Client;
use Dazamate\S3ImageSync\Dto\S3UploadJob;

class S3SyncService {
    // Upload every job for the attachment. A single failed object aborts the run
    // and surfaces the failing message(s); successfully uploaded objects are left
    // in place. Returns the original file's object key on success, null on failure.
    //
    // @param S3UploadJob[] $jobs
    public static function upload(S3Client $client, string $bucket, array $jobs, array &$errors): ?string {
        if ($bucket === '') {
            $errors[] = 'S3 sync error: No bucket configured';
            return null;
        }

        if (empty($jobs)) {
            $errors[] = 'S3 sync error: Nothing to upload';
            return null;
        }

        $primary_key = null;

        foreach ($jobs as $job) {
            if (!($job instanceof S3UploadJob)) {
                $errors[] = 'S3 sync error: Upload job must be an S3UploadJob instance';
                return null;
            }

            if (!is_readable($job->local_path)) {
                $errors[] = sprintf('S3 sync error: File not readable: %s', $job->local_path);
                return null;
            }

            try {
                $client->putObject([
                    'Bucket'      => $bucket,
                    'Key'         => $job->object_key,
                    'SourceFile'  => $job->local_path,
                    'ContentType' => $job->mime,
                ]);
            } catch (\Throwable $e) {
                $errors[] = sprintf('S3 upload error: %s', $e->getMessage());
                return null;
            }

            $primary_key ??= $job->object_key;
        }

        return $primary_key;
    }

    // Remove the given object keys from the bucket in a single batch request.
    //
    // @param string[] $object_keys
    public static function delete(S3Client $client, string $bucket, array $object_keys, array &$errors): bool {
        $keys = array_values(array_filter($object_keys, static fn($key): bool => is_string($key) && $key !== ''));

        if ($bucket === '' || empty($keys)) {
            return true;
        }

        try {
            $client->deleteObjects([
                'Bucket' => $bucket,
                'Delete' => [
                    'Objects' => array_map(static fn(string $key): array => ['Key' => $key], $keys),
                ],
            ]);
        } catch (\Throwable $e) {
            $errors[] = sprintf('S3 delete error: %s', $e->getMessage());
            return false;
        }

        return true;
    }
}
