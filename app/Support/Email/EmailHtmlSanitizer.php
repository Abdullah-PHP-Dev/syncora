<?php

namespace App\Support\Email;

use Mews\Purifier\Facades\Purifier;

/**
 * Save-time safety net for email template/campaign HTML. The block editor
 * (resources/js/components/email/lib/htmlGenerator.js) generates this HTML
 * client-side, and the hidden form field it populates is an ordinary
 * request field - nothing stops a tampered/hand-crafted POST from
 * submitting a <script> or an onerror="..." payload directly, bypassing
 * the generator entirely. This class is that boundary: it runs
 * unconditionally on every submitted body, regardless of whether it was
 * really produced by the generator.
 *
 * Two things the generator legitimately emits would otherwise be silently
 * destroyed by a naive Purifier::clean() call, defeating the whole point
 * of the email-safe HTML engine:
 *
 *   1. MSO conditional comments (<!--[if mso]>...<![endif]--> and the
 *      <!--[if !mso]><!--> / <!--<![endif]--> marker pair) - Purifier
 *      strips HTML comments by default. These are Outlook-only VML/table
 *      fallback markup (the bulletproof-button technique, fixed-width
 *      container wrappers) that must survive byte-for-byte.
 *   2. The <head><style> block (the responsive @media rules) - Purifier's
 *      HTML.Allowed doesn't include <style> by default, and properly
 *      reinstating it needs a CSS-tidy dependency beyond this app's two
 *      approved new packages (sortablejs, mews/purifier).
 *
 * Resolution: everything from the document start up to and including the
 * opening <body ...> tag, and everything from the closing </body> tag to
 * the end, is treated as trusted generator-authored preamble/postamble -
 * built by htmlGenerator.js entirely from validated numeric/enum settings
 * values (emailWidth, backgroundColor, ...), never from a user-typed HTML
 * string - and is never passed through Purifier at all. This fully solves
 * problem 2 (the <style> block lives in the preamble). Only the inner
 * <body> fragment - where a Text/Heading/Custom HTML block's actual
 * user-typed content lives - is purified.
 *
 * Within that fragment, MSO conditional comments are protected with inert
 * text placeholders before purifying and restored verbatim afterward,
 * solving problem 1. This is safe because the placeholder regexes only
 * match the exact "if mso" / "if !mso" conditional comment forms - a
 * Custom HTML block's raw content can't forge one undetected, since
 * whatever it contains either matches one of these narrow patterns (and
 * gets treated the same protective way, harmlessly) or doesn't (and goes
 * through Purifier like everything else).
 *
 * Load-bearing constraint on the generator this design depends on: mso-*
 * CSS properties must only ever appear inside the <head><style> block or
 * inside [if mso]-wrapped markup - never as an inline
 * style="mso-...:...;" attribute on a tag that also renders in modern
 * clients, or a future edit could reintroduce a silent-strip risk here.
 */
class EmailHtmlSanitizer
{
    private const MSO_BLOCK_PATTERN = '/<!--\[if\s+mso\]>.*?<!\[endif\]-->/is';
    private const MSO_MARKER_PATTERNS = [
        '/<!--\[if\s+!mso\]><!-->/i',
        '/<!--<!\[endif\]-->/i',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        [$preamble, $fragment, $postamble] = self::splitBody($html);

        [$fragment, $placeholders] = self::protectMsoComments($fragment);

        $purified = Purifier::clean($fragment, 'email');

        $purified = self::restoreMsoComments($purified, $placeholders);

        return $preamble . $purified . $postamble;
    }

    /**
     * Splits a full HTML document into [preamble, bodyFragment, postamble].
     * A string with no <body> tag (the legacy contenteditable editor never
     * wrapped its content in a full document - it only ever submitted an
     * inner fragment) is treated entirely as the fragment, with empty
     * pre/postamble, so it's purified as-is.
     */
    private static function splitBody(string $html): array
    {
        if (!preg_match('/<body\b[^>]*>/i', $html, $openMatch, PREG_OFFSET_CAPTURE)) {
            return ['', $html, ''];
        }

        $openTag = $openMatch[0][0];
        $openEnd = $openMatch[0][1] + strlen($openTag);

        $closePos = stripos($html, '</body>');
        if ($closePos === false || $closePos < $openEnd) {
            return ['', $html, ''];
        }

        return [
            substr($html, 0, $openEnd),
            substr($html, $openEnd, $closePos - $openEnd),
            substr($html, $closePos),
        ];
    }

    private static function protectMsoComments(string $fragment): array
    {
        $placeholders = [];
        $index = 0;

        $fragment = preg_replace_callback(self::MSO_BLOCK_PATTERN, function ($match) use (&$placeholders, &$index) {
            $token = self::token($index++);
            $placeholders[$token] = $match[0];

            return $token;
        }, $fragment);

        foreach (self::MSO_MARKER_PATTERNS as $pattern) {
            $fragment = preg_replace_callback($pattern, function ($match) use (&$placeholders, &$index) {
                $token = self::token($index++);
                $placeholders[$token] = $match[0];

                return $token;
            }, $fragment);
        }

        return [$fragment, $placeholders];
    }

    private static function restoreMsoComments(string $purified, array $placeholders): string
    {
        return strtr($purified, $placeholders);
    }

    private static function token(int $index): string
    {
        return '%%EMAILSANITIZER_MSO_' . $index . '%%';
    }
}
