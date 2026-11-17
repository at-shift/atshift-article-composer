# Japanese Translation Review for 1.0.0

The public 1.0.0 packages add bundled Japanese to the approved English-only
WordPress.org submission baseline. The reviewed submission and historical ZIPs
are not overwritten.

## Coverage

- 647 current gettext entries, all translated and non-fuzzy.
- Japanese uses one plural form; printf placeholders retain their positions/types.
- One PHP MO and four path-hashed JavaScript catalogs cover Articles, Components,
  AI connections and client guidance.
- Both packages include identical catalogs and the Japanese user guide.
- AI instructions, field identifiers, API schemas, stored article content and
  sample component definitions retain their original language and identity.

Translations were prepared in context by the coding assistant, then reviewed
against the source text, UI names, permission boundaries and placeholders.
No Google Translate or runtime translation service was used. This is not a
claim of independent professional linguistic certification.

## Review Focus

Use consistent terms for components, drafts, private files, shared structures and
publication. In particular, preserve the distinction between saving, Review
approval, granting publication access, actual publication and scheduling. Keep
the difference between credential expiry and revocation explicit. Connection
instructions must never suggest pasting keys into chat or bypassing permissions.

The gettext tests compare every PO entry with the current POT and check
placeholders, plurals, completeness and fuzzy status. Compilation uses WP-CLI;
`msgfmt --check --check-format` supplies an independent format check. Tests load
Japanese through WordPress and exercise per-script catalogs. Browser checks cover
desktop/mobile pages and a real localized draft-save response. Handoff checks
must also verify translations after a fresh request, not only ZIP membership.
