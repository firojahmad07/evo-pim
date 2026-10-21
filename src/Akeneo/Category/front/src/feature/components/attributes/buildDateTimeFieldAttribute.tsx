import React from 'react';
import {Field, AkeneoThemedProps} from 'akeneo-design-system';
import styled from 'styled-components';
import {AttributeFieldBuilder, AttributeInputValue, AttributeFieldProps} from './types';
import {getLabelFromAttribute} from './templateAttributesFactory';
import {memoize} from 'lodash/fp';

const DateTimeInput = styled.input`
  width: 100%;
  height: 40px;
  border: 1px solid ${({theme}: AkeneoThemedProps) => theme.color.grey80};
  border-radius: 2px;
  padding: 0 15px;
  font-size: 13px;
  color: ${({theme}: AkeneoThemedProps) => theme.color.grey120};
  box-sizing: border-box;
  background: ${({theme}: AkeneoThemedProps) => theme.color.white};

  &:focus {
    outline: none;
    border-color: ${({theme}: AkeneoThemedProps) => theme.color.blue100};
  }
`;

const toDateTimeLocalValue = (value: AttributeInputValue): string => {
  if (typeof value !== 'string' || value === '') {
    return '';
  }

  return value.includes(' ') ? value.replace(' ', 'T').slice(0, 16) : value.slice(0, 16);
};

const unMemoizedBuildDateTimeFieldAttribute: AttributeFieldBuilder<AttributeInputValue> = attribute => {
  const Component: React.FC<AttributeFieldProps<AttributeInputValue>> = ({
    channel,
    locale,
    value,
    onChange,
  }: AttributeFieldProps<AttributeInputValue>) => {
    return (
      <Field label={getLabelFromAttribute(attribute, locale)} channel={channel.label} locale={locale}>
        <DateTimeInput
          type="datetime-local"
          name={attribute.code}
          value={toDateTimeLocalValue(value)}
          onChange={(event: React.ChangeEvent<HTMLInputElement>) => onChange(event.currentTarget.value)}
        />
      </Field>
    );
  };

  Component.displayName = 'DateTimeFieldAttribute';
  return Component;
};

export const buildDateTimeFieldAttribute = memoize(unMemoizedBuildDateTimeFieldAttribute);
