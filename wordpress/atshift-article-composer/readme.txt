=== atshift Article Composer ===
Contributors: atshift
Tags: ai, mcp, publishing, structured-content
Requires at least: 6.9
Tested up to: 7.2
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and publish WordPress articles with your usual AI. Focus on your content, not the editor.

== Description ==

**Create the article, its layout and its publication plan with the AI you already use.**

atshift Article Composer connects a compatible AI client to WordPress so you can
turn a conversation, reference materials or a layout image into a structured
article. Your AI can assemble sections and responsive columns, style reusable
components, save a private draft, prepare a preview, and publish or schedule it
to the destination you choose. It does not include an AI model or a new chat app.

Start with one thing in Article Composer: download the guide for your AI.

Give it to the AI you already use, then follow its instructions to connect to
WordPress. Once a compatible AI client is connected and authorized, you can create
and publish new articles through that conversation, without opening Article Composer for
everyday authoring.

Work with your AI on the article, ask it to create the page design and column
layout, and tell it where the article should appear. Review the result, then
simply ask, "Publish it." Ask your AI to select existing categories and tags, too.
Where access rules have been verified, eligible article pages also provide
structure for the current reader and JSON-LD for anonymous public responses.

Focus on creating content. Let your AI handle the assembly and publication.

Initial setup, permission changes, reconnection and private theme-based previews
can still require WordPress sign-in. Site maintenance remains a separate responsibility.

English and Japanese interfaces are included. Additional languages can be added
using the bundled translation template or WordPress language packs.

[Plugin website](https://plugins.at-shift.net/article-composer/) |
[User guide](https://github.com/at-shift/atshift-article-composer/blob/main/docs/GETTING-STARTED.md) |
[Support](https://plugins.at-shift.net/en/support/?product=article-composer)

= Get started in six steps =

1. **STEP 1 - Install Article Composer.** In WordPress, open Plugins > Add New Plugin, find atshift Article Composer, then install and activate it. WordPress 6.9+ and PHP 8.2+ are required. Back up before updating an existing installation.
2. **STEP 2 - Give the guide to your AI.** Open atshift Article Composer, choose Download AI guide, and attach the file to your usual AI conversation or chat project. Say, "Read this guide and help me connect to my site."
3. **STEP 3 - Install the required MCP Adapter.** Before connecting, an administrator must install and activate the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) 0.7.1 or newer. In Plugins > Add New Plugin, search for MCP Adapter by WordPress.org. Update it if necessary when already installed. Ask your site administrator if you cannot do this yourself.
4. **STEP 4 - Connect and verify.** Follow the client-specific instructions, authorize your own WordPress account for the access you need, and ask the AI to verify a real connection. Keep connection keys in secure client settings, never in chat. A compatible MCP client and an externally reachable HTTPS site are needed for remote AI access.
5. **STEP 5 - Try a sample or create your article.** The guide includes an optional private sample. Or tell the AI who the article is for, provide your materials, and ask for the wording, page composition and column layout. Ask it to follow your theme's look or build a separate article layout. Save the draft and check the preview.
6. **STEP 6 - Choose where and when to publish.** Confirm the destination (post, page or eligible custom post type), existing categories/tags and timing. Grant a separate publishing connection when ready, then say "Publish it" or specify the date, time and time zone. Ask for the resulting public URL or reservation status. Saving or reviewing a draft alone never publishes it.

Initial setup is needed only for the chosen client and permissions, not for each
article. The guide remains available from Articles after the welcome dialog closes.
You can also import article JSON manually when an AI client cannot connect over MCP.

= Compose articles and page layouts =

Ask for page composition as well as wording, including responsive two-column
sections and multi-section layouts.

* Work from conversation, supporting documents and optional layout-reference images.
* Assemble headings, images, stacked sections, responsive columns and supported Q&A layouts.
* Reuse your theme's look or create an article design from shared components.
* Let an authorized AI set bounded colors, spacing, borders and typography inside Article Composer, without editing theme Additional CSS.
* Reuse available components before creating a missing structure. Saved articles retain their exact component revisions.

= Save, review and publish =

* Save and resume owner-only drafts with version checks that reject stale writes.
* Review a portable preview or an authenticated preview using your site's theme.
* Choose a normal post, page or eligible existing custom post type for each article.
* Select existing categories, tags and supported taxonomy terms with native WordPress permissions.
* Publish immediately or at a requested date/time through a separately authorized connection.
* Trash and restore drafts without deleting saved versions or files.

Saving a draft or approving Review does not publish it. A publishing connection
can publish without a second WordPress confirmation; grant it only to a trusted
client. Revocation cancels pending reservations; expiry alone does not cancel
an already accepted reservation. Scheduled processing depends on WP-Cron.

= Keep staff work and files separate =

Each person's drafts, private files and connections belong to their WordPress
account. Shared content-free structures can be reused without sharing private
article content. Selected images/PDFs can be stored privately and bound to a
saved version; attaching a file to chat alone does not upload it to WordPress.
The AI client needs file-byte and transfer capabilities. Supported existing
WordPress media can also be reused; existing public URLs are not made private.

= Provide reader-safe structure =

Eligible pages embed content-only structure JSON for the current viewer and
minimal JSON-LD for anonymous public responses. Supported whole-article access
rules from atshift Members, Members and Ultimate Member apply to structure and
managed media. Unverified integrations pause these additional outputs and show
the reason to administrators; normal article HTML is not rewritten.

= Requirements and boundaries =

WordPress 6.9+, PHP 8.2+ with DOM, mbstring, OpenSSL and Sodium, and MySQL/MariaDB
with InnoDB and named-lock support are required. Private files also require PHP
fileinfo and database capacity. Remote AI connections require an externally
reachable HTTPS site, MCP Adapter 0.7.1+ and a compatible client. No Node.js server
or AI provider API key is required in WordPress. AI service accounts, subscriptions
and client capabilities are separate; not every client supports every workflow.
Manual JSON import is available without MCP.

Test your theme/plugin combination on a backup copy before production use.
Published-post updates, staff ownership transfers and file-retention controls
are not implemented. Deactivation stops scheduled processing, managed-media
delivery and plugin styles. Designer/admin raw CSS can affect the whole page;
assign those roles only to trusted people. This plugin does not replace atshift
Fields or automatically migrate existing posts or experimental plugin data.

README.md contains detailed installation and deployment constraints;
AI-GUIDE.md defines the external-AI workflow. Both are shipped with the plugin.

== Installation ==

1. Back up an isolated WordPress test site.
   Use a fresh installation when coming from an older experimental plugin.
   Keep its backup; older experimental data is not automatically migrated.
   Both current channels use atshift-article-composer/atshift-article-composer.php.
   Do not activate an older experimental package alongside Article Composer.
2. Upload the plugin ZIP using Plugins > Add New > Upload Plugin and activate it.
3. Open atshift Article Composer and download the AI guide.
4. Give the downloaded guide to your AI. Before AI connection setup, an administrator must install and activate the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) 0.7.1 or newer. Open Plugins > Add New Plugin, search for MCP Adapter by WordPress.org, then choose Install Now > Activate. If already installed, update if needed and ensure it is active. Ask your site administrator if you cannot install or activate plugins. Manual JSON import does not require it.
5. Follow connection setup, then try the optional private sample or start your article.
6. Describe the article and its layout, review the saved preview, and explicitly request publication only after granting publishing access.

Private storage requires PHP fileinfo and database capacity. This plugin does not
automatically migrate older experimental data or read legacy private files.
Test a fresh installation first. Back up existing data and files; deploying over
an older installation requires a separate export/import plan. Do not mix old/new
plugin workers or downgrade only plugin files.
HTTPS is required except for explicitly local loopback development.
No AI provider credentials or external AI service are bundled with this plugin.

== Frequently Asked Questions ==

= Is this ready for production? =

Evaluate your site's theme, plugins and permissions on an isolated test site first. Published-post updates,
ownership transfers and file-retention controls are not yet implemented.
Back up before upgrading and review the documented permission boundaries.

= Does installing the plugin publish existing drafts? =

No. Existing drafts remain private. Immediate or scheduled publication requires
the owning user's publishing authorization through the supported connection.

= Can I delete a saved draft? =

Published and scheduled articles leave the Article Composer draft list and appear in their
selected destination. Scheduled entries are marked Scheduled (Article Composer) and remain
private until Article Composer's authorization checks pass at the requested time. Cancelled,
invalidated and failed reservations return to drafts. Saved versions and files
are retained. Ask your AI to find reservations with list-publications or use
Manage reservation in Article Composer from the destination list.

Ask your AI to move the draft to Trash, or select the trash icon in Article Composer's
draft list. Restore it from the Trash view using the undo icon or your AI.
Only the owner can trash or restore it. Cancel any publication reservation first.
Saved versions and files are retained; published posts and shared components
are not deleted. Restore does not publish or reschedule. There is no permanent
deletion or automatic purge, and Trash does not reclaim storage.

= Can my AI decorate components without editing my theme? =

Yes. An authorized authoring connection can reuse or create shared structures with
typed color, spacing, border, typography, column and link-button options. Article Composer
generates component-scoped CSS. Saved articles retain their component revisions.
Arbitrary CSS and JavaScript are not accepted by the AI structure-creation tool.

= Can I choose where each article is published? =

Yes. Choose normal posts, pages and eligible existing
public custom post types. If you have not chosen a destination for the article,
the AI guide instructs it to ask where to publish, show available destinations and
wait for your answer. An existing choice is validated and stated without asking
again. Choosing a destination alone does not publish. Native WordPress permissions
and separate Article Composer publication access still apply.

= Can the AI select categories and tags? =

Yes. The AI can select existing categories, tags and public
custom taxonomy terms attached to the selected destination. The AI confirms a
relevant selection with you; native assignment permissions are required. It does
not create terms. Without a selection, WordPress defaults apply. Changes to selected
terms or lost permissions stop pending reservations.

= Is machine-readable public content available? =

Eligible article pages embed structure JSON from content returned to the current
viewer; the former public REST endpoint is removed. Anonymous public responses
can also include minimal BlogPosting/WebPage JSON-LD. atshift Members, Members
and Ultimate Member whole-article access rules apply automatically to structure
and Composer-managed images/PDFs. Unverified plugins/themes and partial-content
rules pause additional delivery; the admin page identifies unverified components.
No private snapshots, component definitions or credentials are included.
See the Structure and Membership Access documentation in GitHub.

== External Services ==

Article Composer does not call an AI provider on its own. You connect your chosen external AI
client through the separately installed WordPress MCP Adapter. That authorized
client receives the shared component catalog and the owner's requested drafts,
previews and files. Sending material to that client is subject to the provider's
terms and privacy policy. Connection setup and publication access are opt-in.
OAuth uses administrator-registered clients; it does not fetch remote client metadata.

The WordPress.org distribution uses WordPress.org updates only. The separate GitHub
distribution checks api.github.com for official at-shift/atshift-article-composer releases and
downloads update metadata and ZIPs from github.com. It sends its plugin version
and the request IP address, not article contents, credentials or site URL. GitHub
terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
GitHub privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
The GitHub updater is excluded from WordPress.org packages.
After directory publication, a final GitHub transition update can install the
official directory ZIP unchanged and remove the external updater and Update URI.
Later updates then come from WordPress.org. Publication alone does not switch
installed copies; the transition update must be applied. Automatic-update
preferences are not changed. Bundled Japanese files are replaced by the directory
package, which also includes Japanese catalogs and the Japanese guide. Standard
WordPress.org language packs remain supported.

== Changelog ==

= 1.0.1 =
* Confirmed compatibility with WordPress 7.2 and PHP 8.6.

= 1.0.0 =
* Prepare the first directory release following WordPress.org approval.
* Complete the current Japanese interface catalog and include PHP/JavaScript translations and the Japanese guide in both distribution packages.
* Retain Japanese through the verified GitHub-to-WordPress.org update path; subsequent updates use WordPress.org.
* Organize setup, composition, publication and permission guidance for the directory listing.

= 0.7.6 =
* Address the November review: verify database setup, restrict AI request capabilities, protect review links and remove obsolete compatibility code.
* Separate runtime branding from directory assets and exclude unused dependency tooling.

= 0.7.5 =
* Rename the product to atshift Article Composer and align the WordPress.org package and text domain with atshift-article-composer.
* Preserve saved data, capabilities, MCP/OAuth connections and the GitHub update identity. Prevent conflicting distribution packages from running together.
* Retain the mixed-collation draft-listing fix and earlier review corrections.

= 0.7.4 =
* Fix draft listing when WordPress and DeFt post-type columns use different database collations. Saved drafts remain intact; no database collation or content changes are required.
* Add mixed-collation regression coverage for draft listing, reservations, cancellation, publication and failure recovery.
* GitHub-only release with the existing updater and Japanese files. The pending WordPress.org submission remains at 0.7.3.

= 0.7.3 =
* Add the DeFt icon and standardize the display name while preserving the plugin slug and API identity.
* Isolate code and dependencies under Atshift\Deft, migrate known legacy data to atshde_ identifiers, and retain compatible connections, roles, drafts and reservations.
* Store new private uploads and frozen media in verified database chunks; preserve read-only access to existing private files and support relocated WordPress paths.
* Harden nonce and OAuth input handling, review privacy headers, title output and AI REST isolation. Add upgrade, recovery, storage, multisite and HTTP regressions.
* Distribute an English-only WordPress.org package without the GitHub updater; keep translation support for WordPress language packs. The separate GitHub ZIP includes its updater and partial Japanese translations.

= 0.7.2 =
* Add the previously missing installation and activation steps for the official MCP Adapter to the AI guide. MCP Adapter 0.7.1 or newer must be installed and active for AI connections; manual JSON import does not require it.
* Link the official plugin in requirements and show prerequisite instructions before AI connection setup, including administrator assistance and Japanese guidance.

= 0.7.1 =
* Add owner-only draft trash and restoration through the workspace, REST and MCP, preserving saved versions and files.
* Show the draft owner's display name and role; place the DEFT menu below Settings.
* Remove published and newly reserved articles from the private draft list while retaining rendering data.
* Show private DEFT reservations in the chosen post, page or custom-type list and publish into the same entry only after revalidation.
* Return cancelled, invalidated or failed reservations to drafts; prevent native editing or premature publication of reserved entries.
* Refresh the AI guide, workspace help and Japanese catalogs. Download a fresh AI guide after upgrading.

= 0.7.0 =
* Choose a post, page or eligible existing custom post type separately for each article.
* Guide the AI to ask where to publish and confirm existing categories or tags with the timing.
* Preserve native destination/taxonomy permissions and recheck pinned choices before publication.
* Add public-content-only structure JSON and minimal JSON-LD; exclude drafts and private configuration.
* Refresh Japanese catalogs and user guides. Download a fresh AI guide after upgrading.

= 0.6.0 =
* Add reusable shared component appearance with bounded settings and component-scoped CSS.
* Carry styling through saved snapshots, previews and publication without modifying theme Additional CSS.
* Preserve field-key targeting, shared reuse, author isolation and existing article revisions.
* Include matching translation catalogs and separate WordPress.org and GitHub distribution packages.
* The GitHub-only package supports verified release updates through WordPress; existing 0.5.x installs need one manual ZIP upgrade.

= 0.5.2 =
* Add publication and scheduling steps to Articles and AI connections, including Japanese guidance.
* Guide the AI from a saved draft through separately authorized publication and result confirmation.
* Expand README and user-guide steps for key-based and OAuth connections without changing permissions or saved data.
* Download a fresh AI guide after upgrading; existing guide files do not update automatically.

= 0.5.1 =
* Add separate Codex desktop setup and clarify the observed ChatGPT web and Claude connector menus.
* Document private Claude header authentication and cloud-only connection troubleshooting, including Xserver REST API restrictions.
* Clarify theme-aligned or separate article layouts and record the scope of actual client checks.
* Update Japanese connection guidance and regression tests without changing saved data or authentication behavior.

= 0.5.0 =
* Guide the user from AI-guide download to AI-led connection, an optional private sample and their own article.
* Rename the product to atshift DEFT and the plugin slug to atshift-deft.
* Preserve saved data, role capabilities, API routes and OAuth identities.
* Prevent simultaneous loading with the legacy Structured Fields Prototype plugin.

= 0.1.0-alpha.14 =
* Add individual OAuth consent for pre-registered public clients, PKCE S256, scoped short-lived tokens and refresh rotation.
* Require PHP 8.2 and stop safely with an administrator warning on unsupported PHP versions.
* Preserve existing keys, owner isolation and previously authorized reservation expiry policy.

= 0.1.0-alpha.13 =
* Add separately authorized AI structure creation, own media discovery, exact-file transfers and portable private previews.
* Simplify connection setup and show authenticated connection activity without exporting credentials.
* Keep existing keys unchanged and publication separately authorized.

= 0.1.0-alpha.12 =
* Add per-user shared component and private media limits.
* Unify HTTPS and local-loopback transport checks.
* Prepare plugin metadata and compatibility checks for the current development package.
