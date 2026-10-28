import React, {useEffect, useMemo, useState} from 'react';
import styled from 'styled-components';

const UserContext = require('pim/user-context');

const FORM_URL = '/rest/commercetool/attribute-mapping';
const ATTRIBUTE_URL = '/rest/attribute/?options[limit]=1000';

const COMMON_HELP =
  'Akeneo attribute used for the product. Variants use this attribute when the variant column is empty.';
const VARIANT_HELP =
  'Optional. Akeneo attribute used for variants when the value differs from the common product field.';
const ADD_HELP = 'CommerceTool attribute code to add as another mapping row, for example weight or color.';
const IMAGE_HELP = 'Akeneo image attributes exported as CommerceTool product images.';

type FieldMapping = {
  commercetools_attribute: string;
  label: string;
  required: boolean;
  akeneo_attribute: string;
  variant_akeneo_attribute: string;
  custom: boolean;
};

type MappingDocument = {
  fields: FieldMapping[];
  images: string[];
};

type AkeneoAttribute = {
  code: string;
  label: string;
  type: string;
};

const headers = {
  Accept: 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
};

const AttributeMappingApp = () => {
  const [fields, setFields] = useState<FieldMapping[] | null>(null);
  const [images, setImages] = useState<string[]>([]);
  const [attributes, setAttributes] = useState<AkeneoAttribute[]>([]);
  const [extraCode, setExtraCode] = useState('');
  const [addError, setAddError] = useState('');
  const [saveError, setSaveError] = useState('');
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    const locale = catalogLocale();

    Promise.all([
      fetch(FORM_URL, {headers}).then(async response => {
        if (!response.ok) {
          throw new Error('mapping');
        }

        return readJson(response);
      }),
      fetch(ATTRIBUTE_URL, {headers}).then(readJson).catch(() => []),
    ])
      .then(([document, attributeList]) => {
        if (cancelled) {
          return;
        }
        const mapping = normalizeDocument(document);
        setFields(mapping.fields);
        setImages(mapping.images);
        setAttributes(normalizeAttributes(attributeList, locale));
        setLoading(false);
      })
      .catch(() => {
        if (!cancelled) {
          setSaveError('The attribute mapping could not be loaded.');
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const imageAttributes = useMemo(
    () => attributes.filter(attribute => attribute.type === 'pim_catalog_image'),
    [attributes]
  );
  const labelByCode = useMemo(() => {
    const labels = new Map<string, string>();
    attributes.forEach(attribute => labels.set(attribute.code, attribute.label));
    return labels;
  }, [attributes]);

  const updateField = (code: string, key: 'akeneo_attribute' | 'variant_akeneo_attribute', value: string) => {
    setSaved(false);
    setFields(current =>
      (current || []).map(field => (field.commercetools_attribute === code ? {...field, [key]: value} : field))
    );
  };

  const addField = () => {
    const code = extraCode.trim();
    if (!/^[A-Za-z][A-Za-z0-9_]{0,99}$/.test(code)) {
      setAddError('Enter a CommerceTool attribute code, starting with a letter.');
      return;
    }
    if (code.startsWith('image__')) {
      setAddError('This code is reserved.');
      return;
    }
    if ((fields || []).some(field => field.commercetools_attribute === code)) {
      setAddError('This CommerceTool field is already listed.');
      return;
    }

    setFields(current => [
      ...(current || []),
      {
        commercetools_attribute: code,
        label: humanize(code),
        required: false,
        akeneo_attribute: '',
        variant_akeneo_attribute: '',
        custom: true,
      },
    ]);
    setExtraCode('');
    setAddError('');
    setSaved(false);
  };

  const removeField = (code: string) => {
    setFields(current => (current || []).filter(field => field.commercetools_attribute !== code));
    setSaved(false);
  };

  const addImage = (code: string) => {
    if ('' === code || images.includes(code)) {
      return;
    }
    setImages(current => [...current, code]);
    setSaved(false);
  };

  const save = () => {
    if (null === fields || saving) {
      return;
    }
    setSaving(true);
    setSaveError('');
    fetch(FORM_URL, {
      method: 'POST',
      headers: {...headers, 'Content-Type': 'application/json'},
      body: JSON.stringify({
        fields: fields.map(field => ({
          commercetools_attribute: field.commercetools_attribute,
          akeneo_attribute: field.akeneo_attribute,
          variant_akeneo_attribute: field.variant_akeneo_attribute,
        })),
        images,
      }),
    })
      .then(async response => {
        const body = await readJson(response);
        if (!response.ok) {
          const errors = Array.isArray(body?.errors) ? body.errors.join(' ') : 'The attribute mapping could not be saved.';
          setSaveError(errors);
          setSaved(false);
          return;
        }
        const mapping = normalizeDocument(body);
        setFields(mapping.fields);
        setImages(mapping.images);
        setSaved(true);
      })
      .catch(() => setSaveError('The attribute mapping could not be saved.'))
      .finally(() => setSaving(false));
  };

  return (
    <Page>
      <Header>
        <div>
          <Crumb>
            COMMERCETOOL <span>/</span> ATTRIBUTE MAPPING
          </Crumb>
          <Title>Attribute Mapping</Title>
        </div>
        <SaveButton type="button" onClick={save} disabled={saving || loading || null === fields}>
          {saving ? 'SAVING' : 'SAVE'}
        </SaveButton>
      </Header>

      {'' !== saveError && <ErrorBanner>{saveError}</ErrorBanner>}
      {saved && <SuccessBanner>Attribute mapping successfully saved.</SuccessBanner>}

      {loading ? (
        <Loading>Loading...</Loading>
      ) : null === fields ? null : (
        <>
          <Grid>
            <Head>Field in CommerceTool</Head>
            <Head>Common product field in Akeneo</Head>
            <HeadSpacer />
            <Head>Variant product field in Akeneo (if different)</Head>
            <HeadSpacer />

            {fields.map(field => (
              <React.Fragment key={field.commercetools_attribute}>
                <FieldName>
                  <span>
                    {field.label} <Code>[{field.commercetools_attribute}]</Code>
                    {field.required && <Required title="Required in CommerceTool">*</Required>}
                  </span>
                  {field.custom && (
                    <Remove type="button" onClick={() => removeField(field.commercetools_attribute)}>
                      Remove
                    </Remove>
                  )}
                </FieldName>
                <AttributeSelect
                  value={field.akeneo_attribute}
                  attributes={attributes}
                  labelByCode={labelByCode}
                  ariaLabel={`${field.label} common product field`}
                  onChange={value => updateField(field.commercetools_attribute, 'akeneo_attribute', value)}
                />
                <Info text={COMMON_HELP} />
                <AttributeSelect
                  value={field.variant_akeneo_attribute}
                  attributes={attributes}
                  labelByCode={labelByCode}
                  ariaLabel={`${field.label} variant product field`}
                  onChange={value => updateField(field.commercetools_attribute, 'variant_akeneo_attribute', value)}
                />
                <Info text={VARIANT_HELP} />
              </React.Fragment>
            ))}
          </Grid>

          <Section>Map more standard attributes</Section>
          <AddRow>
            <AddField>
              <AddLabel>
                CommerceTool standard attribute code (required)
                <Info text={ADD_HELP} />
              </AddLabel>
              <TextInput
                value={extraCode}
                aria-label="CommerceTool standard attribute code"
                onChange={event => {
                  setExtraCode(event.target.value);
                  setAddError('');
                }}
                onKeyDown={event => {
                  if ('Enter' === event.key) {
                    event.preventDefault();
                    addField();
                  }
                }}
              />
              {'' !== addError && <InlineError>{addError}</InlineError>}
            </AddField>
            <AddButton type="button" onClick={addField}>
              Add field
            </AddButton>
          </AddRow>

          <Section>Image mappings</Section>
          <ImageRow>
            <ImageLabel>Attributes to be used as Images</ImageLabel>
            <ChipBox>
              {images.map(code => (
                <Chip key={code}>
                  <ChipRemove type="button" aria-label={`Remove ${labelByCode.get(code) || code}`} onClick={() => {
                    setImages(current => current.filter(image => image !== code));
                    setSaved(false);
                  }}>
                    ×
                  </ChipRemove>
                  {labelByCode.get(code) || code}
                </Chip>
              ))}
              <ImageSelect
                value=""
                aria-label="Add an image attribute"
                onChange={event => addImage(event.target.value)}
              >
                <option value="">Select Attribute</option>
                {imageAttributes
                  .filter(attribute => !images.includes(attribute.code))
                  .map(attribute => (
                    <option key={attribute.code} value={attribute.code}>
                      {attribute.label}
                    </option>
                  ))}
              </ImageSelect>
            </ChipBox>
            <Info text={IMAGE_HELP} />
            <Pill
              type="button"
              onClick={() => {
                setImages(imageAttributes.map(attribute => attribute.code));
                setSaved(false);
              }}
            >
              Select all
            </Pill>
            <Pill
              type="button"
              onClick={() => {
                setImages([]);
                setSaved(false);
              }}
            >
              Deselect all
            </Pill>
          </ImageRow>
        </>
      )}
    </Page>
  );
};

const AttributeSelect = ({
  value,
  attributes,
  labelByCode,
  ariaLabel,
  onChange,
}: {
  value: string;
  attributes: AkeneoAttribute[];
  labelByCode: Map<string, string>;
  ariaLabel: string;
  onChange: (value: string) => void;
}) => (
  <Select $empty={'' === value} aria-label={ariaLabel} value={value} onChange={event => onChange(event.target.value)}>
    <option value="">Select Attribute</option>
    {'' !== value && !labelByCode.has(value) && <option value={value}>{value}</option>}
    {attributes.map(attribute => (
      <option key={attribute.code} value={attribute.code}>
        {attribute.label}
      </option>
    ))}
  </Select>
);

const Info = ({text}: {text: string}) => (
  <InfoButton type="button" aria-label={text}>
    i<span>{text}</span>
  </InfoButton>
);

const catalogLocale = (): string => {
  try {
    return UserContext.get('catalogLocale') || 'en_US';
  } catch (error) {
    return 'en_US';
  }
};

const readJson = (response: Response) => response.json().catch(() => ({}));

const normalizeDocument = (document: any): MappingDocument => ({
  fields: Array.isArray(document?.fields)
    ? document.fields.map((field: any) => ({
        commercetools_attribute: String(field.commercetools_attribute || ''),
        label: String(field.label || field.commercetools_attribute || ''),
        required: Boolean(field.required),
        akeneo_attribute: String(field.akeneo_attribute || ''),
        variant_akeneo_attribute: String(field.variant_akeneo_attribute || ''),
        custom: Boolean(field.custom),
      }))
    : [],
  images: Array.isArray(document?.images) ? document.images.map((code: unknown) => String(code)) : [],
});

const normalizeAttributes = (payload: any, locale: string): AkeneoAttribute[] => {
  const list = Array.isArray(payload) ? payload : [];

  return list
    .filter((attribute: any) => attribute && attribute.code)
    .map((attribute: any) => ({
      code: String(attribute.code),
      label: attributeLabel(attribute, locale),
      type: String(attribute.type || ''),
    }))
    .sort((left: AkeneoAttribute, right: AkeneoAttribute) => left.label.localeCompare(right.label));
};

const attributeLabel = (attribute: any, locale: string): string => {
  const labels = attribute.labels || {};

  return labels[locale] || labels.en_US || Object.values(labels).find(value => typeof value === 'string' && value !== '') || attribute.code;
};

const humanize = (code: string): string => {
  const spaced = code.replace(/_/g, ' ').replace(/([a-z0-9])([A-Z])/g, '$1 $2');

  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
};

const Page = ({children}: {children: React.ReactNode}) => (
  <div className="AknDefault-contentWithColumn">
    <div className="AknDefault-contentWithBottom">
      <Main>{children}</Main>
    </div>
  </div>
);

const Main = styled.div.attrs({className: 'AknDefault-mainContent'})`
  padding: 28px 36px 64px;
  background: white;
  min-height: calc(100vh - 90px);
`;

const Header = styled.header`
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 28px;
`;

const Crumb = styled.div`
  color: #8b97a6;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;

  span {
    margin: 0 6px;
  }
`;

const Title = styled.h1`
  margin: 8px 0 0;
  color: #9b4dca;
  font-size: 32px;
  font-weight: 500;
  line-height: 1.2;
`;

const SaveButton = styled.button`
  border: 0;
  border-radius: 4px;
  background: #67b930;
  color: white;
  min-width: 92px;
  height: 38px;
  padding: 0 18px;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.04em;
  cursor: pointer;

  &:hover:enabled {
    background: #5aa828;
  }

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
`;

const Grid = styled.div`
  display: grid;
  grid-template-columns: minmax(230px, 1.05fr) minmax(190px, 1.35fr) 26px minmax(210px, 1.45fr) 26px;
  column-gap: 18px;
  row-gap: 16px;
  align-items: center;
`;

const Head = styled.div`
  padding: 0 0 12px;
  border-bottom: 1px solid #d9dee7;
  color: #67788a;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
`;

const HeadSpacer = styled.div`
  border-bottom: 1px solid #d9dee7;
  align-self: stretch;
`;

const FieldName = styled.div`
  color: #44566a;
  font-size: 15px;
  line-height: 1.4;
`;

const Code = styled.span`
  color: #8b97a6;
`;

const Required = styled.span`
  margin-left: 4px;
  color: #e2544a;
`;

const Remove = styled.button`
  display: block;
  margin-top: 2px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #8b97a6;
  font-size: 12px;
  cursor: pointer;

  &:hover {
    color: #e2544a;
  }
`;

const Select = styled.select<{$empty: boolean}>`
  width: 100%;
  height: 40px;
  padding: 0 36px 0 12px;
  border: 1px solid #d5dbe3;
  border-radius: 4px;
  background-color: white;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2398a2b3' d='M1.4 0 6 4.6 10.6 0 12 1.4 6 7.4 0 1.4z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  color: ${({$empty}) => ($empty ? '#98a2b3' : '#344054')};
  font-size: 14px;
  appearance: none;

  option {
    color: #344054;
  }
`;

const InfoButton = styled.button`
  position: relative;
  width: 18px;
  height: 18px;
  padding: 0;
  border: 1px solid #c5ced8;
  border-radius: 50%;
  background: white;
  color: #98a2b3;
  font-size: 12px;
  font-family: Georgia, serif;
  font-style: italic;
  line-height: 16px;
  cursor: help;

  span {
    display: none;
    position: absolute;
    z-index: 5;
    bottom: calc(100% + 8px);
    left: 50%;
    width: 220px;
    padding: 8px 10px;
    transform: translateX(-50%);
    border-radius: 4px;
    background: #344054;
    color: white;
    font-family: inherit;
    font-size: 12px;
    font-style: normal;
    font-weight: 400;
    line-height: 1.4;
    text-align: left;
  }

  &:hover span,
  &:focus span {
    display: block;
  }
`;

const Section = styled.h2`
  margin: 36px 0 18px;
  padding-bottom: 10px;
  border-bottom: 1px solid #d9dee7;
  color: #3d4d5f;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
`;

const AddRow = styled.div`
  display: flex;
  align-items: flex-end;
  gap: 16px;
`;

const AddField = styled.div`
  width: min(100%, 460px);
`;

const AddLabel = styled.label`
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  color: #67788a;
  font-size: 14px;
`;

const TextInput = styled.input`
  width: 100%;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #d5dbe3;
  border-radius: 2px;
  color: #344054;
  font-size: 14px;
`;

const AddButton = styled.button`
  height: 36px;
  padding: 0 18px;
  border: 0;
  border-radius: 18px;
  background: #8e99a4;
  color: white;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  cursor: pointer;

  &:hover {
    background: #7d8894;
  }
`;

const ImageRow = styled.div`
  display: grid;
  grid-template-columns: minmax(180px, 240px) minmax(260px, 460px) 26px auto auto;
  column-gap: 16px;
  align-items: center;
`;

const ImageLabel = styled.div`
  color: #67788a;
  font-size: 14px;
`;

const ChipBox = styled.div`
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 72px;
  padding: 8px;
  border: 1px solid #d5dbe3;
  background: white;
`;

const Chip = styled.span`
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 28px;
  padding: 0 8px;
  border: 1px solid #d5dbe3;
  color: #5c6b7e;
  font-size: 14px;
`;

const ChipRemove = styled.button`
  padding: 0;
  border: 0;
  background: transparent;
  color: #98a2b3;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
`;

const ImageSelect = styled.select`
  flex: 1 1 140px;
  min-width: 140px;
  height: 28px;
  border: 0;
  background: transparent;
  color: #98a2b3;
  font-size: 14px;
`;

const Pill = styled.button`
  height: 34px;
  padding: 0 16px;
  border: 0;
  border-radius: 17px;
  background: #8e99a4;
  color: white;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
  cursor: pointer;

  &:hover {
    background: #7d8894;
  }
`;

const ErrorBanner = styled.div`
  margin: -8px 0 18px;
  color: #c0392b;
  font-size: 14px;
`;

const SuccessBanner = styled.div`
  margin: -8px 0 18px;
  color: #3d8b1f;
  font-size: 14px;
`;

const InlineError = styled.div`
  margin-top: 6px;
  color: #c0392b;
  font-size: 13px;
`;

const Loading = styled.div`
  color: #67788a;
`;

export {AttributeMappingApp};
