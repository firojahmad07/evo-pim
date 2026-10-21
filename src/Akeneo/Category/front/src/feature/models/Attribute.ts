import {LabelCollection} from '@akeneo-pim-community/shared';

export const CATEGORY_ATTRIBUTE_TYPE_TEXT = 'text';
export const CATEGORY_ATTRIBUTE_TYPE_AREA = 'textarea';
export const CATEGORY_ATTRIBUTE_TYPE_RICHTEXT = 'richtext';
export const CATEGORY_ATTRIBUTE_TYPE_IMAGE = 'image';
export const CATEGORY_ATTRIBUTE_TYPE_BOOLEAN = 'boolean';
export const CATEGORY_ATTRIBUTE_TYPE_DATETIME = 'datetime';
export const CATEGORY_ATTRIBUTE_TYPE_SIMPLE_SELECT = 'simple_select';
export const CATEGORY_ATTRIBUTE_TYPE_MULTI_SELECT = 'multi_select';

const TYPES = [
  CATEGORY_ATTRIBUTE_TYPE_TEXT,
  CATEGORY_ATTRIBUTE_TYPE_AREA,
  CATEGORY_ATTRIBUTE_TYPE_RICHTEXT,
  CATEGORY_ATTRIBUTE_TYPE_IMAGE,
  CATEGORY_ATTRIBUTE_TYPE_BOOLEAN,
  CATEGORY_ATTRIBUTE_TYPE_DATETIME,
  CATEGORY_ATTRIBUTE_TYPE_SIMPLE_SELECT,
  CATEGORY_ATTRIBUTE_TYPE_MULTI_SELECT,
] as const;

export type CategoryAttributeType = typeof TYPES[number];

export type CategoryAttributeOption = {
  code: string;
  labels: LabelCollection;
};

export type Attribute = {
  uuid: string;
  code: string;
  type: CategoryAttributeType;
  order: number;
  is_scopable: boolean;
  is_localizable: boolean;
  labels: LabelCollection;
  template_uuid: string;
  additional_properties?: {[name: string]: string};
};

export const getAttributeDefaultValue = (attribute: Attribute): string => {
  const defaultValue = attribute.additional_properties?.default_value;

  return typeof defaultValue === 'string' ? defaultValue : '';
};

export const getAttributeOptions = (attribute: Attribute): CategoryAttributeOption[] => {
  const rawOptions = attribute.additional_properties?.options;
  if (typeof rawOptions !== 'string' || rawOptions === '') {
    return [];
  }

  try {
    const parsed: unknown = JSON.parse(rawOptions);
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter((option): option is CategoryAttributeOption => {
      return (
        typeof option === 'object' &&
        option !== null &&
        typeof option.code === 'string' &&
        typeof option.labels === 'object' &&
        option.labels !== null
      );
    });
  } catch {
    return [];
  }
};
