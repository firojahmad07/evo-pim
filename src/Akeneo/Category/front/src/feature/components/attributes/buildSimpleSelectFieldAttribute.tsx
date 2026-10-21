import React from 'react';
import {Field, Helper, SelectInput} from 'akeneo-design-system';
import {useTranslate} from '@akeneo-pim-community/shared';
import {getAttributeOptions} from '../../models';
import {AttributeFieldBuilder, AttributeInputValue, AttributeFieldProps} from './types';
import {getLabelFromAttribute} from './templateAttributesFactory';
import {memoize} from 'lodash/fp';

const unMemoizedBuildSimpleSelectFieldAttribute: AttributeFieldBuilder<AttributeInputValue> = attribute => {
  const Component: React.FC<AttributeFieldProps<AttributeInputValue>> = ({
    channel,
    locale,
    value,
    onChange,
  }: AttributeFieldProps<AttributeInputValue>) => {
    const translate = useTranslate();
    const options = getAttributeOptions(attribute);
    const selectedValue = typeof value === 'string' && value !== '' ? value : null;

    return (
      <Field label={getLabelFromAttribute(attribute, locale)} channel={channel.label} locale={locale}>
        {options.length === 0 ? (
          <Helper level="info">
            {translate('akeneo.category.template.attribute.settings.select_options.empty')}
          </Helper>
        ) : (
          <SelectInput
            emptyResultLabel={translate('pim_common.no_result')}
            openLabel={translate('pim_common.open')}
            clearLabel={translate('pim_common.clear_value')}
            value={selectedValue}
            onChange={onChange}
            clearable={true}
          >
            {options.map(option => (
              <SelectInput.Option key={option.code} value={option.code} title={option.labels[locale] || option.code}>
                {option.labels[locale] || option.code}
              </SelectInput.Option>
            ))}
          </SelectInput>
        )}
      </Field>
    );
  };

  Component.displayName = 'SimpleSelectFieldAttribute';
  return Component;
};

export const buildSimpleSelectFieldAttribute = memoize(unMemoizedBuildSimpleSelectFieldAttribute);
