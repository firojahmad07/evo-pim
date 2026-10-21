import {useRoute} from '@akeneo-pim-community/shared';
import {useMutation} from 'react-query';
import {ApiError, apiFetch} from '../tools/apiFetch';

type Data = {
  isRichTextArea?: boolean;
  labels?: {[locale: string]: string};
  options?: {code: string; labels: {[locale: string]: string}}[];
  default_value?: string | null;
};

type Error = {
  labels: {[localeCode: string]: string[]};
};

export const useUpdateTemplateAttribute = (templateUuid: string, attributeUuid: string) => {
  const url = useRoute('pim_category_template_rest_update_attribute', {
    templateUuid: templateUuid,
    attributeUuid: attributeUuid,
  });

  return useMutation<void, ApiError<Error>, Data>(data =>
    apiFetch(url, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  );
};
