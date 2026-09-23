import { DEFAULT_FONT } from './fonts';

let counter = 0;

function uid(prefix) {
  counter += 1;
  return `${prefix}_${Date.now().toString(36)}${counter.toString(36)}`;
}

const PADDING = (t = 8, r = 0, b = 8, l = 0) => ({ top: t, right: r, bottom: b, left: l });

export function makeTextBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'text',
    content: 'Add your text here.',
    settings: {
      fontFamily: DEFAULT_FONT,
      fontSize: 15,
      fontWeight: 'normal',
      lineHeight: 1.5,
      letterSpacing: 0,
      color: '#4b4d5c',
      backgroundColor: 'transparent',
      align: 'left',
      padding: PADDING(),
      customClass: '',
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeHeadingBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'heading',
    content: 'Your Heading',
    settings: {
      level: 2,
      fontFamily: DEFAULT_FONT,
      fontSize: 24,
      fontWeight: 'bold',
      lineHeight: 1.3,
      letterSpacing: 0,
      color: '#1e1e2d',
      backgroundColor: 'transparent',
      align: 'left',
      padding: PADDING(12, 0, 12, 0),
      customClass: '',
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeImageBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'image',
    content: null,
    settings: {
      src: '',
      alt: '',
      link: '',
      newTab: true,
      width: 560,
      align: 'center',
      padding: PADDING(),
      borderWidth: 0,
      borderColor: '#e5e5ea',
      borderRadius: 0,
      customClass: '',
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeButtonBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'button',
    content: null,
    settings: {
      label: 'Shop Now',
      url: '',
      newTab: true,
      fontFamily: DEFAULT_FONT,
      fontSize: 16,
      fontWeight: 'bold',
      bg: '#7c5cff',
      color: '#ffffff',
      fullWidth: false,
      align: 'center',
      paddingY: 12,
      paddingX: 28,
      marginTop: 8,
      marginBottom: 8,
      radius: 6,
      customClass: '',
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeLinkBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'link',
    content: null,
    settings: {
      label: 'Learn more',
      url: '',
      newTab: true,
      fontFamily: DEFAULT_FONT,
      fontSize: 14,
      fontWeight: 'normal',
      underline: true,
      color: '#7c5cff',
      align: 'left',
      padding: PADDING(4, 0, 4, 0),
      customClass: '',
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeDividerBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'divider',
    content: null,
    settings: {
      style: 'solid',
      color: '#e5e5ea',
      thickness: 1,
      widthPercent: 100,
      paddingTop: 16,
      paddingBottom: 16,
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeSpacerBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'spacer',
    content: null,
    settings: {
      height: 24,
      collapseOnMobile: false,
      ...overrides,
    },
  };
}

export function makeSocialBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'social',
    content: null,
    settings: {
      accounts: [],
      iconSize: 28,
      gap: 10,
      align: 'center',
      padding: PADDING(12, 0, 12, 0),
      hideOnMobile: false,
      ...overrides,
    },
  };
}

export function makeHtmlBlock(overrides = {}) {
  return {
    id: uid('blk'),
    type: 'html',
    content: '<p>Custom HTML content...</p>',
    settings: {
      customClass: '',
      hideOnMobile: false,
      ...overrides,
    },
  };
}

const FACTORIES = {
  text: makeTextBlock,
  heading: makeHeadingBlock,
  image: makeImageBlock,
  button: makeButtonBlock,
  link: makeLinkBlock,
  divider: makeDividerBlock,
  spacer: makeSpacerBlock,
  social: makeSocialBlock,
  html: makeHtmlBlock,
};

export function makeBlock(type, overrides = {}) {
  const factory = FACTORIES[type];
  if (!factory) {
    throw new Error(`Unknown block type: ${type}`);
  }
  return factory(overrides);
}

export function cloneBlock(block) {
  const copy = JSON.parse(JSON.stringify(block));
  copy.id = uid('blk');
  return copy;
}

export function makeColumn(width = '100%', blocks = []) {
  return {
    id: uid('col'),
    width,
    settings: {
      backgroundColor: 'transparent',
      padding: PADDING(0, 0, 0, 0),
      borderWidth: 0,
      borderColor: '#e5e5ea',
      borderRadius: 0,
      stackOnMobile: true,
    },
    blocks,
  };
}

export function makeSection(columns) {
  return {
    id: uid('sec'),
    type: 'section',
    settings: {
      backgroundColor: '#ffffff',
      padding: PADDING(16, 16, 16, 16),
    },
    columns,
  };
}

/**
 * Block-panel presets - Header/Footer/1-2-3 Column are not distinct block
 * types, they insert a fully-formed section (see the schema design note
 * in EmailTemplateDesigner.vue). Keeping this list small and declarative
 * means the html generator's per-type switch only ever needs to handle
 * the 9 real leaf types plus section/column.
 */
export const SECTION_PRESETS = {
  oneColumn: () => makeSection([makeColumn('100%', [makeTextBlock()])]),
  twoColumn: () => makeSection([
    makeColumn('50%', [makeTextBlock()]),
    makeColumn('50%', [makeTextBlock()]),
  ]),
  threeColumn: () => makeSection([
    makeColumn('33.33%', [makeTextBlock({ fontSize: 13 })]),
    makeColumn('33.33%', [makeTextBlock({ fontSize: 13 })]),
    makeColumn('33.33%', [makeTextBlock({ fontSize: 13 })]),
  ]),
  header: () => makeSection([
    makeColumn('100%', [
      makeImageBlock({ width: 160, align: 'center' }),
      makeTextBlock({ align: 'center', content: 'Your tagline goes here' }),
    ]),
  ]),
  footer: () => makeSection([
    makeColumn('100%', [
      makeDividerBlock(),
      makeSocialBlock(),
      makeTextBlock({
        align: 'center',
        fontSize: 12,
        color: '#8b8d9c',
        content: 'Your Company Name, 123 Main St, City, Country<br>You are receiving this email because you subscribed to our list.',
      }),
    ]),
  ]),
};

export function emptySchema() {
  return {
    version: 1,
    settings: {
      emailWidth: 600,
      fontFamily: DEFAULT_FONT,
      backgroundColor: '#f5f5fa',
    },
    sections: [],
  };
}

/**
 * Fallback for a template with no schema_json yet - either one that
 * predates the block editor, or a restored legacy-code-editor version
 * (schema_json is null for both). Wraps the raw stored HTML in a single
 * Custom HTML block rather than erroring or discarding it, so existing
 * content is never lost - a seller can keep editing it as raw HTML, or
 * delete it and start fresh with real blocks. Also reused for
 * AI-generated content, which returns raw HTML the same way.
 */
export function wrapLegacyHtmlAsSchema(html) {
  const schema = emptySchema();
  schema.sections = [makeSection([makeColumn('100%', [makeHtmlBlock()])])];
  schema.sections[0].columns[0].blocks[0].content = html || '';
  return schema;
}
