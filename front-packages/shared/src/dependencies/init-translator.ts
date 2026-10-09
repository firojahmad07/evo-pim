// @ts-ignore
import Translator from '../../legacy/translator';
import {userContext} from './user-context';

const translationCacheBuster = (): string => {
  if (typeof document === 'undefined') {
    return '';
  }

  const script = document.querySelector('script[src*="main.min.js"]');
  const query = script?.getAttribute('src')?.split('?')[1];

  return query ? `?${query}` : '';
};

const initTranslator = {
  fetch: async () => {
    const locale = userContext.get('uiLocale');

    if (Translator.locale === locale) {
      return Promise.resolve();
    }

    const response = await fetch('js/translation/' + locale + '.js' + translationCacheBuster());
    const messages = await response.json();

    Translator.fromJSON(messages);

    return Promise.resolve();
  },
};

export {initTranslator};
