import React, {FC} from 'react';
import styled from 'styled-components';
import {Search, Table} from 'akeneo-design-system';
import {Locale, LocaleSelector, useTranslate} from '@akeneo-pim-community/shared';
import {useCatalogLocales} from '../../hooks/useCatalogLocales';
import {CategorySearchResult} from '../../hooks/useSearchCategories';
import {NoResults} from './NoResults';

type Props = {
  searchValue: string;
  onSearchChange: (searchValue: string) => void;
  locale: string;
  onLocaleChange: (locale: string) => void;
  results: CategorySearchResult[];
  isSearching: boolean;
  onSelectCategory: (categoryId: number) => void;
};

const CategoryTreeSearch: FC<Props> = ({
  searchValue,
  onSearchChange,
  locale,
  onLocaleChange,
  results,
  isSearching,
  onSelectCategory,
}) => {
  const translate = useTranslate();
  const catalogLocales = useCatalogLocales();
  const locales: Locale[] = (catalogLocales ?? []).map(({code, label, region, language}) => ({
    code,
    label,
    region,
    language,
  }));
  const hasSearch = searchValue.trim().length > 0;

  return (
    <>
      <StyledSearch
        searchValue={searchValue}
        onSearchChange={onSearchChange}
        placeholder={translate('pim_enrich.entity.category.content.tree.search_by_label_or_code')}
      >
        {locales.length > 0 && (
          <LocaleSelector
            value={locales.some(catalogLocale => catalogLocale.code === locale) ? locale : locales[0].code}
            values={locales}
            onChange={onLocaleChange}
          />
        )}
      </StyledSearch>
      {hasSearch && results.length === 0 && !isSearching && (
        <NoResults
          title={translate('pim_common.no_search_result')}
          subtitle={translate('pim_datagrid.no_results_subtitle')}
        />
      )}
      {hasSearch && results.length > 0 && (
        <Table>
          <Table.Header>
            <Table.HeaderCell>{translate('pim_enrich.entity.category.plural_label')}</Table.HeaderCell>
            <Table.HeaderCell>{translate('pim_enrich.entity.category.content.tree.columns.code')}</Table.HeaderCell>
            <Table.HeaderCell>{translate('pim_enrich.entity.category.content.tree.columns.path')}</Table.HeaderCell>
          </Table.Header>
          <Table.Body>
            {results.map(category => (
              <Table.Row key={category.id} onClick={() => onSelectCategory(category.id)}>
                <Table.Cell rowTitle>{category.label}</Table.Cell>
                <Table.Cell>[{category.code}]</Table.Cell>
                <Table.Cell>{category.path}</Table.Cell>
              </Table.Row>
            ))}
          </Table.Body>
        </Table>
      )}
    </>
  );
};

const StyledSearch = styled(Search)`
  margin: 10px 0 20px;
`;

export {CategoryTreeSearch};
