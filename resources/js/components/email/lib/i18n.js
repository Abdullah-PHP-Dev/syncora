// Translator for the email template designer. _form.blade.php passes
// __('email_designer') (lang/{en,ar}/email_designer.php) as JSON;
// EmailTemplateDesigner.vue provides it and the designer/* children
// inject it - outside the designer, t() just returns the key.
import { inject } from 'vue';
import { createTranslator } from '../../ads/facebook/i18n';

export { createTranslator };

export function useEmailI18n() {
  return inject('emailI18n', createTranslator({}, 'en'));
}
