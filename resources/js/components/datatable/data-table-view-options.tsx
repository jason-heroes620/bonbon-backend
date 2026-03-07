import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Table } from '@tanstack/react-table';
import { Columns2 } from 'lucide-react';

interface DataTableViewOptionsProps<TData> {
    table: Table<TData>;
}

export function DataTableViewOptions<TData>({
    table,
}: DataTableViewOptionsProps<TData>) {
    return (
        <div>
            <DropdownMenu>
                <DropdownMenuTrigger>
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto hidden h-8 lg:flex"
                    >
                        <Columns2 />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-[150px]">
                    {table
                        .getAllColumns()
                        .filter(
                            (column) =>
                                typeof column.accessorFn !== 'undefined' &&
                                column.getCanHide(),
                        )
                        .map((column) => {
                            return (
                                <DropdownMenuCheckboxItem
                                    key={column.id}
                                    className="capitalize"
                                    checked={column.getIsVisible()}
                                    onCheckedChange={(value) =>
                                        column.toggleVisibility(!!value)
                                    }
                                >
                                    {column.id}
                                </DropdownMenuCheckboxItem>
                            );
                        })}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
