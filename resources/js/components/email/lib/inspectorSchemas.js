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
// `label` / `note` / an option's `key` and a group's `group` are keys
// into lang/{en,ar}/email_designer.php (fields.*, options.*, groups.*);
// BLOCK_LIBRARY / LAYOUT_PRESETS text lives under blocks.* / layouts.*.
//
// A field's value normally binds to block.settings[key]; `target:
// 'content'` binds to block.content instead (used by Text/Heading/HTML,
// whose primary editable content isn't a "setting").

const WEIGHT_OPTIONS = [
  { value: 'normal', key: 'normal' },
  { value: 'bold', key: 'bold' },
];

const TYPOGRAPHY_FIELDS = [
  { key: 'fontFamily', label: 'font', type: 'font' },
  { key: 'fontSize', label: 'size', type: 'number', min: 8, max: 72, suffix: 'px' },
  { key: 'fontWeight', label: 'weight', type: 'select', options: WEIGHT_OPTIONS },
  { key: 'lineHeight', label: 'line_height', type: 'number', step: 0.1, min: 1, max: 3 },
  { key: 'letterSpacing', label: 'letter_spacing', type: 'number', suffix: 'px' },
];

const ADVANCED_FIELDS = [
  { key: 'customClass', label: 'custom_class', type: 'text' },
  { key: 'hideOnMobile', label: 'hide_on_mobile', type: 'toggle' },
];

const PADDING_FIELD = { key: 'padding', label: 'padding', type: 'padding' };
const ALIGN_FIELD = { key: 'align', label: 'alignment', type: 'align' };

export const INSPECTOR_SCHEMAS = {
  text: [
    { group: 'typography', fields: TYPOGRAPHY_FIELDS },
    { group: 'colors', fields: [
      { key: 'color', label: 'text_color', type: 'color' },
      { key: 'backgroundColor', label: 'background', type: 'color', allowTransparent: true },
    ] },
    { group: 'alignment', fields: [ALIGN_FIELD] },
    { group: 'spacing', fields: [PADDING_FIELD] },
    { group: 'advanced', fields: ADVANCED_FIELDS },
  ],

  heading: [
    { group: 'content', fields: [
      { key: 'level', label: 'heading_level', type: 'select', options: [1, 2, 3, 4, 5, 6].map((n) => ({ value: n, label: 'H' + n })) },
    ] },
    { group: 'typography', fields: TYPOGRAPHY_FIELDS },
    { group: 'colors', fields: [
      { key: 'color', label: 'text_color', type: 'color' },
      { key: 'backgroundColor', label: 'background', type: 'color', allowTransparent: true },
    ] },
    { group: 'alignment', fields: [ALIGN_FIELD] },
    { group: 'spacing', fields: [PADDING_FIELD] },
    { group: 'advanced', fields: ADVANCED_FIELDS },
  ],

  image: [
    { group: 'image', fields: [
      { key: 'src', label: 'image', type: 'image' },
      { key: 'alt', label: 'alt_text', type: 'text' },
    ] },
    { group: 'link', fields: [
      { key: 'link', label: 'url', type: 'url' },
      { key: 'newTab', label: 'new_tab', type: 'toggle' },
    ] },
    { group: 'dimensions', fields: [
      { key: 'width', label: 'width', type: 'number', min: 20, max: 1200, suffix: 'px' },
    ] },
    { group: 'alignment', fields: [ALIGN_FIELD] },
    { group: 'spacing', fields: [PADDING_FIELD] },
    { group: 'border', fields: [
      { key: 'borderWidth', label: 'border_width', type: 'number', min: 0, max: 20, suffix: 'px' },
      { key: 'borderColor', label: 'border_color', type: 'color' },
      { key: 'borderRadius', label: 'corner_radius', type: 'number', min: 0, max: 100, suffix: 'px' },
    ] },
    { group: 'advanced', fields: ADVANCED_FIELDS },
  ],

  button: [
    { group: 'content', fields: [
      { key: 'label', label: 'button_text', type: 'text' },
      { key: 'url', label: 'url', type: 'url' },
      { key: 'newTab', label: 'new_tab', type: 'toggle' },
    ] },
    { group: 'typography', fields: [
      { key: 'fontFamily', label: 'font', type: 'font' },
      { key: 'fontSize', label: 'size', type: 'number', min: 10, max: 40, suffix: 'px' },
      { key: 'fontWeight', label: 'weight', type: 'select', options: WEIGHT_OPTIONS },
    ] },
    { group: 'colors', fields: [
      { key: 'bg', label: 'background', type: 'color' },
      { key: 'color', label: 'text_color', type: 'color' },
    ] },
    { group: 'dimensions', fields: [
      { key: 'fullWidth', label: 'full_width', type: 'toggle' },
    ] },
    { group: 'alignment', fields: [ALIGN_FIELD] },
    { group: 'spacing', fields: [
      { key: 'paddingY', label: 'inner_padding_y', type: 'number', min: 0, max: 60, suffix: 'px' },
      { key: 'paddingX', label: 'inner_padding_x', type: 'number', min: 0, max: 80, suffix: 'px' },
      { key: 'marginTop', label: 'margin_top', type: 'number', min: 0, max: 80, suffix: 'px' },
      { key: 'marginBottom', label: 'margin_bottom', type: 'number', min: 0, max: 80, suffix: 'px' },
    ] },
    { group: 'border', fields: [
      { key: 'radius', label: 'corner_radius', type: 'number', min: 0, max: 40, suffix: 'px' },
    ] },
    { group: 'advanced', fields: ADVANCED_FIELDS },
  ],

  link: [
    { group: 'content', fields: [
      { key: 'label', label: 'link_text', type: 'text' },
      { key: 'url', label: 'url', type: 'url' },
      { key: 'newTab', label: 'new_tab', type: 'toggle' },
    ] },
    { group: 'typography', fields: [
      ...TYPOGRAPHY_FIELDS.filter((f) => f.key !== 'letterSpacing' && f.key !== 'lineHeight'),
      { key: 'underline', label: 'underline', type: 'toggle' },
    ] },
    { group: 'colors', fields: [
      { key: 'color', label: 'text_color', type: 'color' },
    ] },
    { group: 'alignment', fields: [ALIGN_FIELD] },
    { group: 'spacing', fields: [PADDING_FIELD] },
    { group: 'advanced', fields: ADVANCED_FIELDS },
  ],

  divider: [
    { group: 'style', fields: [
      { key: 'style', label: 'line_style', type: 'select', options: [
        { value: 'solid', key: 'solid' },
        { value: 'dashed', key: 'dashed' },
        { value: 'dotted', key: 'dotted' },
      ] },
    ] },
    { group: 'colors', fields: [
      { key: 'color', label: 'line_color', type: 'color' },
    ] },
    { group: 'dimensions', fields: [
      { key: 'thickness', label: 'thickness', type: 'number', min: 1, max: 20, suffix: 'px' },
      { key: 'widthPercent', label: 'width', type: 'number', min: 5, max: 100, suffix: '%' },
    ] },
    { group: 'spacing', fields: [
      { key: 'paddingTop', label: 'padding_top', type: 'number', min: 0, max: 100, suffix: 'px' },
      { key: 'paddingBottom', label: 'padding_bottom', type: 'number', min: 0, max: 100, suffix: 'px' },
    ] },
    { group: 'advanced', fields: [ADVANCED_FIELDS[1]] },
  ],

  spacer: [
    { group: 'dimensions', fields: [
      { key: 'height', label: 'height', type: 'number', min: 4, max: 200, suffix: 'px' },
    ] },
    { group: 'advanced', fields: [
      { key: 'collapseOnMobile', label: 'collapse_on_mobile', type: 'toggle' },
    ] },
  ],

  social: [
    { group: 'content', fields: [
      { key: 'accounts', label: 'connected_accounts', type: 'socialAccounts' },
    ] },
    { group: 'style', fields: [
      { key: 'iconSize', label: 'icon_size', type: 'number', min: 16, max: 64, suffix: 'px' },
      { key: 'gap', label: 'gap', type: 'number', min: 0, max: 40, suffix: 'px' },
    ] },
    { group: 'alignment', fields: [ALIGN_FIELD] },
    { group: 'spacing', fields: [PADDING_FIELD] },
    { group: 'advanced', fields: [ADVANCED_FIELDS[1]] },
  ],

  html: [
    { group: 'content', fields: [
      { key: 'content', label: 'html_content', type: 'textarea', target: 'content', rows: 12, note: 'html_note' },
    ] },
    { group: 'advanced', fields: ADVANCED_FIELDS },
  ],

  section: [
    { group: 'background', fields: [
      { key: 'backgroundColor', label: 'background_color', type: 'color', allowTransparent: true },
    ] },
    { group: 'spacing', fields: [PADDING_FIELD] },
  ],

  column: [
    { group: 'layout', fields: [
      { key: 'width', label: 'column_width', type: 'text', target: 'width' },
      { key: 'stackOnMobile', label: 'stack_on_mobile', type: 'toggle' },
    ] },
    { group: 'background', fields: [
      { key: 'backgroundColor', label: 'background_color', type: 'color', allowTransparent: true },
    ] },
    { group: 'spacing', fields: [PADDING_FIELD] },
    { group: 'border', fields: [
      { key: 'borderWidth', label: 'border_width', type: 'number', min: 0, max: 20, suffix: 'px' },
      { key: 'borderColor', label: 'border_color', type: 'color' },
      { key: 'borderRadius', label: 'corner_radius', type: 'number', min: 0, max: 100, suffix: 'px' },
    ] },
  ],
};

export const BLOCK_LIBRARY = [
  { type: 'text', icon: 'bx-text' },
  { type: 'heading', icon: 'bx-heading' },
  { type: 'image', icon: 'bx-image' },
  { type: 'button', icon: 'bx-purchase-tag-alt' },
  { type: 'link', icon: 'bx-link' },
  { type: 'divider', icon: 'bx-minus' },
  { type: 'spacer', icon: 'bx-move-vertical' },
  { type: 'social', icon: 'bx-share-alt' },
  { type: 'html', icon: 'bx-code-alt' },
];

export const LAYOUT_PRESETS = [
  { preset: 'oneColumn', icon: 'bx-rectangle' },
  { preset: 'twoColumn', icon: 'bx-columns' },
  { preset: 'threeColumn', icon: 'bx-grid-horizontal' },
  { preset: 'header', icon: 'bx-dock-top' },
  { preset: 'footer', icon: 'bx-dock-bottom' },
];
