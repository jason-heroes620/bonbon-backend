import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Eye, Plus } from "lucide-react";
import { format } from "date-fns";

type LuckyDrawSessionRow = {
    id: number;
    session_name: string;
    session_status: string;
    winners_count: number;
    session_start_time: string;
    session_end_time: string;
};

export const columns: ColumnDef<LuckyDrawSessionRow>[] = [
    {
        accessorKey: "session_name",
        header: "Session",
        cell: ({ row }) => row.original.session_name,
    },
    {
        accessorKey: "session_status",
        header: "Status",
        cell: ({ row }) => row.original.session_status,
    },
    {
        accessorKey: "winners_count",
        header: "Winners",
        cell: ({ row }) => row.original.winners_count,
    },
    {
        accessorKey: "session_start_time",
        header: "Start",
        cell: ({ row }) => format(row.original.session_start_time, "PPP p"),
    },
    {
        accessorKey: "session_end_time",
        header: "End",
        cell: ({ row }) => format(row.original.session_end_time, "PPP p"),
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
                            route("lucky_draw.page", {
                                session_id: row.original.id,
                            }),
                        )
                    }
                >
                    <Eye />
                </Button>
            </div>
        ),
    },
];

const LuckyDrawSessions = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Lucky Draw Sessions
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(
                                        route("lucky_draw.sessions.create"),
                                    )
                                }
                            >
                                <Plus className="mr" size={20} />
                                Session
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/lucky-draw/sessions/all"
                            options={{
                                showSearch: true,
                                showFilters: false,
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

export default LuckyDrawSessions;
