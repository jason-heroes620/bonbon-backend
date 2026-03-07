import type { ApiResponse, PaginationMeta, TableOptions } from '@/types';
import type {
    ColumnDef,
    PaginationState,
    SortingState,
} from '@tanstack/react-table';
import axios from 'axios';
import { useEffect, useState } from 'react';

export function useTable<T>(
    columns: ColumnDef<T>[],
    endpoint: string,
    options: TableOptions = {},
) {
    const {
        showSearch = true,
        showFilters = true,
        showPagination = true,
        defaultPageSize = 10,
    } = options;

    const [data, setData] = useState<T[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [filters, setFilters] = useState<Record<string, any>>({});
    const [sorting, setSorting] = useState<SortingState>([]);
    const [pagination, setPagination] = useState<PaginationState>({
        pageIndex: 0,
        pageSize: defaultPageSize,
    });

    const fetchData = async () => {
        try {
            setLoading(true);

            const params: any = {
                page: pagination.pageIndex + 1,
                per_page: pagination.pageSize,
            };

            if (showSearch && search) {
                params.search = search;
            }

            if (showFilters && Object.keys(filters).length > 0) {
                params.filters = filters;
            }

            if (sorting.length > 0) {
                params.sort = {
                    field: sorting[0].id,
                    direction: sorting[0].desc ? 'desc' : 'asc',
                };
            }

            const response = await axios.get<ApiResponse<T>>(endpoint, {
                params,
            });
            setData(response.data.data);
            setMeta(response.data.meta);

            if (response.data.filters) {
                setFilters(response.data.filters);
            }
            setError(null);
        } catch (err) {
            setError('Failed to fetch data');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [search, filters, sorting, pagination]);

    return {
        columns,
        data,
        meta,
        loading,
        error,
        search,
        setSearch,
        filters,
        setFilters,
        sorting,
        setSorting,
        pagination,
        setPagination,
        showSearch,
        showFilters,
        showPagination,
        fetchData,
    };
}
