import {useTranslate, userContext} from '@akeneo-pim-community/shared';
import {Button, Field, SelectInput, useBooleanState} from 'akeneo-design-system';
import {useEffect, useState} from 'react';
import {useQueryClient} from 'react-query';
import {useSaveStatus} from '../../../hooks/useSaveStatus';
import {useUpdateTemplateAttribute} from '../../../hooks/useUpdateTemplateAttribute';
import {Attribute, getAttributeDefaultValue, getAttributeOptions} from '../../../models';
import {Status} from '../../providers/SaveStatusProvider';
import {ManageAttributeOptions} from './ManageAttributeOptions';

type Props = {
  attribute: Attribute;
};

export const AttributeOptionsEditor = ({attribute}: Props) => {
  const translate = useTranslate();
  const catalogLocale = userContext.get('catalogLocale');
  const queryClient = useQueryClient();
  const mutation = useUpdateTemplateAttribute(attribute.template_uuid, attribute.uuid);
  const {handleStatusListChange} = useSaveStatus();
  const saveStatusId = `${attribute.uuid}_default_value`;
  const [defaultValue, setDefaultValue] = useState(() => getAttributeDefaultValue(attribute));
  const [isManageOptionsOpen, openManageOptions, closeManageOptions] = useBooleanState(false);
  const options = getAttributeOptions(attribute);
  const selectedDefault = options.some(option => option.code === defaultValue) ? defaultValue : null;

  useEffect(() => {
    setDefaultValue(getAttributeDefaultValue(attribute));
  }, [attribute]);

  if (attribute.type !== 'simple_select' && attribute.type !== 'multi_select') {
    return null;
  }

  const handleDefaultValueChange = async (nextValue: string | null) => {
    setDefaultValue(nextValue ?? '');
    try {
      handleStatusListChange(saveStatusId, Status.SAVING);
      await mutation.mutateAsync({default_value: nextValue});
      await queryClient.invalidateQueries(['get-template', attribute.template_uuid]);
      handleStatusListChange(saveStatusId, Status.SAVED);
    } catch {
      handleStatusListChange(saveStatusId, Status.ERRORS);
    }
  };

  return (
    <>
      <Button ghost level="tertiary" onClick={openManageOptions}>
        {translate('akeneo.category.template.attribute.settings.select_options.manage_options')}
      </Button>
      <Field label={translate('akeneo.category.template.attribute.settings.select_options.default_value')}>
        <SelectInput
          emptyResultLabel={translate('pim_common.no_result')}
          openLabel={translate('pim_common.open')}
          clearLabel={translate('pim_common.clear_value')}
          value={selectedDefault}
          clearable={true}
          onChange={handleDefaultValueChange}
        >
          {options.map(option => (
            <SelectInput.Option
              key={option.code}
              value={option.code}
              title={option.labels[catalogLocale] || option.code}
            >
              {option.labels[catalogLocale] || option.code}
            </SelectInput.Option>
          ))}
        </SelectInput>
      </Field>
      {isManageOptionsOpen && <ManageAttributeOptions attribute={attribute} onClose={closeManageOptions} />}
    </>
  );
};
