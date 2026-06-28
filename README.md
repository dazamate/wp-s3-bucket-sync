# Daz S3 Image Sync

Intercepts the WordPress media gallery and mirrors images (originals plus every
generated size) to an Amazon S3 bucket. Synced attachments can be served from the
bucket URL or a configured CDN.

## Requirements

- PHP 8.5
- An S3 bucket and IAM credentials with `s3:PutObject` / `s3:DeleteObject`

## Configuration

Settings → **S3 Image Sync** in the WordPress admin. Enter the bucket, region,
access key, secret key, and optionally a custom endpoint (for S3-compatible
storage), a key prefix, and a CDN base URL.

## Bucket layout

Every file belonging to an attachment — the original, each generated size, and
any optimised variant (WebP/AVIF) — is stored together under a per-attachment
folder keyed by the attachment's post ID:

```
{prefix}/{attachment_id}/{filename}
```

For example, attachment `42` with the optional prefix `media`:

```
media/42/photo.jpg
media/42/photo-150x150.jpg
media/42/photo-300x200.jpg
media/42/photo.webp
```

This keeps each attachment's files grouped (rather than flattened into shared
`YYYY/MM` folders) and makes a single attachment easy to locate with
`aws s3 ls {prefix}/{attachment_id}/`. The optional key prefix is prepended as
shown; leave it blank to store folders at the bucket root.


## WP-CLI

The plugin registers commands under the `wp s3-image-sync` namespace.

### `wp s3-image-sync resync`

Re-uploads every image attachment in the media library (originals plus every
generated size) to S3. Useful for backfilling a bucket after installing the
plugin, changing buckets/prefixes, or recovering from failed syncs.

It walks the library in batches, syncing each attachment and reporting progress.
On completion it prints a summary; if any attachments fail it exits with a
warning listing how many synced versus failed.

**Options**

| Option | Default | Description |
|---|---|---|
| `--batch-size=<number>` | `100` | How many attachments to load per database query. |

**Examples**

```bash
# Re-sync the whole library using the default batch size
wp s3-image-sync resync

# Re-sync using a larger batch size (fewer, bigger queries)
wp s3-image-sync resync --batch-size=250
```

## Development

All tooling runs inside a PHP 8.5 container — the host PHP version is irrelevant.

```bash
bin/test.sh     # PHPUnit suite
bin/stan.sh     # PHPStan static analysis (level 5)
bin/build.sh    # Production, namespace-scoped, installable zip
```
