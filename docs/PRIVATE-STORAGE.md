# Private Storage

Private uploads and frozen publication copies are stored in the current site's
`atshde_blob_chunks` database table. They do not create a file in the plugin,
WordPress core, content root, public uploads, or an externally configured folder.
The normal PHP upload temporary file is read within its upload request only.

Each file is split into 64 KiB chunks. SQL uses bounded hexadecimal binary
transport, not charset-sensitive text storage. Reads require the exact chunk
sequence, length and SHA-256. Missing, extra or modified chunks fail closed.
Writes are serialized and verified; an unchanged retry reuses existing bytes.
Caught write failures remove their partial chunks. A process termination may
leave an orphan; it is not served without valid metadata and counts toward the
bounded capacity. Automatic orphan deletion is not implemented.

## Access and Limits

- Staged files require the owning workspace account, upload permission, the
  owning draft and an asset/hash-bound nonce for browser downloads.
- Frozen bytes require the current matching publication, an eligible published
  post, a retained media binding and an allowing membership/access decision.
  GET and HEAD enforce the same checks and private no-store response headers.
  See [Public Structure and Access](PUBLIC-STRUCTURE.md).
- Database storage has no directly addressable web file, independent of Apache
  or Nginx access rules. Database and backup security remain the host's
  responsibility. This is access-controlled storage, not encryption at rest.
- Existing type, 10 MiB per-file, article and per-user limits apply. Staged files
  have a 500 MiB site quota. Frozen copies have a separate 500 MiB quota; these
  are not one combined limit.
- Trash/deactivation retain data and do not reclaim quota. There is no automatic
  permanent deletion or retention policy.

## Earlier Experimental Storage

Version 0.7.6 removes legacy filesystem readers and automatic prefix migration
at the owner's request. Old files are not deleted, imported or served. The
old `SF_PRIVATE_MEDIA_DIR` and `SF_PUBLIC_DOCUMENT_ROOT` constants are not used.
See [Identifiers and Earlier Versions](PREFIX-MIGRATION.md) before updating an
experimental installation.

## WordPress Paths and Multisite

Existing Media Library attachments use WordPress uploads APIs and retain their
original access behavior. Reusing them does not make a public URL private.
Database tables use the current site's WordPress table prefix; one site's
private bytes are not searched in another site's table. Private storage does
not depend on `ABSPATH`, `WP_CONTENT_DIR`, `DOCUMENT_ROOT`, Unix file
permissions or a hard-coded uploads path.

Regression evidence and environment limitations belong in
[0.7.6 Verification](VERIFICATION-0.7.6.md).
