import {useRoute} from '@akeneo-pim-community/shared';
import {useQuery} from 'react-query';
import {apiFetch} from '../tools/apiFetch';

type CategorySearchResult = {
  id: number;
  code: string;
  label: string;
  path: string;
};

const useSearchCategories = (treeId: string, locale: string, search: string) => {
  const url = useRoute('pim_enrich_categorytree_search', {id: treeId});
  const trimmedSearch = search.trim();

  return useQuery<CategorySearchResult[]>(
    ['category-tree-search', treeId, locale, trimmedSearch],
    async () => {
      const params = new URLSearchParams({locale, search: trimmedSearch});

      return apiFetch<CategorySearchResult[]>(`${url}?${params.toString()}`, {method: 'GET'});
    },
    {
      enabled: trimmedSearch.length > 0,
      keepPreviousData: true,
    }
  );
};

export type {CategorySearchResult};
export {useSearchCategories};
