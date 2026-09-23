<?php
/**
 * Ok, glad you are here
 * first we get a config instance, and set the settings
 * $config = HTMLPurifier_Config::createDefault();
 * $config->set('Core.Encoding', $this->config->get('purifier.encoding'));
 * $config->set('Cache.SerializerPath', $this->config->get('purifier.cachePath'));
 * if ( ! $this->config->get('purifier.finalize')) {
 *     $config->autoFinalize = false;
 * }
 * $config->loadArray($this->getConfig());
 *
 * You must NOT delete the default settings
 * anything in settings should be compacted with params that needed to instance HTMLPurifier_Config.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding'           => 'UTF-8',
    'finalize'           => true,
    'ignoreNonStrings'   => false,
    'cachePath'          => storage_path('app/purifier'),
    'cacheFileMode'      => 0755,
    'settings'      => [
        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            'HTML.Allowed'             => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties'    => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,
        ],
        'test'    => [
            'Attr.EnableID' => 'true',
        ],
        // Used by App\Support\Email\EmailHtmlSanitizer as a save-time
        // safety net on submitted email template/campaign HTML (see that
        // class's docblock for why only the <body> inner fragment is ever
        // passed through this profile - MSO conditional comments and the
        // <head><style> block are handled separately, never by Purifier).
        // Allowlist matches exactly what resources/js/components/email's
        // htmlGenerator.js emits: table-based layout, inline styles, real
        // hosted <img> icons, mailto/http(s) links only.
        'email' => [
            'HTML.Doctype'          => 'HTML 4.01 Transitional',
            'HTML.Allowed'          => 'table[width|cellpadding|cellspacing|border|role|align|bgcolor|style],tr[style],td[width|height|align|valign|style|bgcolor|colspan|rowspan],th[style],img[src|alt|width|height|style|border|align],a[href|target|rel|style],p[style],div[style],span[style],br,hr,b,strong,i,em,u,ul[style],ol[style],li[style],h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],center',
            'CSS.AllowedProperties' => 'width,max-width,min-width,height,max-height,min-height,padding,padding-top,padding-right,padding-bottom,padding-left,margin,margin-top,margin-right,margin-bottom,margin-left,background,background-color,background-image,color,font,font-family,font-size,font-weight,font-style,line-height,letter-spacing,text-align,text-decoration,text-transform,vertical-align,border,border-top,border-right,border-bottom,border-left,border-color,border-width,border-style,border-radius,display,float,white-space',
            // border-radius lives behind CSS.Proprietary and display
            // behind CSS.AllowTricky in HTMLPurifier - both neutral/safe
            // layout flags (neither is a security control), but without
            // them HTMLPurifier silently drops those two properties from
            // every style="..." attribute even though CSS.AllowedProperties
            // lists them, which would quietly flatten every Button block's
            // rounded corners and inline-block layout on every save.
            'CSS.Proprietary'       => true,
            'CSS.AllowTricky'       => true,
            'HTML.ForbiddenElements' => 'script,iframe,object,embed,form,style,link,meta,base',
            'URI.AllowedSchemes'    => ['http' => true, 'https' => true, 'mailto' => true],
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty'   => false,
        ],
        "youtube" => [
            "HTML.SafeIframe"      => 'true',
            "URI.SafeIframeRegexp" => "%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%",
        ],
        'custom_definition' => [
            'id'  => 'html5-definitions',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // http://developers.whatwg.org/sections.html
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],
				
				// Content model actually excludes several tags, not modelled here
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common'],
				
				// http://developers.whatwg.org/grouping-content.html
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
				
				// http://developers.whatwg.org/the-video-element.html#the-video-element
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src' => 'URI',
					'type' => 'Text',
					'width' => 'Length',
					'height' => 'Length',
					'poster' => 'URI',
					'preload' => 'Enum#auto,metadata,none',
					'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
					'src' => 'URI',
					'type' => 'Text',
                ]],

				// http://developers.whatwg.org/text-level-semantics.html
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty', 'Core'],
				
				// http://developers.whatwg.org/edits.html
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['th', 'border', 'Text'],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_target,_top'],
            // role="presentation" on layout tables - a real HTML5/ARIA
            // attribute the email HTML generator emits (see the 'email'
            // profile below), not recognized under the HTML 4.01
            // Transitional doctype used here without this.
            ['table', 'role', 'Enum#presentation'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],

];
