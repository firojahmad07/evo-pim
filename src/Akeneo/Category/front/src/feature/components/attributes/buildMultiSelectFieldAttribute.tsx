import React from 'react';
import {Field, Helper, MultiSelectInput} from 'akeneo-design-system';
import {useTranslate} from '@akeneo-pim-community/shared';
import {getAttributeOptions} from '../../models';
import {AttributeFieldBuilder, AttributeInputValue, AttributeFieldProps} from './types';
import {getLabelFromAttribute} from './templateAttributesFactory';
import {memoize} from 'lodash/fp';

const unMemoizedBuildMultiSelectFieldAttribute: AttributeFieldBuilder<AttributeInputValue> = attribute => {
  const Component: React.FC<AttributeFieldProps<AttributeInputValue>> = ({
    channel,
    locale,
    value,
    onChange,
  }: AttributeFieldProps<AttributeInputValue>) => {
    const translate = useTranslate();
    const options = getAttributeOptions(attribute);
    const selectedValues = Array.isArray(value) ? value : [];

    return (
      <Field label={getLabelFromAttribute(attribute, locale)} channel={channel.label} locale={locale}>
        {options.length === 0 ? (
          <Helper level="info">
            {translate('akeneo.category.template.attribute.settings.select_options.empty')}
          </Helper>
        ) : (
          <MultiSelectInput
            emptyResultLabel={translate('pim_common.no_result')}
            openLabel={translate('pim_common.open')}
            removeLabel={translate('pim_common.remove')}
            value={selectedValues}
            onChange={onChange}
          >
            {options.map(option => (
              <MultiSelectInput.Option key={option.code} value={option.code}>
                {option.labels[locale] || option.code}
              </MultiSelectInput.Option>
            ))}
          </MultiSelectInput>
        )}
      </Field>
    );
  };

  Component.displayName = 'MultiSelectFieldAttribute';
  return Component;
};

export const buildMultiSelectFieldAttribute = memoize(unMemoizedBuildMultiSelectFieldAttribute);
