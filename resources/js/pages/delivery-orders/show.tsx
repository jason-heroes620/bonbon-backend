import { useEffect, useMemo, useState } from "react";
import AppLayout from "@/layouts/AppLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { usePage, router } from "@inertiajs/react";
import { format } from "date-fns";
import {
    ArrowLeft,
    CalendarCheck,
    FileDown,
    MapPin,
    Package,
    Truck,
    UserCircle2,
    ClipboardList,
    Info,
    AlertCircle,
    CheckCircle2,
    Loader2,
} from "lucide-react";
import type { DeliveryOrderDetail, OrderItem, TrackingEvent } from "@/types";
import { toast } from "sonner";

const statusTone: Record<string, { className: string; label: string }> = {
    pending: {
        className: "bg-amber-500/15 text-amber-700 hover:bg-amber-500/15",
        label: "Pending",
    },
    prepared: {
        className: "bg-blue-500/15 text-blue-700 hover:bg-blue-500/15",
        label: "Prepared",
    },
    processing: {
        className: "bg-blue-500/15 text-blue-700 hover:bg-blue-500/15",
        label: "Processing",
    },
    shipped: {
        className: "bg-indigo-500/15 text-indigo-700 hover:bg-indigo-500/15",
        label: "Shipped",
    },
    completed: {
        className: "bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/15",
        label: "Completed",
    },
    refunded: {
        className: "bg-rose-500/15 text-rose-700 hover:bg-rose-500/15",
        label: "Refunded",
    },
};

const formatCurrency = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === "") {
        return "RM 0.00";
    }
    const n = typeof v === "number" ? v : Number(v);
    if (Number.isNaN(n)) {
        return "RM 0.00";
    }
    return `RM ${n.toFixed(2)}`;
};

const toD = (s: string | Date | null | undefined) => {
    if (!s) return null;
    try {
        return new Date(s);
    } catch {
        return null;
    }
};

const formatDateTime = (s: string | Date | null | undefined) => {
    const d = toD(s);
    return d ? format(d, "PPp") : "—";
};

const formatDate = (s: string | Date | null | undefined) => {
    const d = toD(s);
    return d ? format(d, "PPP") : "—";
};

type InfoPairProps = {
    label: string;
    value: React.ReactNode;
    className?: string;
};

const InfoPair = ({ label, value, className }: InfoPairProps) => (
    <div className={`flex flex-col gap-1 ${className ?? ""}`}>
        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">
            {label}
        </span>
        <span className="text-sm text-slate-900 break-words">{value}</span>
    </div>
);

const shippingAddressLines = (
    addr: DeliveryOrderDetail["shipping_address_json"],
): string[] => {
    if (!addr) return [];
    const lines: (string | null | undefined)[] = [];
    if (addr.name) lines.push(addr.name);
    if (addr.contact_no) lines.push(addr.contact_no);
    if (addr.line1 ?? addr.address_1) lines.push(addr.line1 ?? addr.address_1);
    if (addr.line2 ?? addr.address_2) lines.push(addr.line2 ?? addr.address_2);
    const cityLine = [addr.city, addr.state, addr.postcode]
        .filter((v): v is string => Boolean(v && v.length > 0))
        .join(" ");
    if (cityLine.trim().length > 0) lines.push(cityLine);
    if (addr.country) lines.push(addr.country);
    return lines
        .map((l) => (l ?? "").toString().trim())
        .filter((l) => l.length > 0);
};

const trackingIcon = (evt: TrackingEvent) => {
    const status = (evt.status ?? evt.code ?? "").toLowerCase();
    if (status.includes("deliver") || status.includes("complete")) {
        return <CheckCircle2 size={16} className="text-emerald-600" />;
    }
    if (
        status.includes("pickup") ||
        status.includes("transit") ||
        status.includes("arrive") ||
        status.includes("out")
    ) {
        return <Truck size={16} className="text-indigo-600" />;
    }
    if (
        status.includes("fail") ||
        status.includes("exception") ||
        status.includes("return")
    ) {
        return <AlertCircle size={16} className="text-rose-600" />;
    }
    if (
        status.includes("process") ||
        status.includes("packed") ||
        status.includes("ready")
    ) {
        return <Package size={16} className="text-blue-600" />;
    }
    return <Info size={16} className="text-slate-500" />;
};

type PageProps = {
    order: DeliveryOrderDetail;
    delyva_configured: boolean;
};

const DeliveryOrderShow = () => {
    const page = usePage();
    const order = (page.props as unknown as PageProps)
        .order as DeliveryOrderDetail;
    const delyvaConfigured = Boolean(
        (page.props as unknown as PageProps).delyva_configured,
    );

    const tone =
        statusTone[order.delivery_status as string] ?? statusTone.pending;

    const hasTracking = Boolean(
        order.delivery_tracking_no || order.delivery_order_no,
    );
    const [trackingLoading, setTrackingLoading] = useState(false);
    const [trackingError, setTrackingError] = useState<string | null>(null);
    const [tracking, setTracking] = useState<TrackingEvent[]>([]);

    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [originScheduledAt, setOriginScheduledAt] = useState<string>(() => {
        const d = new Date();
        d.setMinutes(0, 0, 0);
        d.setHours(d.getHours() + 2);
        const pad = (n: number) => String(n).padStart(2, "0");
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    });

    const loadTracking = () => {
        if (!hasTracking) return;
        setTrackingLoading(true);
        setTrackingError(null);
        const url = route("delivery-orders.tracking", order.order_id);
        fetch(url, {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
            credentials: "same-origin",
        })
            .then(async (res) => {
                if (!res.ok) {
                    const txt = await res.text().catch(() => "");
                    throw new Error(
                        `Failed (${res.status}): ${txt.length > 0 ? txt : res.statusText}`,
                    );
                }
                const json = (await res.json()) as {
                    events?: TrackingEvent[] | null;
                    error?: string | null;
                };
                if (json?.error) {
                    setTrackingError(json.error);
                }
                setTracking(Array.isArray(json?.events) ? json.events : []);
            })
            .catch((e: Error) => {
                setTrackingError(e.message ?? "Failed to fetch tracking");
            })
            .finally(() => setTrackingLoading(false));
    };

    useEffect(() => {
        if (hasTracking) {
            loadTracking();
        }
    }, [order.order_id, order.delivery_tracking_no, order.delivery_order_no]);

    const submitConfirm = () => {
        if (!originScheduledAt) return;
        setConfirming(true);
        router.post(
            route("delivery-orders.confirm", order.order_id),
            { originScheduledAt },
            {
                onFinish: () => {
                    setConfirming(false);
                },
                onSuccess: () => {
                    setConfirmOpen(false);
                },
            },
        );
    };

    const getConsignmentNo = async () => {
        if (!delyvaConfigured) return;
        const url = route(
            "delivery-orders.consignment-no",
            order.delivery_order_id,
        );
        router.post(
            url,
            {},
            {
                onFinish: () => {
                    //reload page
                    // window.location.reload();
                },
            },
        );
    };

    const printLabel = () => {
        const url = route("delivery-orders.label", order.order_id);
        window.open(url, "_blank", "noopener");
    };

    const customerName = useMemo(() => {
        if (!order.customer) return "—";
        const parts = [
            order.customer.first_name?.trim(),
            order.customer.last_name?.trim(),
        ].filter(Boolean);
        if (parts.length > 0) return parts.join(" ");
        return order.customer.email ?? "—";
    }, [order.customer]);

    const addressLines = shippingAddressLines(order.shipping_address_json);

    const itemsTotal = useMemo(() => {
        const rows = Array.isArray(order.order_items) ? order.order_items : [];
        return rows.reduce((acc: number, it: OrderItem) => {
            const n = Number(it.total_price ?? 0);
            return Number.isNaN(n) ? acc : acc + n;
        }, 0);
    }, [order.order_items]);

    return (
        <AppLayout>
            <div className="flex flex-col gap-4 px-4 py-2 w-full">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.visit(route("delivery-orders.index"))
                                }
                                className="px-2 text-slate-600 hover:text-slate-900"
                            >
                                <ArrowLeft size={16} className="mr-1" />
                                Back
                            </Button>
                        </div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-xl font-bold text-slate-900">
                                Delivery Order
                            </h2>
                            <span className="text-lg font-semibold text-[#3730A3]">
                                {order.order_no}
                            </span>
                            <Badge className={tone.className}>
                                {tone.label}
                            </Badge>
                        </div>
                        <p className="text-xs text-slate-500">
                            Created {formatDateTime(order.created_at)} · Last
                            updated {formatDateTime(order.updated_at)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            onClick={getConsignmentNo}
                            disabled={
                                order.delivery_tracking_no !== null ||
                                order.delivery_order_id === null
                            }
                        >
                            Get Consignment No
                        </Button>
                        <Dialog
                            open={confirmOpen}
                            onOpenChange={setConfirmOpen}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="default"
                                    disabled={
                                        !delyvaConfigured ||
                                        order.delivery_order_id !== null
                                    }
                                >
                                    <CalendarCheck size={16} className="mr-2" />
                                    Parcel Is Ready
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Confirm Delivery</DialogTitle>
                                    <DialogDescription>
                                        Choose the pickup datetime from the
                                        origin branch. This will schedule the
                                        courier and generate a tracking number.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-3 py-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="originScheduledAt">
                                            Origin Pickup Scheduled At
                                        </Label>
                                        <Input
                                            id="originScheduledAt"
                                            type="datetime-local"
                                            value={originScheduledAt}
                                            onChange={(e) =>
                                                setOriginScheduledAt(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <p className="text-xs text-slate-500">
                                            Delyva reference:{" "}
                                            <code className="text-[11px] bg-slate-100 px-1 rounded">
                                                {order.delivery_order_id ?? "—"}
                                            </code>
                                            {" / "}
                                            Service:{" "}
                                            <code className="text-[11px] bg-slate-100 px-1 rounded">
                                                {order.shipping_service_code ??
                                                    order.shipping_service_name ??
                                                    "—"}
                                            </code>
                                        </p>
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        variant="ghost"
                                        onClick={() => setConfirmOpen(false)}
                                        disabled={confirming}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        variant="default"
                                        onClick={submitConfirm}
                                        disabled={
                                            confirming || !originScheduledAt
                                        }
                                    >
                                        {confirming ? (
                                            <>
                                                <Loader2
                                                    size={16}
                                                    className="mr-2 animate-spin"
                                                />
                                                Submitting…
                                            </>
                                        ) : (
                                            <>Confirm & Schedule</>
                                        )}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                        <Button
                            variant="secondary"
                            onClick={printLabel}
                            disabled={
                                !delyvaConfigured || !order.delivery_order_id
                            }
                        >
                            <FileDown size={16} className="mr-2" />
                            Print Label
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-900">
                                <ClipboardList
                                    size={18}
                                    className="text-[#3730A3]"
                                />
                                Order Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <InfoPair label="Order No" value={order.order_no} />
                            <InfoPair
                                label="Order Date"
                                value={formatDate(order.order_date)}
                            />
                            <InfoPair
                                label="Status"
                                value={
                                    <Badge className={tone.className}>
                                        {tone.label}
                                    </Badge>
                                }
                            />
                            <InfoPair
                                label="Description"
                                value={order.order_description ?? "—"}
                            />
                            <InfoPair
                                label="Courier Service"
                                value={
                                    <div className="flex flex-col gap-0.5">
                                        <span className="font-medium text-slate-900">
                                            {order.shipping_service_name ??
                                                order.shipping_provider ??
                                                "—"}
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            {order.shipping_service_code ?? "—"}
                                        </span>
                                    </div>
                                }
                            />
                            <InfoPair
                                label="Shipping Fee"
                                value={formatCurrency(
                                    order.shipping_fee ?? order.total_charges,
                                )}
                            />
                            <InfoPair
                                label="Subtotal"
                                value={formatCurrency(order.total_price)}
                            />
                            <InfoPair
                                label="Discount"
                                value={formatCurrency(order.total_discount)}
                            />
                            <InfoPair
                                label="Voucher Discount"
                                value={
                                    <div className="flex flex-col gap-0.5">
                                        <span>
                                            {formatCurrency(
                                                order.applied_voucher_discount ??
                                                    0,
                                            )}
                                        </span>
                                        {order.discount_code ? (
                                            <span className="text-xs text-slate-500">
                                                Code: {order.discount_code}
                                            </span>
                                        ) : null}
                                    </div>
                                }
                            />
                            <InfoPair
                                label="Wallet Credit Used"
                                value={formatCurrency(
                                    order.wallet_credit_used ?? 0,
                                )}
                            />
                            <InfoPair
                                label="Total Payment"
                                value={
                                    <span className="font-semibold text-lg text-slate-900">
                                        {formatCurrency(order.total_payment)}
                                    </span>
                                }
                                className="sm:col-span-2"
                            />
                            <Separator className="col-span-full my-1" />
                            {/* <InfoPair
                                label="Delyva Order ID"
                                value={order.delivery_order_id ?? "—"}
                            /> */}
                            <InfoPair
                                label="Delivery Order No"
                                value={order.delivery_order_no ?? "—"}
                            />
                            <InfoPair
                                label="Tracking No"
                                value={
                                    order.delivery_tracking_no ? (
                                        <span className="font-mono text-sm bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5 inline-block">
                                            {order.delivery_tracking_no}
                                        </span>
                                    ) : (
                                        <Badge className="bg-amber-500/15 text-amber-700 hover:bg-amber-500/15">
                                            Pending Confirmation
                                        </Badge>
                                    )
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-900">
                                <UserCircle2
                                    size={18}
                                    className="text-[#3730A3]"
                                />
                                Customer Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <InfoPair
                                label="Name"
                                value={customerName}
                                className="sm:col-span-2"
                            />
                            <InfoPair
                                label="Email"
                                value={order.customer?.email ?? "—"}
                            />
                            <InfoPair
                                label="Contact No"
                                value={order.customer?.contact_no ?? "—"}
                            />
                            {/* <InfoPair
                                label="User ID"
                                value={order.customer?.user_id ?? "—"}
                                className="sm:col-span-2"
                            /> */}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-900">
                                <MapPin size={18} className="text-[#3730A3]" />
                                Shipping Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <InfoPair
                                label="Delivery From"
                                value={
                                    <div className="flex flex-col gap-0.5">
                                        <span className="font-medium text-slate-900">
                                            {order.fulfillment_branch
                                                ?.location_name ??
                                                "Branch " +
                                                    (order.fulfillment_vendor_location_id ??
                                                        "—")}
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            {order.fulfillment_branch
                                                ?.vendor_name ?? "—"}
                                        </span>
                                        {order.fulfillment_branch?.address ? (
                                            <span className="text-xs text-slate-600">
                                                {
                                                    order.fulfillment_branch
                                                        .address
                                                }
                                            </span>
                                        ) : null}
                                        {order.fulfillment_branch
                                            ?.contact_no ? (
                                            <span className="text-xs text-slate-500">
                                                {
                                                    order.fulfillment_branch
                                                        .contact_no
                                                }
                                            </span>
                                        ) : null}
                                    </div>
                                }
                                className="sm:col-span-2"
                            />
                            {/* <InfoPair
                                label="Shipping Method"
                                value={order.shipping_method ?? "delivery"}
                            />
                            <InfoPair
                                label="Fulfillment Type"
                                value={order.shipping_method ?? "delivery"}
                            /> */}
                            <Separator className="col-span-full my-1" />
                            <InfoPair
                                label="Delivery To"
                                value={
                                    addressLines.length > 0 ? (
                                        <div className="flex flex-col gap-0.5">
                                            {addressLines.map((line, i) => (
                                                <span
                                                    key={i}
                                                    className="text-sm text-slate-900"
                                                >
                                                    {line}
                                                </span>
                                            ))}
                                        </div>
                                    ) : (
                                        <span className="text-xs text-slate-500">
                                            No address payload saved.
                                        </span>
                                    )
                                }
                                className="sm:col-span-2"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-900">
                                <Package size={18} className="text-[#3730A3]" />
                                Order Items
                                <span className="ml-auto text-xs font-normal text-slate-500">
                                    Items total: {formatCurrency(itemsTotal)}
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-hidden rounded-b-lg border-t border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Item
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Qty
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Unit
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {Array.isArray(order.order_items) &&
                                        order.order_items.length > 0 ? (
                                            order.order_items.map((it) => (
                                                <tr key={it.order_item_id}>
                                                    <td className="px-3 py-2 align-top">
                                                        <div className="flex flex-col gap-0.5">
                                                            <span className="font-medium text-slate-900">
                                                                {it.line_name ??
                                                                    it.product
                                                                        ?.product_name ??
                                                                    "Item"}
                                                            </span>
                                                            {it.product
                                                                ?.product_sku ? (
                                                                <span className="text-xs text-slate-500">
                                                                    SKU:{" "}
                                                                    {
                                                                        it
                                                                            .product
                                                                            .product_sku
                                                                    }
                                                                </span>
                                                            ) : null}
                                                            {it.uom &&
                                                            it.uom !==
                                                                "unit" ? (
                                                                <span className="text-xs text-slate-500">
                                                                    UOM:{" "}
                                                                    {it.uom}
                                                                </span>
                                                            ) : null}
                                                            {Number(
                                                                it.discount ??
                                                                    0,
                                                            ) > 0 ? (
                                                                <span className="text-xs text-emerald-700">
                                                                    Discount:{" "}
                                                                    {formatCurrency(
                                                                        it.discount,
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 align-top text-slate-900">
                                                        {it.quantity}
                                                    </td>
                                                    <td className="px-3 py-2 align-top text-right tabular-nums text-slate-700">
                                                        {formatCurrency(
                                                            it.unit_price,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 align-top text-right tabular-nums font-medium text-slate-900">
                                                        {formatCurrency(
                                                            it.total_price,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="px-3 py-6 text-center text-sm text-slate-500"
                                                >
                                                    No items for this order.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="pb-2 flex flex-row items-center justify-between">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold text-slate-900">
                            <Truck size={18} className="text-[#3730A3]" />
                            Tracking History
                            {order.delivery_tracking_no ? (
                                <span className="text-xs font-normal text-slate-500 font-mono bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5">
                                    {order.delivery_tracking_no}
                                </span>
                            ) : order.delivery_order_no ? (
                                <span className="text-xs font-normal text-slate-500 font-mono bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5">
                                    {order.delivery_order_no}
                                </span>
                            ) : null}
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={loadTracking}
                                disabled={!hasTracking || trackingLoading}
                            >
                                {trackingLoading ? (
                                    <Loader2
                                        size={14}
                                        className="mr-2 animate-spin"
                                    />
                                ) : null}
                                Refresh
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!hasTracking ? (
                            <div className="border border-amber-200 bg-amber-50 text-amber-800 rounded-md px-3 py-2 text-sm">
                                No tracking number yet. Click on Parcel Is Ready
                                to schedule pickup and generate a tracking
                                reference.
                            </div>
                        ) : trackingLoading && tracking.length === 0 ? (
                            <div className="flex flex-col gap-2 py-2">
                                <Skeleton className="h-4 w-2/3" />
                                <Skeleton className="h-4 w-1/2" />
                                <Skeleton className="h-4 w-3/4" />
                            </div>
                        ) : trackingError && tracking.length === 0 ? (
                            <div className="border border-rose-200 bg-rose-50 text-rose-800 rounded-md px-3 py-2 text-sm">
                                Unable to load tracking: {trackingError}
                            </div>
                        ) : tracking.length === 0 ? (
                            <div className="border border-slate-200 bg-slate-50 text-slate-600 rounded-md px-3 py-2 text-sm">
                                No tracking events available yet.
                            </div>
                        ) : (
                            <ol className="relative border-l border-slate-200 ml-2">
                                {tracking.map((evt) => (
                                    <li
                                        key={`${evt.index}-${evt.occurred_at ?? "x"}`}
                                        className="mb-4 ml-5 last:mb-0"
                                    >
                                        <span className="absolute -left-[11px] flex h-5 w-5 items-center justify-center rounded-full bg-white border border-slate-200 shadow-sm">
                                            {trackingIcon(evt)}
                                        </span>
                                        <div className="flex flex-col gap-0.5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium text-slate-900 text-sm">
                                                    {evt.status ||
                                                        evt.code ||
                                                        "Tracking event"}
                                                </span>
                                                {evt.code ? (
                                                    <span className="text-[11px] font-mono text-slate-500 bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5">
                                                        {evt.code}
                                                    </span>
                                                ) : null}
                                                {evt.location ? (
                                                    <span className="text-xs text-slate-500">
                                                        <MapPin
                                                            size={10}
                                                            className="inline mr-0.5"
                                                        />
                                                        {evt.location}
                                                    </span>
                                                ) : null}
                                            </div>
                                            {evt.description ? (
                                                <p className="text-xs text-slate-600">
                                                    {evt.description}
                                                </p>
                                            ) : null}
                                            <time className="text-[11px] text-slate-400 tabular-nums">
                                                {evt.occurred_at
                                                    ? formatDateTime(
                                                          evt.occurred_at,
                                                      )
                                                    : "—"}
                                            </time>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
};

export default DeliveryOrderShow;
