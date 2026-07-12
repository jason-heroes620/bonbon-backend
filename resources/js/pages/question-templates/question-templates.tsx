import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import type { QuestionTemplate } from "@/types";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { router } from "@inertiajs/react";

export const columns: ColumnDef<QuestionTemplate>[] = [
    {
        accessorKey: "question_label",
        header: "Label",
        cell: ({ row }) => row.original.question_label,
    },
    {
        accessorKey: "question_type",
        header: "Type",
        cell: ({ row }) => row.original.question_type,
    },
    {
        accessorKey: "is_required_default",
        header: "Required",
        cell: ({ row }) => (row.original.is_required_default ? "Yes" : "No"),
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
                            route(
                                "question_templates.edit",
                                row.original.question_template_id,
                            ),
                        )
                    }
                >
                    <Pencil />
                </Button>
                <Button
                    size={"sm"}
                    variant="secondary"
                    onClick={() => {
                        const ok = window.confirm(
                            "Deactivate this question template?",
                        );
                        if (!ok) return;
                        router.delete(
                            route(
                                "question_templates.destroy",
                                row.original.question_template_id,
                            ),
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                >
                    <Trash2 />
                </Button>
            </div>
        ),
    },
];

const QuestionTemplates = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Questionnaire Templates
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(
                                        route("question_templates.create"),
                                    )
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Questionnaire Template
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/question-templates/all"
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

export default QuestionTemplates;
