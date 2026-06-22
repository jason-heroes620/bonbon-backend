import AppLayout from "@/layouts/AppLayout";
import type { Compartment, Rack } from "@/types";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { ChevronLeft, Save } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

type CellValue = {
    compartment_id: string;
    min_price: string;
    min_month: number;
    size_dimensions: string;
    allocated: boolean;
};

function formatCurrencyMYR(value: number) {
    return new Intl.NumberFormat("en-MY", {
        style: "currency",
        currency: "MYR",
        maximumFractionDigits: 2,
    }).format(value);
}

export default function EditCompartments({
    rack,
    compartments,
}: {
    rack: Rack;
    compartments: Compartment[];
}) {
    const rows = Math.max(1, Number(rack.rack_rows ?? 1));
    const cols = Math.max(1, Number(rack.rack_columns ?? 1));

    const compartmentByPos = useMemo(() => {
        const map = new Map<string, Compartment>();
        for (const c of compartments) {
            map.set(`${c.row_index}-${c.column_index}`, c);
        }
        return map;
    }, [compartments]);

    const [cells, setCells] = useState<Record<string, CellValue>>(() => {
        const initial: Record<string, CellValue> = {};
        for (const c of compartments) {
            initial[c.compartment_id] = {
                compartment_id: c.compartment_id,
                min_price: String(c.min_price ?? "0"),
                min_month: Number(c.min_month ?? 6),
                size_dimensions: String(c.size_dimensions ?? ""),
                allocated: c.compartment_status === "allocated",
            };
        }
        return initial;
    });

    const setCell = (
        compartmentId: string,
        patch: Partial<Omit<CellValue, "compartment_id">>,
    ) => {
        setCells((prev) => ({
            ...prev,
            [compartmentId]: {
                ...(prev[compartmentId] ?? {
                    compartment_id: compartmentId,
                    min_price: "0",
                    min_month: 6,
                    size_dimensions: "",
                    allocated: false,
                }),
                ...patch,
            },
        }));
    };

    const handleSave = () => {
        const payload = Object.values(cells).map((c) => ({
            compartment_id: c.compartment_id,
            min_price: Number(c.min_price ?? 0),
            min_month: Number(c.min_month ?? 6),
            size_dimensions: c.size_dimensions ? c.size_dimensions : null,
            allocated: Boolean(c.allocated),
        }));

        router.post(
            route("racks.compartments.update", rack.rack_id),
            {
                compartments: payload,
            } as any,
            {
                onSuccess: () => toast.success("Compartments saved."),
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to save compartments.");
                    Object.values(errors).forEach((e) => toast.error(e));
                },
            },
        );
    };

    const total = useMemo(() => {
        let total = 0;
        let totalMonths = 0;
        let countedCompartments = 0;
        for (const c of compartments) {
            const cell = cells[c.compartment_id];
            if (!cell) continue;
            const price = Number(cell.min_price ?? 0);
            const minMonths = Number(cell.min_month ?? 6);

            if (!Number.isFinite(price)) continue;
            if (!Number.isFinite(minMonths) || minMonths <= 0) continue;

            countedCompartments += 1;
            totalMonths += minMonths;
            total += price * minMonths;
        }
        const meanMonths =
            countedCompartments > 0 ? totalMonths / countedCompartments : 0;
        const potentialMonthlyRevenue = meanMonths > 0 ? total / meanMonths : 0;
        return {
            total,
            meanMonths,
            potentialMonthlyRevenue,
        };
    }, [cells, compartments]);

    const applyAllDimensions = (value: string) => {
        setCells((prev) => {
            const next: Record<string, CellValue> = { ...prev };
            for (const c of compartments) {
                next[c.compartment_id] = {
                    ...(next[c.compartment_id] ?? {
                        compartment_id: c.compartment_id,
                        min_price: "0",
                        min_month: 1,
                        size_dimensions: "",
                        allocated: false,
                    }),
                    size_dimensions: value,
                };
            }
            return next;
        });
    };

    const gridTemplateColumns = `repeat(${cols}, minmax(260px, 1fr))`;

    return (
        <AppLayout>
            <Head title="Compartments" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("racks.index"))
                                }
                            >
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <div>
                                <h2 className="text-lg font-bold text-[#3730A3]">
                                    Compartments
                                </h2>
                                <div className="text-sm text-gray-700">
                                    {rack.rack_name} ({rows} × {cols})
                                </div>
                            </div>
                        </div>
                        <div>
                            <Button variant="default" onClick={handleSave}>
                                <Save className="mr-2" size={18} />
                                Save
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="mt-4 rounded-lg border bg-white p-4">
                    <div className="text-sm font-medium">
                        Set Dimensions for All Compartments
                    </div>
                    <div className="mt-2 flex flex-col gap-2 md:flex-row md:items-center">
                        <Input
                            placeholder="e.g. 2ft x 3ft"
                            onChange={(e) => applyAllDimensions(e.target.value)}
                        />
                        <div className="text-xs text-gray-500">
                            Updates all cells immediately
                        </div>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto">
                    <div className="grid gap-3" style={{ gridTemplateColumns }}>
                        {Array.from({ length: rows }).flatMap((_, rIdx) => {
                            const row = rIdx + 1;
                            return Array.from({ length: cols }).map(
                                (_, cIdx) => {
                                    const col = cIdx + 1;
                                    const compartment = compartmentByPos.get(
                                        `${row}-${col}`,
                                    );
                                    if (!compartment) {
                                        return (
                                            <div
                                                key={`${row}-${col}`}
                                                className="border rounded-md bg-gray-50 p-3"
                                            >
                                                <div className="text-sm font-semibold text-gray-700">
                                                    {row}-{col}
                                                </div>
                                                <div className="text-xs text-gray-500 mt-1">
                                                    Not available
                                                </div>
                                            </div>
                                        );
                                    }

                                    const cell =
                                        cells[compartment.compartment_id];
                                    return (
                                        <div
                                            key={compartment.compartment_id}
                                            className="border rounded-md bg-white p-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="text-sm font-semibold">
                                                    {compartment.label}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    R{row} C{col}
                                                </div>
                                            </div>

                                            <div className="mt-3 flex items-center gap-2">
                                                <Checkbox
                                                    checked={Boolean(
                                                        cell?.allocated ??
                                                        false,
                                                    )}
                                                    onCheckedChange={(v) =>
                                                        setCell(
                                                            compartment.compartment_id,
                                                            {
                                                                allocated:
                                                                    Boolean(v),
                                                            },
                                                        )
                                                    }
                                                />
                                                <div className="text-xs text-gray-700">
                                                    Allocated
                                                </div>
                                            </div>

                                            <div className="mt-3 grid grid-cols-2 gap-2">
                                                <div className="col-span-2">
                                                    <div className="text-xs text-gray-600">
                                                        Dimensions
                                                    </div>
                                                    <Input
                                                        value={
                                                            cell?.size_dimensions ??
                                                            ""
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                compartment.compartment_id,
                                                                {
                                                                    size_dimensions:
                                                                        e.target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                        placeholder="e.g. 2ft x 3ft"
                                                    />
                                                </div>
                                                <div>
                                                    <div className="text-xs text-gray-600">
                                                        Min Price
                                                    </div>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={
                                                            cell?.min_price ??
                                                            "0"
                                                        }
                                                        onChange={(e) =>
                                                            setCell(
                                                                compartment.compartment_id,
                                                                {
                                                                    min_price:
                                                                        e.target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div>
                                                    <div className="text-xs text-gray-600">
                                                        Min Month
                                                    </div>
                                                    <Input
                                                        type="number"
                                                        min={6}
                                                        value={String(
                                                            cell?.min_month ??
                                                                1,
                                                        )}
                                                        onChange={(e) =>
                                                            setCell(
                                                                compartment.compartment_id,
                                                                {
                                                                    min_month:
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value ??
                                                                                1,
                                                                        ) || 1,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                },
                            );
                        })}
                    </div>
                </div>

                <div className="mt-4 rounded-lg border bg-white p-4">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium">
                            Potential Amount (Min Price × Months × Compartments)
                        </div>
                        <div className="text-lg font-bold">
                            {formatCurrencyMYR(total.total)}
                        </div>
                    </div>
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium">
                            Potential Monthly Revenue
                        </div>
                        <div className="text-lg font-bold">
                            {formatCurrencyMYR(total.potentialMonthlyRevenue)}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
