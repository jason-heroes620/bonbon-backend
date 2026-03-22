import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { ColumnDef } from "@tanstack/react-table";
import { DataTable } from "@/components/datatable/data-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus, Send } from "lucide-react";
import { format } from "date-fns";
import { useState } from "react";
import { toast } from "sonner";

type NotificationRow = {
    notification_id: string;
    title: string;
    audience: "all_users" | "user";
    status: "draft" | "sent";
    sent_at?: string | null;
    created_at?: string | null;
};

const buildColumns = (onSent: () => void): ColumnDef<NotificationRow>[] => [
    {
        accessorKey: "title",
        header: "Title",
        cell: ({ row }) => row.original.title,
    },
    {
        accessorKey: "audience",
        header: "Audience",
        cell: ({ row }) =>
            row.original.audience === "all_users" ? "All Users" : "User",
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => (row.original.status === "sent" ? "Sent" : "Draft"),
    },
    {
        accessorKey: "sent_at",
        header: "Sent At",
        cell: ({ row }) =>
            row.original.sent_at
                ? format(new Date(row.original.sent_at), "PPP HH:mm")
                : "-",
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
                            route(
                                "notifications.edit",
                                row.original.notification_id,
                            ),
                        )
                    }
                >
                    <Pencil />
                </Button>
                {row.original.status !== "sent" ? (
                    <Button
                        size={"sm"}
                        variant="outline"
                        onClick={() =>
                            router.post(
                                route(
                                    "notifications.send",
                                    row.original.notification_id,
                                ),
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        toast.success(
                                            "Notification sent successfully.",
                                        );
                                        onSent();
                                    },
                                    onError: () => {
                                        toast.error(
                                            "Failed to send notification.",
                                        );
                                    },
                                },
                            )
                        }
                    >
                        <Send />
                    </Button>
                ) : null}
            </div>
        ),
    },
];

const Notifications = () => {
    const [tableKey, setTableKey] = useState(0);
    const columns = buildColumns(() => setTableKey((k) => k + 1));

    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Notifications
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("notifications.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Notification
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            key={tableKey}
                            columns={columns}
                            endpoint="/notifications/all"
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

export default Notifications;
