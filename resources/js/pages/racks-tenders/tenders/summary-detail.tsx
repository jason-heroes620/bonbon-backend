import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Head, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { format } from "date-fns";

type CompartmentLite = {
    compartment_id: string;
    rack_id: string;
    label: string;
    row_index: number;
    column_index: number;
    min_price: string;
    min_month: number;
    compartment_status: "open" | "reviewing" | "allocated" | "closed";
    is_active: boolean;
};

type RackLite = {
    rack_id: string;
    rack_name: string;
    rack_rows: number;
    rack_columns: number;
    vendor_location_name: string;
};

type Bid = {
    tender_compartment_id: string;
    compartment_id: string;
    vendor_id: string | null;
    vendor_name: string | null;
    bid_price: string;
    durations: number;
    product_description: string | null;
    tender_status: string;
    selected_at: string | null;
    unallocated_at?: string | null;
    unallocated_by?: string | null;
    unallocated_reason?: string | null;
    tender_start_date: string;
    tender_end_date: string;
};

type Option = { value: string; label: string };

type Props = {
    rack: RackLite;
    compartments: CompartmentLite[];
    bids: Bid[];
    canSelect: boolean;
    isAdmin?: boolean;
    vendorOptions?: Option[];
};

export default function TenderSummaryDetail({
    rack,
    compartments,
    bids,
    canSelect,
    isAdmin = false,
    vendorOptions = [],
}: Props) {
    const [selectedCompartmentId, setSelectedCompartmentId] =
        useState<string>("");
    const [unallocateOpen, setUnallocateOpen] = useState(false);
    const [unallocateReason, setUnallocateReason] = useState("");
    const [unallocateBid, setUnallocateBid] = useState<Bid | null>(null);
    const [assignVendorIdByBidId, setAssignVendorIdByBidId] = useState<
        Record<string, string>
    >({});
    const [
        manualAllocateVendorByCompartmentId,
        setManualAllocateVendorByCompartmentId,
    ] = useState<Record<string, string>>({});

    const bidsByCompartmentId = useMemo(() => {
        const map = new Map<string, Bid[]>();
        for (const b of bids) {
            const list = map.get(b.compartment_id) ?? [];
            list.push(b);
            map.set(b.compartment_id, list);
        }
        for (const [k, list] of map.entries()) {
            list.sort((a, b) => Number(a.bid_price) - Number(b.bid_price));
            map.set(k, list);
        }
        return map;
    }, [bids]);

    const compartmentByPos = useMemo(() => {
        const map = new Map<string, CompartmentLite>();
        for (const c of compartments) {
            map.set(`${c.row_index}-${c.column_index}`, c);
        }
        return map;
    }, [compartments]);

    const selectedBids = selectedCompartmentId
        ? (bidsByCompartmentId.get(selectedCompartmentId) ?? [])
        : [];

    const compartmentById = useMemo(() => {
        const map = new Map<string, CompartmentLite>();
        for (const c of compartments) map.set(c.compartment_id, c);
        return map;
    }, [compartments]);

    const allocatedBids = useMemo(() => {
        return bids.filter((b) => b.tender_status === "selected");
    }, [bids]);

    const allocatedCompartmentsMissingBid = useMemo(() => {
        if (!isAdmin) return [];
        return compartments.filter((c) => {
            if (c.compartment_status !== "allocated") return false;
            const list = bidsByCompartmentId.get(c.compartment_id) ?? [];
            return list.length === 0;
        });
    }, [bidsByCompartmentId, compartments, isAdmin]);

    const activePaidBids = useMemo(() => {
        const now = new Date();
        return bids.filter((b) => {
            if (b.tender_status !== "paid" || !b.tender_end_date) return false;
            const endDate = new Date(b.tender_end_date);
            return !Number.isNaN(endDate.getTime()) && endDate > now;
        });
    }, [bids]);

    const expiredPaidBids = useMemo(() => {
        const now = new Date();
        return bids.filter((b) => {
            if (b.tender_status !== "paid" || !b.tender_end_date) return false;
            const endDate = new Date(b.tender_end_date);
            return !Number.isNaN(endDate.getTime()) && endDate <= now;
        });
    }, [bids]);

    const handleSelect = (bid: Bid) => {
        if (!canSelect) {
            toast.error("You are not allowed to allocate.");
            return;
        }

        if (confirm("Confirm to allocate this bid to the selected vendor?")) {
            router.post(
                route("tenders-summary.select", rack.rack_id),
                { tender_compartment_id: bid.tender_compartment_id } as any,
                {
                    preserveScroll: true,
                    onSuccess: () => toast.success("Allocated."),
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to allocate.");
                        Object.values(errors).forEach((e) => toast.error(e));
                    },
                },
            );
        }
    };

    const openUnallocate = (bid: Bid) => {
        if (bid.tender_status === "paid") {
            toast.error("Paid bids cannot be unallocated.");
            return;
        }
        setUnallocateBid(bid);
        setUnallocateReason("");
        setUnallocateOpen(true);
    };

    const submitUnallocate = () => {
        if (!unallocateBid) return;
        if (!canSelect) {
            toast.error("You are not allowed to unallocate.");
            return;
        }

        const reason = unallocateReason.trim();
        if (reason.length === 0) {
            toast.error("Unallocate reason is required.");
            return;
        }

        router.post(
            route("tenders-summary.unallocate", rack.rack_id),
            {
                tender_compartment_id: unallocateBid.tender_compartment_id,
                reason,
            } as any,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Unallocated.");
                    setUnallocateOpen(false);
                    setUnallocateBid(null);
                    setUnallocateReason("");
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to unallocate.");
                    Object.values(errors).forEach((e) => toast.error(e));
                },
            },
        );
    };

    const submitAssignVendor = (bid: Bid) => {
        const vendorId = assignVendorIdByBidId[bid.tender_compartment_id] ?? "";
        if (!vendorId) {
            toast.error("Please select a vendor.");
            return;
        }
        router.post(
            route("tenders-summary.assign-vendor", rack.rack_id),
            {
                tender_compartment_id: bid.tender_compartment_id,
                vendor_id: vendorId,
            } as any,
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Vendor assigned."),
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to assign vendor.");
                    Object.values(errors).forEach((e) => toast.error(e));
                },
            },
        );
    };

    const submitManualAllocate = (compartmentId: string) => {
        const vendorId =
            manualAllocateVendorByCompartmentId[compartmentId] ?? "";
        if (!vendorId) {
            toast.error("Please select a vendor.");
            return;
        }
        router.post(
            route("tenders-summary.manual-allocate", rack.rack_id),
            {
                compartment_id: compartmentId,
                vendor_id: vendorId,
            } as any,
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Allocation recorded."),
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to record allocation.");
                    Object.values(errors).forEach((e) => toast.error(e));
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Tender Summary" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                    <div>
                        <h2 className="text-lg font-bold text-[#3730A3]">
                            Tender Summary
                        </h2>
                        <div className="text-sm text-gray-700">
                            {rack.vendor_location_name} - {rack.rack_name}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="default"
                            onClick={() =>
                                router.visit(route("tenders-summary.index"))
                            }
                        >
                            Back
                        </Button>
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2 rounded-lg border bg-white p-4 overflow-x-auto">
                        <div
                            className="grid gap-2"
                            style={{
                                gridTemplateColumns: `repeat(${rack.rack_columns}, minmax(56px, 1fr))`,
                            }}
                        >
                            {Array.from({ length: rack.rack_rows }).flatMap(
                                (_, rIdx) => {
                                    const row = rIdx + 1;
                                    return Array.from({
                                        length: rack.rack_columns,
                                    }).map((_, cIdx) => {
                                        const col = cIdx + 1;
                                        const compartment =
                                            compartmentByPos.get(
                                                `${row}-${col}`,
                                            );

                                        if (!compartment) {
                                            return (
                                                <button
                                                    key={`${row}-${col}`}
                                                    type="button"
                                                    disabled
                                                    className="h-14 rounded-md border bg-gray-50 text-xs text-gray-400"
                                                >
                                                    -
                                                </button>
                                            );
                                        }

                                        const isSelected =
                                            selectedCompartmentId ===
                                            compartment.compartment_id;
                                        const hasBids =
                                            (bidsByCompartmentId.get(
                                                compartment.compartment_id,
                                            )?.length ?? 0) > 0;
                                        const hasPaidBid =
                                            (bidsByCompartmentId
                                                .get(compartment.compartment_id)
                                                ?.some(
                                                    (b) =>
                                                        b.tender_status ===
                                                        "paid",
                                                ) ?? false) === true;
                                        const isAllocated =
                                            compartment.compartment_status ===
                                                "allocated" || hasPaidBid;

                                        return (
                                            <button
                                                key={compartment.compartment_id}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedCompartmentId(
                                                        compartment.compartment_id,
                                                    )
                                                }
                                                className={[
                                                    "h-14 rounded-md border px-2 text-left text-xs",
                                                    isSelected
                                                        ? "border-[#3730A3] bg-[#3730A3]/10"
                                                        : "bg-white",
                                                    "hover:bg-gray-50",
                                                ].join(" ")}
                                            >
                                                <div className="font-semibold">
                                                    {compartment.label}
                                                </div>
                                                <div className="text-[10px] text-gray-500">
                                                    {isAllocated
                                                        ? "Allocated"
                                                        : hasBids
                                                          ? "Has bids"
                                                          : `R${row} C${col}`}
                                                </div>
                                            </button>
                                        );
                                    });
                                },
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white p-4">
                        <div className="text-sm font-medium">Bids</div>
                        <div className="mt-2 text-xs text-gray-600">
                            {selectedCompartmentId
                                ? `Selected compartment: ${
                                      compartments.find(
                                          (c) =>
                                              c.compartment_id ===
                                              selectedCompartmentId,
                                      )?.label ?? "-"
                                  }`
                                : "Select a compartment to view bids"}
                        </div>

                        <div className="mt-4 space-y-2">
                            {selectedCompartmentId &&
                            selectedBids.length === 0 ? (
                                <div className="text-sm text-gray-500">
                                    No bids yet.
                                </div>
                            ) : null}

                            {selectedBids.map((b) => {
                                const isSelected =
                                    b.tender_status === "selected" ||
                                    b.tender_status === "paid";
                                const isPaid = b.tender_status === "paid";
                                return (
                                    <div
                                        key={b.tender_compartment_id}
                                        className="rounded-md border p-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="text-sm font-semibold">
                                                {b.vendor_name}
                                            </div>
                                            <div className="text-xs text-gray-600">
                                                {isSelected
                                                    ? "Selected"
                                                    : b.tender_status}
                                            </div>
                                        </div>
                                        <div className="mt-1 text-sm">
                                            Per Month: RM
                                            {Number(b.bid_price).toFixed(
                                                2,
                                            )}{" "}
                                        </div>
                                        <div className="mt-1 text-sm">
                                            Duration: {b.durations} months
                                        </div>
                                        <div className="mt-1 text-sm">
                                            Product: {b.product_description}
                                        </div>
                                        <div className="mt-2 flex justify-end">
                                            <Button
                                                variant="default"
                                                type="button"
                                                disabled={
                                                    !canSelect || isSelected
                                                }
                                                onClick={() => handleSelect(b)}
                                            >
                                                {isPaid ? "Paid" : "Select"}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="mt-4 rounded-lg border bg-white p-4">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium">
                            Allocated Compartments
                        </div>
                    </div>

                    {allocatedBids.length === 0 ? (
                        <div className="mt-3 text-sm text-gray-500">
                            No allocated compartments yet.
                        </div>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Compartment
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Vendor
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Price (RM / month)
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Duration (months)
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Product
                                        </th>
                                        <th className="px-4 py-2 text-right text-xs font-bold tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {allocatedBids.map((b) => {
                                        const label =
                                            compartmentById.get(
                                                b.compartment_id,
                                            )?.label ?? "-";
                                        const isPaid =
                                            b.tender_status === "paid";
                                        const needsVendor =
                                            !b.vendor_id || b.vendor_id === "";
                                        return (
                                            <tr key={b.tender_compartment_id}>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {label}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {b.vendor_name ?? "-"}
                                                    {isAdmin && needsVendor ? (
                                                        <div className="mt-2 max-w-[260px]">
                                                            <Select
                                                                value={
                                                                    assignVendorIdByBidId[
                                                                        b
                                                                            .tender_compartment_id
                                                                    ] ?? ""
                                                                }
                                                                onValueChange={(
                                                                    v,
                                                                ) =>
                                                                    setAssignVendorIdByBidId(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [b.tender_compartment_id]:
                                                                                v,
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select vendor" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {vendorOptions.map(
                                                                        (
                                                                            opt,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    opt.value
                                                                                }
                                                                                value={
                                                                                    opt.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    opt.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <div className="mt-2 flex justify-end">
                                                                <Button
                                                                    variant="default"
                                                                    disabled={
                                                                        vendorOptions.length ===
                                                                        0
                                                                    }
                                                                    onClick={() =>
                                                                        submitAssignVendor(
                                                                            b,
                                                                        )
                                                                    }
                                                                >
                                                                    Assign
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {Number(
                                                        b.bid_price,
                                                    ).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {b.durations}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {b.product_description}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap text-right">
                                                    <Button
                                                        variant="destructive"
                                                        disabled={
                                                            !canSelect || isPaid
                                                        }
                                                        onClick={() =>
                                                            openUnallocate(b)
                                                        }
                                                    >
                                                        Unallocate
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {isAdmin && allocatedCompartmentsMissingBid.length > 0 ? (
                    <div className="mt-4 rounded-lg border bg-white p-4">
                        <div className="text-sm font-medium">
                            Allocated Compartments (Reserved)
                        </div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Compartment
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Price (RM / month)
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Duration (months)
                                        </th>
                                        <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                            Vendor
                                        </th>
                                        <th className="px-4 py-2 text-right text-xs font-bold tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {allocatedCompartmentsMissingBid.map(
                                        (c) => (
                                            <tr key={c.compartment_id}>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {c.label}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {Number(
                                                        c.min_price ?? 0,
                                                    ).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    {c.min_month ?? 1}
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                    <div className="max-w-[260px]">
                                                        <Select
                                                            value={
                                                                manualAllocateVendorByCompartmentId[
                                                                    c
                                                                        .compartment_id
                                                                ] ?? ""
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setManualAllocateVendorByCompartmentId(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        [c.compartment_id]:
                                                                            v,
                                                                    }),
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select vendor" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {vendorOptions.map(
                                                                    (opt) => (
                                                                        <SelectItem
                                                                            key={
                                                                                opt.value
                                                                            }
                                                                            value={
                                                                                opt.value
                                                                            }
                                                                        >
                                                                            {
                                                                                opt.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2 text-sm whitespace-nowrap text-right">
                                                    <Button
                                                        variant="default"
                                                        disabled={
                                                            vendorOptions.length ===
                                                            0
                                                        }
                                                        onClick={() =>
                                                            submitManualAllocate(
                                                                c.compartment_id,
                                                            )
                                                        }
                                                    >
                                                        Record
                                                    </Button>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                <div className="mt-4 rounded-lg border bg-white p-4">
                    <div className="text-sm font-medium">Paid Compartments</div>
                    <Tabs defaultValue="active" className="mt-4 w-full">
                        <TabsList className="w-full md:w-auto">
                            <TabsTrigger value="active">
                                Paid Active
                            </TabsTrigger>
                            <TabsTrigger value="history">
                                Paid History
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="active" className="mt-4">
                            {activePaidBids.length === 0 ? (
                                <div className="text-sm text-gray-500">
                                    No active paid compartments.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Compartment
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Vendor
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Price (RM / month)
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Duration (months)
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Product
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Start Date
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    End Date
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {activePaidBids.map((b) => (
                                                <tr
                                                    key={
                                                        b.tender_compartment_id
                                                    }
                                                >
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {compartmentById.get(
                                                            b.compartment_id,
                                                        )?.label ?? "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {b.vendor_name ?? "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {Number(
                                                            b.bid_price,
                                                        ).toFixed(2)}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {b.durations}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {b.product_description ??
                                                            "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {format(
                                                            b.tender_start_date,
                                                            "d MMM, y",
                                                        ) ?? "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {format(
                                                            b.tender_end_date,
                                                            "d MMM, y",
                                                        ) ?? "-"}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent value="history" className="mt-4">
                            {expiredPaidBids.length === 0 ? (
                                <div className="text-sm text-gray-500">
                                    No expired paid history.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Compartment
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Vendor
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Price (RM / month)
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Duration (months)
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Product
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    Start Date
                                                </th>
                                                <th className="px-4 py-2 text-left text-xs font-bold tracking-wider text-gray-500">
                                                    End Date
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {expiredPaidBids.map((b) => (
                                                <tr
                                                    key={
                                                        b.tender_compartment_id
                                                    }
                                                >
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {compartmentById.get(
                                                            b.compartment_id,
                                                        )?.label ?? "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {b.vendor_name ?? "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {Number(
                                                            b.bid_price,
                                                        ).toFixed(2)}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {b.durations}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {b.product_description ??
                                                            "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {format(
                                                            b.tender_start_date,
                                                            "d MMM, y",
                                                        ) ?? "-"}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm whitespace-nowrap">
                                                        {format(
                                                            b.tender_end_date,
                                                            "d MMM, y",
                                                        ) ?? "-"}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>
            </div>

            <Dialog open={unallocateOpen} onOpenChange={setUnallocateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Unallocate Compartment</DialogTitle>
                        <DialogDescription>
                            Provide a reason for unallocating this compartment.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <div className="text-sm font-medium">Reason</div>
                        <Textarea
                            value={unallocateReason}
                            onChange={(e) =>
                                setUnallocateReason(e.target.value)
                            }
                            placeholder="Enter unallocate reason..."
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => {
                                setUnallocateOpen(false);
                                setUnallocateBid(null);
                                setUnallocateReason("");
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            type="button"
                            onClick={submitUnallocate}
                        >
                            Unallocate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
