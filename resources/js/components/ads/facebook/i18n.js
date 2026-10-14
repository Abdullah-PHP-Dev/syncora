// Translator for the Facebook campaign builder. The page passes
// __('ads_builder') (lang/{en,ar}/ads_builder.php) as JSON; t() reads a
// dotted key from it, replaces :params, and tc() picks the plural form
// using Laravel's "{1} one|[2,10] few|[11,*] many" syntax.
import { inject } from 'vue';

function lookup(messages, key) {
  return key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), messages);
}

function replace(text, params = {}) {
  return Object.keys(params)
    .sort((a, b) => b.length - a.length)
    .reduce((out, name) => out.split(':' + name).join(params[name] === null || params[name] === undefined ? '' : String(params[name])), text);
}

function choose(text, count) {
  const parts = text.split('|');
  for (const part of parts) {
    const exact = part.match(/^\s*\{(\d+)\}\s*/);
    if (exact) {
      if (Number(exact[1]) === count) return part.slice(exact[0].length);
      continue;
    }
    const range = part.match(/^\s*\[(\d+|\*),\s*(\d+|\*)\]\s*/);
    if (range) {
      const lo = range[1] === '*' ? -Infinity : Number(range[1]);
      const hi = range[2] === '*' ? Infinity : Number(range[2]);
      if (count >= lo && count <= hi) return part.slice(range[0].length);
    }
  }
  // No conditions matched: "one|many".
  const plain = parts.map((p) => p.replace(/^\s*(\{\d+\}|\[[^\]]*\])\s*/, ''));
  return count === 1 || plain.length === 1 ? plain[0] : plain[plain.length - 1];
}

export function createTranslator(messages = {}, locale = 'en') {
  const t = (key, params) => {
    const value = lookup(messages, key);
    return typeof value === 'string' ? replace(value, params) : key;
  };
  const tc = (key, count, params = {}) => {
    const value = lookup(messages, key);
    return typeof value === 'string' ? replace(choose(value, count), { count, ...params }) : key;
  };
  // Latin digits in Arabic too - prices and IDs stay readable/copyable.
  const intl = locale === 'ar' ? 'ar-u-nu-latn' : undefined;

  return { t, tc, locale, intl, dir: locale === 'ar' ? 'rtl' : 'ltr' };
}

// Child components (SearchPicker, IdentityCards, BudgetSummary) use the
// translator the builder provides; outside it, t() just returns the key.
export function useBuilderI18n() {
  return inject('fbI18n', createTranslator({}, 'en'));
}
