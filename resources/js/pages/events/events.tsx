import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import type { Event } from "@/types";
import { Pencil, Plus } from "lucide-react";
import { router } from "@inertiajs/react";
import { format } from "date-fns";

export const columns: ColumnDef<Event>[] = [
    {
        accessorKey: "event_name",
        header: "Event",
        cell: ({ row }) => row.original.event_name,
    },
    {
        accessorKey: "event_start_date",
        header: "Start",
        cell: ({ row }) =>
            format(new Date(row.original.event_start_date), "PPP"),
    },
    {
        accessorKey: "event_end_date",
        header: "End",
        cell: ({ row }) => format(new Date(row.original.event_end_date), "PPP"),
    },
    {
        accessorKey: "event_start_time",
        header: "Time",
        cell: ({ row }) =>
            `${format(
                new Date(
                    row.original.event_start_date +
                        "T" +
                        row.original.event_start_time,
                ),
                "h:mm a",
            )} - ${format(
                new Date(
                    row.original.event_end_date +
                        "T" +
                        row.original.event_end_time,
                ),
                "h:mm a",
            )}`,
    },
    {
        accessorKey: "event_location",
        header: "Location",
        cell: ({ row }) => row.original.event_location,
    },
    {
        accessorKey: "is_published",
        header: "Published",
        cell: ({ row }) => (row.original.is_published ? "Yes" : "No"),
    },
    {
        accessorKey: "is_active",
        header: "Active",
        cell: ({ row }) => (row.original.is_active ? "Yes" : "No"),
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Button
                    size={"sm"}
                    variant="default"
                    onClick={() =>
                        router.visit(
                            route("events.edit", row.original.event_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Events = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Events
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("events.create"))
                                }
                            >
                                <Plus className="mr" size={20} />
                                Event
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/events/all"
                            options={{
                                showSearch: true,
                                showFilters: true,
                                showPagination: true,
                                defaultPageSize: 10,
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default Events;
