import React from 'react';
import {LocaleSelector, useTranslate, userContext} from '@akeneo-pim-community/shared';
import {
  Button,
  CloseIcon,
  DeleteIcon,
  Field,
  getColor,
  getFontSize,
  Helper,
  IconButton,
  TextInput,
} from 'akeneo-design-system';
import {createPortal} from 'react-dom';
import {useEffect, useMemo, useState} from 'react';
import {useQueryClient} from 'react-query';
import styled from 'styled-components';
import {useCatalogActivatedLocaleCodes} from '../../../hooks/useCatalogActivatedLocaleCodes';
import {useCatalogLocales} from '../../../hooks/useCatalogLocales';
import {useSaveStatus} from '../../../hooks/useSaveStatus';
import {useUpdateTemplateAttribute} from '../../../hooks/useUpdateTemplateAttribute';
import {Attribute, CategoryAttributeOption, getAttributeDefaultValue, getAttributeOptions} from '../../../models';
import {Status} from '../../providers/SaveStatusProvider';

type Props = {
  attribute: Attribute;
  onClose: () => void;
};

const CODE_PATTERN = /^[a-zA-Z0-9_]+$/;

const slugifyOptionCode = (label: string): string =>
  label
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');

const uniqueOptionCode = (label: string, existingCodes: string[]): string => {
  const base = slugifyOptionCode(label);
  if (base === '' || !CODE_PATTERN.test(base)) {
    return '';
  }
  if (!existingCodes.includes(base)) {
    return base;
  }

  let index = 2;
  while (existingCodes.includes(`${base}_${index}`)) {
    index += 1;
  }

  return `${base}_${index}`;
};

export const ManageAttributeOptions = ({attribute, onClose}: Props) => {
  const translate = useTranslate();
  const queryClient = useQueryClient();
  const mutation = useUpdateTemplateAttribute(attribute.template_uuid, attribute.uuid);
  const {handleStatusListChange} = useSaveStatus();
  const catalogLocales = useCatalogLocales();
  const activatedCatalogLocaleCodes = useCatalogActivatedLocaleCodes();
  const saveStatusId = `${attribute.uuid}_options`;

  const locales = useMemo(() => {
    if (!catalogLocales || !activatedCatalogLocaleCodes) {
      return [];
    }

    return catalogLocales
      .filter(locale => activatedCatalogLocaleCodes.includes(locale.code))
      .sort((left, right) => left.label.localeCompare(right.label));
  }, [catalogLocales, activatedCatalogLocaleCodes]);

  const catalogLocale = userContext.get('catalogLocale');
  const [localeCode, setLocaleCode] = useState<string>(catalogLocale);
  const [options, setOptions] = useState<CategoryAttributeOption[]>(() => getAttributeOptions(attribute));
  const [selectedCode, setSelectedCode] = useState<string | null>(
    () => getAttributeOptions(attribute)[0]?.code ?? null
  );
  const [draftLabel, setDraftLabel] = useState('');
  const [draftCode, setDraftCode] = useState('');
  const [codeEdited, setCodeEdited] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (locales.length === 0) {
      return;
    }
    if (!locales.some(locale => locale.code === localeCode)) {
      setLocaleCode(locales[0].code);
    }
  }, [locales, localeCode]);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [onClose]);

  const translationLocales = locales.filter(locale => locale.code !== localeCode);
  const selectedOption = options.find(option => option.code === selectedCode) ?? null;

  const handleDraftLabelChange = (nextLabel: string) => {
    setDraftLabel(nextLabel);
    setError(null);
    if (!codeEdited) {
      setDraftCode(
        uniqueOptionCode(
          nextLabel,
          options.map(option => option.code)
        )
      );
    }
  };

  const updateOptionLabel = (optionCode: string, locale: string, nextLabel: string) => {
    setOptions(currentOptions =>
      currentOptions.map(option =>
        option.code === optionCode ? {...option, labels: {...option.labels, [locale]: nextLabel}} : option
      )
    );
  };

  const removeOption = (optionCode: string) => {
    setOptions(currentOptions => currentOptions.filter(option => option.code !== optionCode));
    if (selectedCode === optionCode) {
      setSelectedCode(null);
    }
  };

  const commitDraft = (): CategoryAttributeOption[] | null => {
    const label = draftLabel.trim();
    const code = draftCode.trim();
    if (label === '' && code === '') {
      return options;
    }
    if (!CODE_PATTERN.test(code)) {
      setError(translate('akeneo.category.template.attribute.settings.select_options.invalid_code'));
      return null;
    }
    if (options.some(option => option.code === code)) {
      setError(translate('akeneo.category.template.attribute.settings.select_options.duplicate_code'));
      return null;
    }

    const nextOptions = [
      ...options,
      {
        code,
        labels: label === '' ? {} : {[localeCode]: label},
      },
    ];
    setOptions(nextOptions);
    setSelectedCode(code);
    setDraftLabel('');
    setDraftCode('');
    setCodeEdited(false);
    setError(null);

    return nextOptions;
  };

  const handleSave = async () => {
    const nextOptions = commitDraft();
    if (nextOptions === null) {
      return;
    }

    const payload: {options: CategoryAttributeOption[]; default_value?: string | null} = {
      options: nextOptions.map(option => {
        const labels: {[locale: string]: string} = {};
        Object.keys(option.labels).forEach(locale => {
          const label = option.labels[locale];
          if (label.trim() !== '') {
            labels[locale] = label;
          }
        });

        return {code: option.code, labels};
      }),
    };
    const defaultValue = getAttributeDefaultValue(attribute);
    if (defaultValue !== '' && !nextOptions.some(option => option.code === defaultValue)) {
      payload.default_value = null;
    }

    try {
      setIsSaving(true);
      setError(null);
      handleStatusListChange(saveStatusId, Status.SAVING);
      await mutation.mutateAsync(payload);
      await queryClient.invalidateQueries(['get-template', attribute.template_uuid]);
      handleStatusListChange(saveStatusId, Status.SAVED);
    } catch {
      handleStatusListChange(saveStatusId, Status.ERRORS);
      setError(translate('akeneo.category.template.add_attribute.error.failed'));
    } finally {
      setIsSaving(false);
    }
  };

  return createPortal(
    <Overlay
      role="dialog"
      aria-modal="true"
      aria-label={translate('akeneo.category.template.attribute.settings.select_options.page_title')}
    >
      <TopBar>
        <IconButton
          ghost="borderless"
          level="tertiary"
          icon={<CloseIcon />}
          title={translate('pim_common.close')}
          onClick={onClose}
        />
        <SaveButton level="primary" onClick={handleSave} disabled={isSaving}>
          {translate('pim_common.save')}
        </SaveButton>
      </TopBar>
      <Heading>
        <ContextLine>
          {translate('akeneo.category.template.attribute.settings.select_options.section')} / {attribute.code}
        </ContextLine>
        <PageTitle>{translate('akeneo.category.template.attribute.settings.select_options.page_title')}</PageTitle>
      </Heading>
      <Columns>
        <LeftPane>
          <LocaleRow>
            {locales.length > 0 && <LocaleSelector value={localeCode} values={locales} onChange={setLocaleCode} />}
          </LocaleRow>
          <OptionsTable>
            <thead>
              <tr>
                <th>{translate('pim_common.label')}</th>
                <th>{translate('akeneo.category.template.attribute.settings.select_options.code_required')}</th>
              </tr>
            </thead>
            <tbody>
              {options.map(option => {
                const isSelected = option.code === selectedCode;

                return (
                  <OptionRow
                    key={option.code}
                    className={isSelected ? 'is-selected' : undefined}
                    onClick={() => setSelectedCode(option.code)}
                  >
                    <td>
                      {isSelected ? (
                        <UnderlineInput
                          value={option.labels[localeCode] || ''}
                          aria-label={translate('pim_common.label')}
                          onChange={(event: React.ChangeEvent<HTMLInputElement>) =>
                            updateOptionLabel(option.code, localeCode, event.currentTarget.value)
                          }
                          onClick={(event: React.MouseEvent<HTMLInputElement>) => event.stopPropagation()}
                        />
                      ) : (
                        <LabelText>{option.labels[localeCode] || option.code}</LabelText>
                      )}
                    </td>
                    <td>
                      <CodeCell>
                        <span>{option.code}</span>
                        <DeleteButton
                          ghost="borderless"
                          level="danger"
                          icon={<DeleteIcon />}
                          title={translate('pim_common.delete')}
                          onClick={event => {
                            event.stopPropagation();
                            removeOption(option.code);
                          }}
                        />
                      </CodeCell>
                    </td>
                  </OptionRow>
                );
              })}
              <tr>
                <td>
                  <UnderlineInput
                    value={draftLabel}
                    placeholder={translate(
                      'akeneo.category.template.attribute.settings.select_options.new_option_placeholder'
                    )}
                    aria-label={translate('pim_common.label')}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) =>
                      handleDraftLabelChange(event.currentTarget.value)
                    }
                    onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
                      if (event.key === 'Enter') {
                        event.preventDefault();
                        commitDraft();
                      }
                    }}
                  />
                </td>
                <td>
                  <UnderlineInput
                    value={draftCode}
                    aria-label={translate('akeneo.category.template.attribute.settings.select_options.code_required')}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
                      setCodeEdited(true);
                      setDraftCode(event.currentTarget.value);
                      setError(null);
                    }}
                    onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
                      if (event.key === 'Enter') {
                        event.preventDefault();
                        commitDraft();
                      }
                    }}
                  />
                </td>
              </tr>
            </tbody>
          </OptionsTable>
          {error && <Helper level="error">{error}</Helper>}
        </LeftPane>
        <RightPane>
          <TranslationsTitle>{translate('pim_common.label_translations')}</TranslationsTitle>
          {translationLocales.map(locale => (
            <Field key={locale.code} label={locale.label} locale={locale.code}>
              <TextInput
                value={selectedOption?.labels[locale.code] || ''}
                readOnly={selectedOption === null}
                onChange={nextLabel => {
                  if (selectedOption) {
                    updateOptionLabel(selectedOption.code, locale.code, nextLabel);
                  }
                }}
              />
            </Field>
          ))}
        </RightPane>
      </Columns>
    </Overlay>,
    document.body
  );
};

const Overlay = styled.div`
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  flex-direction: column;
  background: ${getColor('white')};
`;

const TopBar = styled.div`
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 28px 0;
`;

const SaveButton = styled(Button)`
  text-transform: uppercase;
`;

const Heading = styled.div`
  text-align: center;
  padding: 0 80px 8px;
`;

const ContextLine = styled.div`
  color: ${getColor('brand', 100)};
  font-size: ${getFontSize('small')};
  letter-spacing: 0.4px;
  text-transform: uppercase;
`;

const PageTitle = styled.h1`
  margin: 6px 0 0;
  color: ${getColor('grey', 140)};
  font-size: 28px;
  font-weight: 400;
  line-height: 1.2;
`;

const Columns = styled.div`
  display: flex;
  flex: 1;
  min-height: 0;
`;

const LeftPane = styled.div`
  flex: 1.45;
  padding: 28px 48px 40px 64px;
  overflow: auto;
`;

const RightPane = styled.div`
  flex: 1;
  border-left: 1px solid ${getColor('brand', 40)};
  padding: 28px 64px 40px 40px;
  overflow: auto;

  & > * + * {
    margin-top: 10px;
  }
`;

const LocaleRow = styled.div`
  display: flex;
  justify-content: flex-end;
  min-height: 32px;
  margin-bottom: 8px;
`;

const OptionsTable = styled.table`
  width: 100%;
  border-collapse: collapse;

  th {
    padding: 12px 12px 12px 0;
    border-bottom: 1px solid ${getColor('grey', 80)};
    color: ${getColor('grey', 120)};
    font-size: ${getFontSize('default')};
    font-weight: 400;
    text-align: left;
  }

  td {
    padding: 8px 12px 8px 0;
    vertical-align: middle;
  }
`;

const OptionRow = styled.tr`
  cursor: pointer;

  &.is-selected {
    background: #f5f9fc;
  }
`;

const LabelText = styled.span`
  display: block;
  padding: 8px 0;
  color: ${getColor('grey', 140)};
`;

const UnderlineInput = styled.input`
  width: 100%;
  border: none;
  border-bottom: 1px solid ${getColor('grey', 80)};
  border-radius: 0;
  background: transparent;
  color: ${getColor('grey', 140)};
  font-size: ${getFontSize('default')};
  line-height: 34px;
  outline: none;

  &::placeholder {
    color: ${getColor('grey', 100)};
  }

  &:focus {
    border-bottom-color: ${getColor('brand', 100)};
  }
`;

const CodeCell = styled.div`
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: ${getColor('grey', 120)};
`;

const DeleteButton = styled(IconButton)`
  opacity: 0;

  tr:hover & {
    opacity: 1;
  }
`;

const TranslationsTitle = styled.div`
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid ${getColor('grey', 80)};
  color: ${getColor('grey', 140)};
  font-size: ${getFontSize('small')};
  letter-spacing: 0.4px;
  text-transform: uppercase;
`;
