// Declarative per-block-type property inspector definitions - one
// generic renderer (designer/InspectorPanel.vue) reads these instead of
// ten near-identical hand-written form components, since every group is
// just "a label + a small set of typed fields" and the actual markup
// never varies by block type, only which fields appear.
//
// Field `type` values the InspectorPanel renderer understands: text,
// textarea, url, number, select, color, toggle, align, padding, font,
// image (upload button), socialAccounts (special - reads from the
// social-accounts prop, not a simple value).
//
// A field's value normally binds to block.settings[key]; `target:
// 'content'` binds to block.content instead (used by Text/Heading/HTML,
// whose primary editable content isn't a "setting").

const WEIGHT_OPTIONS = [
  { value: 'normal', label: 'Normal' },
  { value: 'bold', label: 'Bold' },
];

const TYPOGRAPHY_FIELDS = [
  { key: 'fontFamily', label: 'Font', type: 'font' },
  { key: 'fontSize', label: 'Size', type: 'number', min: 8, max: 72, suffix: 'px' },
  { key: 'fontWeight', label: 'Weight', type: 'select', options: WEIGHT_OPTIONS },
  { key: 'lineHeight', label: 'Line height', type: 'number', step: 0.1, min: 1, max: 3 },
  { key: 'letterSpacing', label: 'Letter spacing', type: 'number', suffix: 'px' },
];

const ADVANCED_FIELDS = [
  { key: 'customClass', label: 'Custom class', type: 'text' },
  { key: 'hideOnMobile', label: 'Hide on mobile', type: 'toggle' },
];

const PADDING_FIELD = { key: 'padding', label: 'Padding', type: 'padding' };
const ALIGN_FIELD = { key: 'align', label: 'Alignment', type: 'align' };

export const INSPECTOR_SCHEMAS = {
  text: [
    { group: 'Typography', fields: TYPOGRAPHY_FIELDS },
    { group: 'Colors', fields: [
      { key: 'color', label: 'Text color', type: 'color' },
      { key: 'backgroundColor', label: 'Background', type: 'color', allowTransparent: true },
    ] },
    { group: 'Alignment', fields: [ALIGN_FIELD] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
    { group: 'Advanced', fields: ADVANCED_FIELDS },
  ],

  heading: [
    { group: 'Content', fields: [
      { key: 'level', label: 'Heading level', type: 'select', options: [1, 2, 3, 4, 5, 6].map((n) => ({ value: n, label: 'H' + n })) },
    ] },
    { group: 'Typography', fields: TYPOGRAPHY_FIELDS },
    { group: 'Colors', fields: [
      { key: 'color', label: 'Text color', type: 'color' },
      { key: 'backgroundColor', label: 'Background', type: 'color', allowTransparent: true },
    ] },
    { group: 'Alignment', fields: [ALIGN_FIELD] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
    { group: 'Advanced', fields: ADVANCED_FIELDS },
  ],

  image: [
    { group: 'Image', fields: [
      { key: 'src', label: 'Image', type: 'image' },
      { key: 'alt', label: 'Alt text', type: 'text' },
    ] },
    { group: 'Link', fields: [
      { key: 'link', label: 'URL', type: 'url' },
      { key: 'newTab', label: 'Open in new tab', type: 'toggle' },
    ] },
    { group: 'Dimensions', fields: [
      { key: 'width', label: 'Width', type: 'number', min: 20, max: 1200, suffix: 'px' },
    ] },
    { group: 'Alignment', fields: [ALIGN_FIELD] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
    { group: 'Border', fields: [
      { key: 'borderWidth', label: 'Border width', type: 'number', min: 0, max: 20, suffix: 'px' },
      { key: 'borderColor', label: 'Border color', type: 'color' },
      { key: 'borderRadius', label: 'Corner radius', type: 'number', min: 0, max: 100, suffix: 'px' },
    ] },
    { group: 'Advanced', fields: ADVANCED_FIELDS },
  ],

  button: [
    { group: 'Content', fields: [
      { key: 'label', label: 'Button text', type: 'text' },
      { key: 'url', label: 'URL', type: 'url' },
      { key: 'newTab', label: 'Open in new tab', type: 'toggle' },
    ] },
    { group: 'Typography', fields: [
      { key: 'fontFamily', label: 'Font', type: 'font' },
      { key: 'fontSize', label: 'Size', type: 'number', min: 10, max: 40, suffix: 'px' },
      { key: 'fontWeight', label: 'Weight', type: 'select', options: WEIGHT_OPTIONS },
    ] },
    { group: 'Colors', fields: [
      { key: 'bg', label: 'Background', type: 'color' },
      { key: 'color', label: 'Text color', type: 'color' },
    ] },
    { group: 'Dimensions', fields: [
      { key: 'fullWidth', label: 'Full width', type: 'toggle' },
    ] },
    { group: 'Alignment', fields: [ALIGN_FIELD] },
    { group: 'Spacing', fields: [
      { key: 'paddingY', label: 'Inner padding (top/bottom)', type: 'number', min: 0, max: 60, suffix: 'px' },
      { key: 'paddingX', label: 'Inner padding (left/right)', type: 'number', min: 0, max: 80, suffix: 'px' },
      { key: 'marginTop', label: 'Outer margin (top)', type: 'number', min: 0, max: 80, suffix: 'px' },
      { key: 'marginBottom', label: 'Outer margin (bottom)', type: 'number', min: 0, max: 80, suffix: 'px' },
    ] },
    { group: 'Border', fields: [
      { key: 'radius', label: 'Corner radius', type: 'number', min: 0, max: 40, suffix: 'px' },
    ] },
    { group: 'Advanced', fields: ADVANCED_FIELDS },
  ],

  link: [
    { group: 'Content', fields: [
      { key: 'label', label: 'Link text', type: 'text' },
      { key: 'url', label: 'URL', type: 'url' },
      { key: 'newTab', label: 'Open in new tab', type: 'toggle' },
    ] },
    { group: 'Typography', fields: [
      ...TYPOGRAPHY_FIELDS.filter((f) => f.key !== 'letterSpacing' && f.key !== 'lineHeight'),
      { key: 'underline', label: 'Underline', type: 'toggle' },
    ] },
    { group: 'Colors', fields: [
      { key: 'color', label: 'Text color', type: 'color' },
    ] },
    { group: 'Alignment', fields: [ALIGN_FIELD] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
    { group: 'Advanced', fields: ADVANCED_FIELDS },
  ],

  divider: [
    { group: 'Style', fields: [
      { key: 'style', label: 'Line style', type: 'select', options: [
        { value: 'solid', label: 'Solid' },
        { value: 'dashed', label: 'Dashed' },
        { value: 'dotted', label: 'Dotted' },
      ] },
    ] },
    { group: 'Colors', fields: [
      { key: 'color', label: 'Line color', type: 'color' },
    ] },
    { group: 'Dimensions', fields: [
      { key: 'thickness', label: 'Thickness', type: 'number', min: 1, max: 20, suffix: 'px' },
      { key: 'widthPercent', label: 'Width', type: 'number', min: 5, max: 100, suffix: '%' },
    ] },
    { group: 'Spacing', fields: [
      { key: 'paddingTop', label: 'Padding (top)', type: 'number', min: 0, max: 100, suffix: 'px' },
      { key: 'paddingBottom', label: 'Padding (bottom)', type: 'number', min: 0, max: 100, suffix: 'px' },
    ] },
    { group: 'Advanced', fields: [ADVANCED_FIELDS[1]] },
  ],

  spacer: [
    { group: 'Dimensions', fields: [
      { key: 'height', label: 'Height', type: 'number', min: 4, max: 200, suffix: 'px' },
    ] },
    { group: 'Advanced', fields: [
      { key: 'collapseOnMobile', label: 'Collapse on mobile', type: 'toggle' },
    ] },
  ],

  social: [
    { group: 'Content', fields: [
      { key: 'accounts', label: 'Connected accounts', type: 'socialAccounts' },
    ] },
    { group: 'Style', fields: [
      { key: 'iconSize', label: 'Icon size', type: 'number', min: 16, max: 64, suffix: 'px' },
      { key: 'gap', label: 'Gap', type: 'number', min: 0, max: 40, suffix: 'px' },
    ] },
    { group: 'Alignment', fields: [ALIGN_FIELD] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
    { group: 'Advanced', fields: [ADVANCED_FIELDS[1]] },
  ],

  html: [
    { group: 'Content', fields: [
      { key: 'content', label: 'HTML Content', type: 'textarea', target: 'content', rows: 12, note: 'Sanitized before saving - scripts and embeds are removed.' },
    ] },
    { group: 'Advanced', fields: ADVANCED_FIELDS },
  ],

  section: [
    { group: 'Background', fields: [
      { key: 'backgroundColor', label: 'Background color', type: 'color', allowTransparent: true },
    ] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
  ],

  column: [
    { group: 'Layout', fields: [
      { key: 'width', label: 'Column width', type: 'text', target: 'width' },
      { key: 'stackOnMobile', label: 'Stack on mobile', type: 'toggle' },
    ] },
    { group: 'Background', fields: [
      { key: 'backgroundColor', label: 'Background color', type: 'color', allowTransparent: true },
    ] },
    { group: 'Spacing', fields: [PADDING_FIELD] },
    { group: 'Border', fields: [
      { key: 'borderWidth', label: 'Border width', type: 'number', min: 0, max: 20, suffix: 'px' },
      { key: 'borderColor', label: 'Border color', type: 'color' },
      { key: 'borderRadius', label: 'Corner radius', type: 'number', min: 0, max: 100, suffix: 'px' },
    ] },
  ],
};

export const BLOCK_LIBRARY = [
  { type: 'text', label: 'Text', icon: 'bx-text', description: 'A paragraph of text' },
  { type: 'heading', label: 'Heading', icon: 'bx-heading', description: 'A section title' },
  { type: 'image', label: 'Image', icon: 'bx-image', description: 'Upload or link an image' },
  { type: 'button', label: 'Button', icon: 'bx-purchase-tag-alt', description: 'A clickable call-to-action' },
  { type: 'link', label: 'Link', icon: 'bx-link', description: 'A styled text link' },
  { type: 'divider', label: 'Divider', icon: 'bx-minus', description: 'A horizontal line' },
  { type: 'spacer', label: 'Spacer', icon: 'bx-move-vertical', description: 'Vertical blank space' },
  { type: 'social', label: 'Social Icons', icon: 'bx-share-alt', description: 'Links to your social profiles' },
  { type: 'html', label: 'Custom HTML', icon: 'bx-code-alt', description: 'Raw HTML, sanitized on save' },
];

export const LAYOUT_PRESETS = [
  { preset: 'oneColumn', label: '1 Column', icon: 'bx-rectangle', description: 'A single full-width section' },
  { preset: 'twoColumn', label: '2 Columns', icon: 'bx-columns', description: 'Two side-by-side columns' },
  { preset: 'threeColumn', label: '3 Columns', icon: 'bx-grid-horizontal', description: 'Three side-by-side columns' },
  { preset: 'header', label: 'Header', icon: 'bx-dock-top', description: 'Logo + tagline' },
  { preset: 'footer', label: 'Footer', icon: 'bx-dock-bottom', description: 'Divider, social icons, address' },
];
