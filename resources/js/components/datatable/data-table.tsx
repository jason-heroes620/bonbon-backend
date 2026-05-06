import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useTable } from "@/hooks/useTable";
import type { TableOptions } from "@/types";
import {
    type ColumnDef,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { X } from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";

type FilterConfig = {
    key: string;
    label: string;
    options: any[];
};
interface DataTableProps<T> {
    columns: ColumnDef<T>[];
    endpoint: string;
    options?: TableOptions;
    filterConfig?: FilterConfig[];
}

export function DataTable<T>({
    columns,
    endpoint,
    options = {},
    filterConfig = [],
}: DataTableProps<T>) {
    const {
        columns: tableColumns,
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
    } = useTable<T>(columns, endpoint, options);

    const table = useReactTable({
        data,
        columns: tableColumns,
        pageCount: meta?.last_page ?? -1,
        state: {
            sorting,
            pagination,
        },
        onSortingChange: setSorting,
        onPaginationChange: setPagination,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
    });

    const getPaginationPages = (pageCount: number, pageIndex: number) => {
        const maxButtons = 7;
        if (pageCount <= maxButtons) {
            return Array.from({ length: pageCount }, (_, i) => i);
        }

        const first = 0;
        const last = pageCount - 1;
        const pages: (number | "ellipsis")[] = [];

        pages.push(first);

        const siblings = 1;
        let start = Math.max(pageIndex - siblings, first + 1);
        let end = Math.min(pageIndex + siblings, last - 1);

        const remainingSlots = maxButtons - 2;
        const windowSize = end - start + 1;
        if (windowSize < remainingSlots) {
            const deficit = remainingSlots - windowSize;
            const shiftLeft = Math.min(deficit, start - (first + 1));
            start -= shiftLeft;
            const shiftRight = deficit - shiftLeft;
            end = Math.min(last - 1, end + shiftRight);
        }

        if (start > first + 1) {
            pages.push("ellipsis");
        }
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        if (end < last - 1) {
            pages.push("ellipsis");
        }

        pages.push(last);

        return pages;
    };

    return (
        <div className="space-y-4">
            {/* Search and Filters */}
            <div className="flex flex-wrap items-center gap-4">
                {showSearch && (
                    <div className="flex w-full flex-row items-center gap-2 md:w-auto">
                        <label htmlFor="search" className="sr-only">
                            Search
                        </label>
                        <Input
                            type="text"
                            id="search"
                            placeholder="Search..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="rounded-md border px-4 py-2"
                        />
                        <div>
                            {search.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setSearch("")}
                                >
                                    <X size={20} className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                {showFilters && (
                    <div className="flex flex-wrap gap-2">
                        {filterConfig.map((f: FilterConfig) => (
                            <div key={f.key}>
                                <Select
                                    value={
                                        Object.keys(filters).length !== 0
                                            ? filters[f.key] || filters[0]
                                            : f.options.length === 1
                                              ? f.options[0].id
                                              : ""
                                    }
                                    onValueChange={(value) =>
                                        setFilters({
                                            ...filters,
                                            [f.key]: value,
                                        })
                                    }
                                    required
                                >
                                    <SelectTrigger className="mt-1 flex w-full">
                                        <SelectValue
                                            placeholder={`Select ${f.label}`}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {f?.options.map((option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.value}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Table */}
            <div className="overflow-x-auto rounded-lg border">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        scope="col"
                                        className="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500"
                                        onClick={header.column.getToggleSortingHandler()}
                                    >
                                        <div className="flex cursor-pointer items-center">
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                            {{
                                                asc: " ↑",
                                                desc: " ↓",
                                            }[
                                                header.column.getIsSorted() as string
                                            ] ?? null}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white">
                        {loading ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-6 py-4 text-center"
                                >
                                    Loading...
                                </td>
                            </tr>
                        ) : error ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-6 py-4 text-center text-red-500"
                                >
                                    {error}
                                </td>
                            </tr>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <tr key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            className="px-6 py-3 text-sm whitespace-nowrap"
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {showPagination && meta && (
                <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div className="text-sm text-gray-700">
                        Showing <span className="font-medium">{meta.from}</span>{" "}
                        to <span className="font-medium">{meta.to}</span> of{" "}
                        <span className="font-medium">{meta.total}</span>{" "}
                        results
                    </div>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size={"sm"}
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            className="rounded-md border px-4 py-2 disabled:opacity-50"
                        >
                            Previous
                        </Button>
                        <div className="flex gap-1">
                            {getPaginationPages(
                                table.getPageCount(),
                                table.getState().pagination.pageIndex,
                            ).map((p, idx) =>
                                p === "ellipsis" ? (
                                    <div
                                        key={`ellipsis-${idx}`}
                                        className="px-2 py-2 text-gray-500 select-none"
                                    >
                                        ...
                                    </div>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        key={p}
                                        onClick={() => table.setPageIndex(p)}
                                        className={`rounded-md border bg-white px-4 py-2 hover:text-[#F06F40] ${
                                            table.getState().pagination
                                                .pageIndex === p
                                                ? "bg-[#F06F40] text-white opacity-80"
                                                : "text-gray-700"
                                        }`}
                                    >
                                        {p + 1}
                                    </Button>
                                ),
                            )}
                        </div>
                        <Button
                            variant="outline"
                            size={"sm"}
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            className="rounded-md border px-4 py-2 disabled:opacity-50"
                        >
                            Next
                        </Button>
                    </div>

                    <select
                        value={table.getState().pagination.pageSize}
                        onChange={(e) => {
                            table.setPageSize(Number(e.target.value));
                        }}
                        className="rounded-md border px-4 py-1 text-sm"
                    >
                        {[10, 20, 30, 40, 50].map((pageSize) => (
                            <option key={pageSize} value={pageSize}>
                                Show {pageSize}
                            </option>
                        ))}
                    </select>
                </div>
            )}
        </div>
    );
}
