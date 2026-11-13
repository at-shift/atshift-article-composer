<?php
namespace Atshift\Deft;
defined( 'ABSPATH' ) || exit;

final class PublicationMedia {
    public static function freeze( $job, $row ) {
        global $wpdb;
        $lock = 'sf_pub_media_' . substr( hash( 'sha256', DB_NAME . ':' . Publications::table() ), 0, 45 );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,3)', $lock ) ) ) throw new \RuntimeException( __( 'Publication storage is busy.', 'atshift-article-composer' ) );
        try { return self::freeze_files( $job, $row ); }
        finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
    }

    private static function freeze_files( $job, $row ) {
        $refs = Media::references( json_decode( $row['payload'] ) );
        if ( ! $refs ) return array();
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( count( $refs ) > 50 ) throw new \RuntimeException( __( 'At most 50 media bindings can be published.', 'atshift-article-composer' ) );
        $files = $unique = array(); $size = 0;
        $capacity = Assets::SITE_BYTES;
        $resolved = Media::resolve_batch( $refs, $row['article_id'] );
        foreach ( $refs as $key => $usage ) {
            $meta = $resolved[$key]; $hash = $meta['sha256'];
            if ( isset( $unique[$hash] ) ) { $files[$key] = array_merge( $unique[$hash], array( 'filename' => $meta['filename'] ) ); continue; }
            $bytes = Media::bytes( $meta );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
            if ( ! is_string( $bytes ) || strlen( $bytes ) !== (int) $meta['bytes'] || ! hash_equals( $meta['sha256'], hash( 'sha256', $bytes ) ) ) throw new \RuntimeException( __( 'Publication media changed.', 'atshift-article-composer' ) );
            $size += strlen( $bytes );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
            if ( $size > Assets::ARTICLE_BYTES ) throw new \RuntimeException( __( 'Publication media exceeds 100 MiB.', 'atshift-article-composer' ) );
            PrivateBlobs::put( 'publication', $job['publication_id'] . ':' . $hash, $bytes, $meta, $capacity );
            $files[$key] = array( 'sha256' => $hash, 'mime' => $meta['mime'], 'filename' => $meta['filename'], 'bytes' => strlen( $bytes ) );
            $unique[$hash] = $files[$key];
        }
        return $files;
    }

    public static function html( $job, $row, $files ) {
        $dom = new \DOMDocument(); $old = libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $row['rendered'] . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors(); libxml_use_internal_errors( $old ); $found = array();
        // Component markup cannot introduce executable WordPress block comments.
        foreach ( ( new \DOMXPath( $dom ) )->query( '//comment()' ) as $comment ) $comment->parentNode->removeChild( $comment );
        foreach ( ( new \DOMXPath( $dom ) )->query( '//*[@data-sf-media]' ) as $element ) {
            $key = $element->getAttribute( 'data-sf-media' );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
            if ( ! isset( $files[$key] ) || isset( $found[$key] ) ) throw new \RuntimeException( __( 'Invalid publication media binding.', 'atshift-article-composer' ) );
            $image = str_starts_with( $files[$key]['mime'], 'image/' );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
            if ( $element->tagName !== ( $image ? 'img' : 'a' ) ) throw new \RuntimeException( __( 'Invalid publication element.', 'atshift-article-composer' ) );
            $element->setAttribute( $image ? 'src' : 'href', add_query_arg( array( 'sf_public_media' => $job['publication_id'], 'sf_media_key' => $key ), home_url( '/' ) ) );
            if ( ! $image ) $element->setAttribute( 'download', '' );
            $element->removeAttribute( 'data-sf-media' ); $found[$key] = true;
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( count( $found ) !== count( $files ) ) throw new \RuntimeException( __( 'Missing publication media binding.', 'atshift-article-composer' ) );
        $html = ''; foreach ( $dom->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) $html .= $dom->saveHTML( $node );
        // User text must never turn into a WordPress shortcode after publication.
        return str_replace( '[', '&#91;', $html );
    }

    public static function bytes( $id, $key ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) || ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{64}$/D', $key ) ) throw new \RuntimeException( __( 'Unavailable media.', 'atshift-article-composer' ) );
        $job = Publications::by_publication( $id );
        $post = $job && $job['post_id'] ? get_post( (int) $job['post_id'] ) : null;
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( ! $job || 'published' !== $job['state'] || ! Publications::matches_post( $job, $post, true ) || ! PublicationAccess::allows( $post ) ) throw new \RuntimeException( __( 'Unavailable media.', 'atshift-article-composer' ) );
        $files = json_decode( $job['files'], true ); $meta = $files[$key] ?? null;
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( ! $meta || ! preg_match( '/^[a-f0-9]{64}$/D', $meta['sha256'] ) ) throw new \RuntimeException( __( 'Unavailable media.', 'atshift-article-composer' ) );
        $dom = new \DOMDocument(); $old = libxml_use_internal_errors( true );
        try { $dom->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $post->post_content . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING ); }
        finally { libxml_clear_errors(); libxml_use_internal_errors( $old ); }
        $image = str_starts_with( $meta['mime'], 'image/' ); $linked = false;
        $url = add_query_arg( array( 'sf_public_media' => $id, 'sf_media_key' => $key ), home_url( '/' ) );
        foreach ( $dom->getElementsByTagName( $image ? 'img' : 'a' ) as $element ) {
            if ( $url === $element->getAttribute( $image ? 'src' : 'href' ) ) { $linked = true; break; }
        }
        // Removing a published binding also withdraws its previously known delivery URL.
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; the delivery handler returns an empty 404, never this text.
        if ( ! $linked ) throw new \RuntimeException( __( 'Unavailable media.', 'atshift-article-composer' ) );
        $bytes = PrivateBlobs::get( 'publication', $id . ':' . $meta['sha256'], $meta );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text exception; REST JSON-encodes messages and HTML callers escape at output.
        if ( ! is_string( $bytes ) || strlen( $bytes ) !== $meta['bytes'] || ! hash_equals( $meta['sha256'], hash( 'sha256', $bytes ) ) ) throw new \RuntimeException( __( 'Unavailable media.', 'atshift-article-composer' ) );
        return array( $meta, $bytes );
    }

    public static function serve() {
        // A known URL is not permission: bytes() rechecks the current viewer on every read.
        if ( ! isset( $_GET['sf_public_media'] ) ) return;
        PublicationAccess::private_headers(); header( 'X-Content-Type-Options: nosniff' ); header( 'Referrer-Policy: no-referrer' );
        header( "Content-Security-Policy: default-src 'none'; sandbox" );
        try {
            if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) throw new \RuntimeException();
            $id = is_string( $_GET['sf_public_media'] ?? null ) ? wp_unslash( $_GET['sf_public_media'] ) : '';
            $key = is_string( $_GET['sf_media_key'] ?? null ) ? wp_unslash( $_GET['sf_media_key'] ) : '';
            list( $meta, $bytes ) = self::bytes( $id, $key );
        } catch ( \Throwable $e ) { status_header( 404 ); exit; }
        header( 'Content-Type: ' . $meta['mime'] ); header( 'Content-Length: ' . strlen( $bytes ) );
        header( 'Content-Disposition: ' . ( 'application/pdf' === $meta['mime'] ? 'attachment' : 'inline' ) . '; filename="media.' . pathinfo( $meta['filename'], PATHINFO_EXTENSION ) . '"; filename*=UTF-8\'\'' . rawurlencode( $meta['filename'] ) );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Exact verified image/PDF response with validated MIME and nosniff, not HTML.
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) echo $bytes;
        exit;
    }
}
