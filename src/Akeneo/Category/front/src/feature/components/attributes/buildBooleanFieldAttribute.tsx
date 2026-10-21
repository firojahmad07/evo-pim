import React from 'react';
import {BooleanInput, Field} from 'akeneo-design-system';
import {useTranslate} from '@akeneo-pim-community/shared';
import {AttributeFieldBuilder, AttributeInputValue, AttributeFieldProps} from './types';
import {getLabelFromAttribute} from './templateAttributesFactory';
import {memoize} from 'lodash/fp';

const unMemoizedBuildBooleanFieldAttribute: AttributeFieldBuilder<AttributeInputValue> = attribute => {
  const Component: React.FC<AttributeFieldProps<AttributeInputValue>> = ({
    channel,
    locale,
    value,
    onChange,
  }: AttributeFieldProps<AttributeInputValue>) => {
    const translate = useTranslate();
    const booleanValue = typeof value === 'boolean' ? value : null;

    return (
      <Field label={getLabelFromAttribute(attribute, locale)} channel={channel.label} locale={locale}>
        <BooleanInput
          clearable={true}
          value={booleanValue}
          yesLabel={translate('pim_common.yes')}
          noLabel={translate('pim_common.no')}
          clearLabel={translate('pim_common.clear_value')}
          onChange={onChange}
        />
      </Field>
    );
  };

  Component.displayName = 'BooleanFieldAttribute';
  return Component;
};

export const buildBooleanFieldAttribute = memoize(unMemoizedBuildBooleanFieldAttribute);
