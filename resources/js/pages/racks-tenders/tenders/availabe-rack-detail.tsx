import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Head, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

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
    is_unavailable: boolean;
};

type RackLite = {
    rack_id: string;
    rack_name: string;
    rack_rows: number;
    rack_columns: number;
    vendor_location_name: string;
    owner_vendor_id: string;
};

type MyBid = {
    tender_compartment_id: string;
    compartment_id: string;
    bid_price: string;
    durations: number;
    tender_status: string;
    selected_at: string | null;
};

type Props = {
    rack: RackLite;
    auth: {
        role: string | null;
        current_vendor_id: string | null;
        is_owner: boolean;
    };
    compartments: CompartmentLite[];
    myBids: MyBid[];
};

export default function AvailableTenderDetail({
    rack,
    auth,
    compartments,
    myBids,
}: Props) {
    const [selectedCompartmentId, setSelectedCompartmentId] =
        useState<string>("");
    const [bidPrice, setBidPrice] = useState<string>("");
    const [durations, setDurations] = useState<string>("1");
    const [productDescription, setProductDescription] = useState<string>("");

    const myBidByCompartmentId = useMemo(() => {
        const map = new Map<string, MyBid>();
        for (const b of myBids) map.set(b.compartment_id, b);
        return map;
    }, [myBids]);

    const compartmentByPos = useMemo(() => {
        const map = new Map<string, CompartmentLite>();
        for (const c of compartments) {
            map.set(`${c.row_index}-${c.column_index}`, c);
        }
        return map;
    }, [compartments]);

    const canBid =
        (auth.role === "vendor" || auth.role === "admin") && !auth.is_owner;

    const onSelectCompartment = (c: CompartmentLite) => {
        setSelectedCompartmentId(c.compartment_id);
        const existing = myBidByCompartmentId.get(c.compartment_id);
        if (existing) {
            setBidPrice(existing.bid_price);
            setDurations(String(existing.durations));
        } else {
            setBidPrice(String(c.min_price ?? ""));
            setDurations(String(c.min_month ?? 1));
        }
    };

    const selectedCompartment = selectedCompartmentId
        ? (compartments.find(
              (c) => c.compartment_id === selectedCompartmentId,
          ) ?? null)
        : null;

    const handleSubmit = () => {
        if (!canBid) {
            toast.error("You are not allowed to bid for this tender.");
            return;
        }
        if (!selectedCompartmentId) {
            toast.error("Please select a compartment.");
            return;
        }
        const minPrice = selectedCompartment
            ? Number(selectedCompartment.min_price ?? 0)
            : 0;
        const minMonths = selectedCompartment
            ? Number(selectedCompartment.min_month ?? 1)
            : 1;
        const price = Number(bidPrice);
        const months = Number(durations);

        if (!Number.isFinite(price)) {
            toast.error("Please enter a valid bid price.");
            return;
        }
        if (price < minPrice) {
            toast.error(`Bid price must be at least RM${minPrice.toFixed(2)}.`);
            return;
        }
        if (!Number.isFinite(months) || months < minMonths) {
            toast.error(`Must be at least ${minMonths} months.`);
            return;
        }
        if (!productDescription) {
            toast.error("Please enter a product description.");
            return;
        }

        router.post(
            route("available-racks.bid", rack.rack_id),
            {
                compartment_id: selectedCompartmentId,
                bid_price: price,
                durations: months,
                product_description: productDescription,
            } as any,
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Bid submitted."),
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to submit bid.");
                    Object.values(errors).forEach((e) => toast.error(e));
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Available For Tender" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                    <div>
                        <h2 className="text-lg font-bold text-[#3730A3]">
                            Available For Tender
                        </h2>
                        <div className="text-sm text-gray-700">
                            {rack.vendor_location_name} - {rack.rack_name}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="default"
                            onClick={() =>
                                router.visit(route("available-racks.index"))
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
                                        const isAllocated =
                                            compartment.compartment_status ===
                                            "allocated";
                                        const isClosed =
                                            compartment.compartment_status ===
                                            "closed";
                                        const isUnavailable =
                                            compartment.is_unavailable;
                                        const isDisabled =
                                            !compartment.is_active ||
                                            isAllocated ||
                                            isUnavailable ||
                                            isClosed;

                                        return (
                                            <button
                                                key={compartment.compartment_id}
                                                type="button"
                                                disabled={isDisabled || !canBid}
                                                onClick={() =>
                                                    onSelectCompartment(
                                                        compartment,
                                                    )
                                                }
                                                className={[
                                                    "h-14 rounded-md border px-2 text-left text-xs",
                                                    isSelected
                                                        ? "border-[#3730A3] bg-[#3730A3]/10"
                                                        : "bg-white",
                                                    isDisabled || !canBid
                                                        ? "opacity-50 cursor-not-allowed"
                                                        : "hover:bg-gray-50",
                                                ].join(" ")}
                                            >
                                                <div className="font-semibold">
                                                    {compartment.label}
                                                </div>
                                                <div className="text-[10px] text-gray-500">
                                                    {isAllocated
                                                        ? "Allocated"
                                                        : isUnavailable
                                                          ? "Unavailable"
                                                          : isClosed
                                                            ? "Closed"
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
                        <div className="text-sm font-medium">Submit Bid</div>
                        <div className="mt-3 text-xs text-gray-600">
                            {selectedCompartment
                                ? `Selected: ${selectedCompartment.label}`
                                : "Select a compartment from the grid"}
                        </div>
                        {selectedCompartment ? (
                            <div className="mt-2 text-xs text-gray-600">
                                Min Price:{" "}
                                {Number(
                                    selectedCompartment.min_price ?? 0,
                                ).toFixed(2)}{" "}
                                · Min Months:{" "}
                                {selectedCompartment.min_month ?? 1}
                            </div>
                        ) : null}

                        <div className="mt-4 grid grid-cols-1 gap-3">
                            <div>
                                <div className="text-xs text-gray-600">
                                    Bid Price (RM / month)
                                </div>
                                <Input
                                    type="number"
                                    min={
                                        selectedCompartment
                                            ? Number(
                                                  selectedCompartment.min_price ??
                                                      0,
                                              )
                                            : 0
                                    }
                                    step="0.01"
                                    value={bidPrice}
                                    disabled={!canBid || !selectedCompartment}
                                    onChange={(e) =>
                                        setBidPrice(e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <div className="text-xs text-gray-600">
                                    No. of Months
                                </div>
                                <Input
                                    type="number"
                                    min={
                                        selectedCompartment
                                            ? Number(
                                                  selectedCompartment.min_month ??
                                                      1,
                                              )
                                            : 1
                                    }
                                    value={durations}
                                    disabled={!canBid || !selectedCompartment}
                                    onChange={(e) =>
                                        setDurations(e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <div className="text-xs text-gray-600">
                                    Product Description
                                </div>
                                <Input
                                    type="text"
                                    value={productDescription}
                                    disabled={!canBid || !selectedCompartment}
                                    onChange={(e) =>
                                        setProductDescription(e.target.value)
                                    }
                                />
                            </div>
                            <Button
                                variant="default"
                                type="button"
                                disabled={!canBid || !selectedCompartment}
                                onClick={handleSubmit}
                            >
                                Submit
                            </Button>
                        </div>

                        {!canBid ? (
                            <div className="mt-4 text-xs text-red-600">
                                {auth.is_owner
                                    ? "Rack owner cannot bid."
                                    : "Only vendors can bid."}
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
