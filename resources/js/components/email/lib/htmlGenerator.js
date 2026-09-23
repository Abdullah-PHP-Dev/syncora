/**
 * Walks a block-schema JSON tree (see EmailTemplateDesigner.vue's top
 * comment for the shape) and emits email-client-safe HTML: table-based
 * layout, inline styles, MSO conditional comments for Outlook desktop
 * (which ignores @media entirely), and a real HTML `width` attribute
 * everywhere Outlook needs one in addition to CSS.
 *
 * Used for BOTH the live preview iframe and the final HTML submitted with
 * the form - one implementation, so preview and saved output can never
 * drift from each other. The server (App\Support\Email\EmailHtmlSanitizer)
 * only ever sanitizes whatever this produces, it never re-generates it.
 *
 * LOAD-BEARING CONSTRAINT the sanitizer's design depends on: mso-*
 * CSS properties must only ever appear inside the <head><style> block
 * built by renderDocument(), or inside [if mso]-wrapped markup - never as
 * an inline style="mso-...:...;" attribute on a tag that also renders in
 * modern clients. Do not add one without re-reading
 * app/Support/Email/EmailHtmlSanitizer.php's docblock first.
 */

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function padStyle(padding) {
  const p = padding || {};
  return `padding:${p.top ?? 0}px ${p.right ?? 0}px ${p.bottom ?? 0}px ${p.left ?? 0}px;`;
}

function alignToBlock(align) {
  return align === 'center' ? 'margin-left:auto;margin-right:auto;' : (align === 'right' ? 'margin-left:auto;' : '');
}

function hideClass(hideOnMobile) {
  return hideOnMobile ? ' hide-mobile' : '';
}

function renderText(block) {
  const s = block.settings;
  const style = [
    padStyle(s.padding),
    `font-family:${s.fontFamily};`,
    `font-size:${s.fontSize}px;`,
    `font-weight:${s.fontWeight};`,
    `line-height:${s.lineHeight};`,
    `letter-spacing:${s.letterSpacing}px;`,
    `color:${s.color};`,
    s.backgroundColor && s.backgroundColor !== 'transparent' ? `background-color:${s.backgroundColor};` : '',
    `text-align:${s.align};`,
  ].join('');

  return `<tr><td class="${s.customClass || ''}${hideClass(s.hideOnMobile)}" style="${style}">${block.content || ''}</td></tr>`;
}

function renderHeading(block) {
  const s = block.settings;
  const level = Math.min(6, Math.max(1, Number(s.level) || 2));
  const style = [
    `margin:0;`,
    `font-family:${s.fontFamily};`,
    `font-size:${s.fontSize}px;`,
    `font-weight:${s.fontWeight};`,
    `line-height:${s.lineHeight};`,
    `letter-spacing:${s.letterSpacing}px;`,
    `color:${s.color};`,
    `text-align:${s.align};`,
  ].join('');

  return `<tr><td class="${s.customClass || ''}${hideClass(s.hideOnMobile)}" style="${padStyle(s.padding)}${s.backgroundColor && s.backgroundColor !== 'transparent' ? `background-color:${s.backgroundColor};` : ''}">`
    + `<h${level} style="${style}">${block.content || ''}</h${level}>`
    + `</td></tr>`;
}

function renderImage(block) {
  const s = block.settings;
  if (!s.src) {
    return '';
  }

  const imgStyle = `display:block;width:100%;max-width:${s.width}px;height:auto;border:0;outline:none;text-decoration:none;border-radius:${s.borderRadius}px;${s.borderWidth ? `border:${s.borderWidth}px solid ${s.borderColor};` : ''}`;
  const img = `<img src="${escapeHtml(s.src)}" width="${s.width}" alt="${escapeHtml(s.alt)}" style="${imgStyle}" />`;
  const linked = s.link ? `<a href="${escapeHtml(s.link)}"${s.newTab ? ' target="_blank" rel="noopener"' : ''} style="text-decoration:none;">${img}</a>` : img;

  return `<tr><td class="${hideClass(s.hideOnMobile)}" style="${padStyle(s.padding)}text-align:${s.align};">${linked}</td></tr>`;
}

// The bulletproof-button / VML technique: Outlook desktop ignores
// border-radius and renders <a> padding inconsistently, so it gets a VML
// <v:roundrect> fallback wrapped in [if mso], paired with a normal
// table+anchor for every other client wrapped in [if !mso].
function renderButton(block) {
  const s = block.settings;
  if (!s.label) {
    return '';
  }

  const widthAttr = s.fullWidth ? ' width="100%"' : '';
  const vmlWidth = s.fullWidth ? '100%' : 'auto';
  const target = s.newTab ? ' target="_blank" rel="noopener"' : '';

  const mso = `<!--[if mso]>
<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="${escapeHtml(s.url)}" style="height:${s.fontSize + s.paddingY * 2}px;v-text-anchor:middle;width:${vmlWidth === 'auto' ? '240px' : vmlWidth};" arcsize="${Math.round((s.radius / (s.fontSize + s.paddingY * 2)) * 100)}%" strokecolor="${s.bg}" fillcolor="${s.bg}">
<w:anchorlock/>
<center style="color:${s.color};font-family:${s.fontFamily};font-size:${s.fontSize}px;font-weight:${s.fontWeight};">${escapeHtml(s.label)}</center>
</v:roundrect>
<![endif]-->`;

  const fallback = `<!--[if !mso]><!-->
<table role="presentation" cellpadding="0" cellspacing="0" border="0"${widthAttr} style="${alignToBlock(s.align)}"><tr>
<td style="border-radius:${s.radius}px;background:${s.bg};" align="center">
<a href="${escapeHtml(s.url)}"${target} style="display:inline-block;${s.fullWidth ? 'width:100%;box-sizing:border-box;' : ''}padding:${s.paddingY}px ${s.paddingX}px;font-family:${s.fontFamily};font-size:${s.fontSize}px;font-weight:${s.fontWeight};color:${s.color};text-decoration:none;border-radius:${s.radius}px;">${escapeHtml(s.label)}</a>
</td></tr></table>
<!--<![endif]-->`;

  return `<tr><td class="${hideClass(s.hideOnMobile)}" style="padding:${s.marginTop}px 0 ${s.marginBottom}px 0;text-align:${s.align};">${mso}${fallback}</td></tr>`;
}

function renderLink(block) {
  const s = block.settings;
  if (!s.label) {
    return '';
  }

  const style = `font-family:${s.fontFamily};font-size:${s.fontSize}px;font-weight:${s.fontWeight};color:${s.color};text-decoration:${s.underline ? 'underline' : 'none'};`;
  const target = s.newTab ? ' target="_blank" rel="noopener"' : '';

  return `<tr><td class="${hideClass(s.hideOnMobile)}" style="${padStyle(s.padding)}text-align:${s.align};"><a href="${escapeHtml(s.url)}"${target} style="${style}">${escapeHtml(s.label)}</a></td></tr>`;
}

// A bordered <td>, not <hr> - <hr> renders inconsistently (or not at all)
// across mail clients.
function renderDivider(block) {
  const s = block.settings;

  return `<tr><td class="${hideClass(s.hideOnMobile)}" style="padding:${s.paddingTop}px 0 ${s.paddingBottom}px 0;">`
    + `<table role="presentation" width="${s.widthPercent}%" cellpadding="0" cellspacing="0" border="0" align="center"><tr>`
    + `<td style="border-top:${s.thickness}px ${s.style} ${s.color};font-size:0;line-height:0;">&nbsp;</td>`
    + `</tr></table></td></tr>`;
}

// Both the HTML height attribute and CSS height - some clients only
// honor one or the other.
function renderSpacer(block) {
  const s = block.settings;
  const cls = s.collapseOnMobile ? ' collapse-mobile' : '';

  return `<tr><td height="${s.height}" class="${cls}" style="font-size:0;line-height:0;height:${s.height}px;">&nbsp;</td></tr>`;
}

// Real hosted PNG icons (matching the legacy editor's own deliberate
// choice - icon fonts don't survive an iframe srcdoc preview or real mail
// clients), not icon-font glyphs.
function renderSocial(block) {
  const s = block.settings;
  const accounts = (s.accounts || []).filter((a) => a.url);
  if (accounts.length === 0) {
    return '';
  }

  const cells = accounts.map((a) => `<td style="padding:0 ${s.gap / 2}px;"><a href="${escapeHtml(a.url)}" target="_blank" rel="noopener"><img src="/assets/img/icons/social/${a.platform}.png" width="${s.iconSize}" height="${s.iconSize}" alt="${escapeHtml(a.platform)}" style="display:block;border:0;" /></a></td>`).join('');

  return `<tr><td class="${hideClass(s.hideOnMobile)}" style="${padStyle(s.padding)}text-align:${s.align};">`
    + `<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="${s.align === 'left' ? 'left' : (s.align === 'right' ? 'right' : 'center')}"><tr>${cells}</tr></table>`
    + `</td></tr>`;
}

// Raw content inserted verbatim - no client-side transform. The server
// sanitizer is the only guard for this block type; the in-canvas preview
// already renders it inside a sandboxed <iframe srcdoc>, so any injected
// script only ever executes there.
function renderHtml(block) {
  const s = block.settings;
  return `<tr><td class="${s.customClass || ''}${hideClass(s.hideOnMobile)}">${block.content || ''}</td></tr>`;
}

const BLOCK_RENDERERS = {
  text: renderText,
  heading: renderHeading,
  image: renderImage,
  button: renderButton,
  link: renderLink,
  divider: renderDivider,
  spacer: renderSpacer,
  social: renderSocial,
  html: renderHtml,
};

function renderBlock(block) {
  const renderer = BLOCK_RENDERERS[block.type];
  return renderer ? renderer(block) : '';
}

function renderColumn(column, emailWidth) {
  const s = column.settings || {};
  const percent = parseFloat(column.width) || 100;
  const pxWidth = Math.round((percent / 100) * emailWidth);
  const stackClass = s.stackOnMobile !== false ? ' stack-column' : '';

  const border = s.borderWidth ? `border:${s.borderWidth}px solid ${s.borderColor};` : '';
  const style = `vertical-align:top;width:${pxWidth}px;${padStyle(s.padding)}${s.backgroundColor && s.backgroundColor !== 'transparent' ? `background-color:${s.backgroundColor};` : ''}${border}border-radius:${s.borderRadius || 0}px;`;

  const blocksHtml = (column.blocks || []).map(renderBlock).join('');
  const inner = `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tbody>${blocksHtml}</tbody></table>`;

  // A real HTML `width` attribute (not just CSS) is what Outlook desktop
  // actually needs for reliable side-by-side columns - no separate MSO-only
  // copy of the content required for that.
  return `<td width="${pxWidth}" class="${stackClass}" style="${style}">${inner}</td>`;
}

// The column content is embedded EXACTLY ONCE - only the enclosing <table>
// tag differs between Outlook and every modern client (explicit pixel
// width vs width:100%, mirroring generateEmailHtml()'s own top-level
// document wrapper below), via two independently self-closing MSO
// conditional comments around it.
//
// A per-column `<!--[if mso]>...<![endif]--><td>...</td><!--[if mso]>
// ...<![endif]-->` pattern looks equivalent but is NOT: each
// `<!--[if mso]>...<![endif]-->` closes itself as its own ordinary HTML
// comment the instant a normal (non-Outlook) browser parses it - Outlook's
// Word engine is the only thing that treats a whole family of `[if mso]`/
// `[if !mso]` markers as one spanning conditional. That means anything
// placed BETWEEN two independently-closed comments is plain, visible HTML
// to every other client - so duplicating the column content once inside
// an "mso" comment and once inside an "!mso" comment doesn't hide either
// copy from a normal browser; it renders both, back to back. That was this
// function's actual bug: every block appeared twice in the live preview
// and in the saved/sent email alike, because "hidden from browsers" never
// actually held for the mso-wrapped copy.
function renderSectionFull(section, emailWidth) {
  const s = section.settings || {};
  const columnsHtml = (section.columns || []).map((c) => renderColumn(c, emailWidth)).join('');

  const sectionStyle = `${s.backgroundColor && s.backgroundColor !== 'transparent' ? `background-color:${s.backgroundColor};` : ''}${padStyle(s.padding)}`;

  return `<tr><td style="${sectionStyle}">`
    + `<!--[if mso]><table role="presentation" width="${emailWidth}" cellpadding="0" cellspacing="0" border="0"><tr><![endif]-->`
    + `<!--[if !mso]><!--><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><!--<![endif]-->`
    + columnsHtml
    + `<!--[if !mso]><!--></tr></table><!--<![endif]-->`
    + `<!--[if mso]></tr></table><![endif]-->`
    + `</td></tr>`;
}

export function generateEmailHtml(schema) {
  const settings = (schema && schema.settings) || {};
  const emailWidth = settings.emailWidth || 600;
  const fontFamily = settings.fontFamily || 'Arial, Helvetica, sans-serif';
  const backgroundColor = settings.backgroundColor || '#f5f5fa';
  const sections = (schema && schema.sections) || [];

  const sectionsHtml = sections.map((s) => renderSectionFull(s, emailWidth)).join('');

  return `<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  body,table,td,a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
  table,td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
  img { -ms-interpolation-mode:bicubic; border:0; height:auto; outline:none; text-decoration:none; }
  body { margin:0; padding:0; width:100% !important; background:${backgroundColor}; font-family:${fontFamily}; }
  .email-container { width:${emailWidth}px; max-width:100%; }
  @media only screen and (max-width:${emailWidth}px) {
    .email-container { width:100% !important; }
    .stack-column { display:block !important; width:100% !important; }
    .hide-mobile { display:none !important; max-height:0 !important; overflow:hidden !important; }
    .collapse-mobile { display:none !important; height:0 !important; line-height:0 !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:${backgroundColor};">
<center>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
<!--[if mso]>
<table role="presentation" width="${emailWidth}" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td>
<![endif]-->
<table role="presentation" class="email-container" width="${emailWidth}" cellpadding="0" cellspacing="0" border="0" align="center" style="width:${emailWidth}px;max-width:100%;">
${sectionsHtml}
</table>
<!--[if mso]>
</td></tr></table>
<![endif]-->
</td></tr></table>
</center>
</body>
</html>`;
}
