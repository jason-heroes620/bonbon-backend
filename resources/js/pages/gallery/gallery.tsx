import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { DataTable } from "@/components/datatable/data-table";
import { UserEmailTagger } from "@/components/gallery/user-email-tagger";
import AppLayout from "@/layouts/AppLayout";
import type { GalleryImage } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import axios from "axios";
import { ImagePlus, Pencil, Tags } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { GalleryUploadForm } from "./gallery-form";

const statusStyles: Record<GalleryImage["status"], string> = {
    pending: "bg-gray-100 text-gray-700",
    processing: "bg-yellow-100 text-yellow-700",
    ready: "bg-green-100 text-green-700",
    failed: "bg-red-100 text-red-700",
};

export const columns: ColumnDef<GalleryImage>[] = [
    {
        accessorKey: "thumbnail",
        header: "Image",
        enableSorting: false,
        cell: ({ row }) => (
            <img
                src={row.original.thumbnail_url ?? row.original.original_url}
                alt={row.original.title ?? ""}
                className="h-12 w-12 rounded-md object-cover"
            />
        ),
    },
    {
        accessorKey: "title",
        header: "Title",
        cell: ({ row }) => row.original.title ?? "-",
    },
    {
        accessorKey: "tagged_users_count",
        header: "Tagged Users",
        cell: ({ row }) => row.original.tagged_users_count ?? 0,
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => (
            <span
                className={`rounded-full px-2 py-1 text-xs font-medium ${
                    statusStyles[row.original.status] ??
                    statusStyles.pending
                }`}
            >
                {row.original.status}
            </span>
        ),
    },
    {
        accessorKey: "is_active",
        header: "Active",
        cell: ({ row }) => (row.original.is_active ? "Yes" : "No"),
    },
    {
        accessorKey: "created_at",
        header: "Uploaded",
        cell: ({ row }) =>
            row.original.created_at
                ? new Date(row.original.created_at).toLocaleString()
                : "-",
    },
    {
        accessorKey: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Button
                    size={"sm"}
                    variant="default"
                    onClick={() =>
                        router.visit(
                            route("gallery.show", row.original.gallery_image_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

function BulkTagDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [images, setImages] = useState<GalleryImage[]>([]);
    const [selected, setSelected] = useState<string[]>([]);
    const [emails, setEmails] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!open) return;
        axios
            .get("/gallery/all", { params: { per_page: 100 } })
            .then((response) => setImages(response.data?.data ?? []))
            .catch(() => setImages([]));
    }, [open]);

    const toggle = (id: string) => {
        setSelected((prev) =>
            prev.includes(id)
                ? prev.filter((item) => item !== id)
                : [...prev, id],
        );
    };

    const submit = () => {
        if (selected.length === 0 || emails.length === 0) {
            toast.error("Select at least one image and one email.");
            return;
        }

        setSubmitting(true);
        router.post(
            route("gallery.tags.bulk"),
            { gallery_image_ids: selected, emails } as any,
            {
                onSuccess: () => {
                    toast.success("Images tagged successfully");
                    setSelected([]);
                    setEmails([]);
                    onOpenChange(false);
                },
                onError: () => toast.error("Failed to tag images"),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Bulk tag images</DialogTitle>
                    <DialogDescription>
                        Select images and tag them to one or more users by
                        email.
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-72 overflow-y-auto">
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        {images.map((image) => {
                            const isSelected = selected.includes(
                                image.gallery_image_id,
                            );
                            return (
                                <button
                                    key={image.gallery_image_id}
                                    type="button"
                                    onClick={() =>
                                        toggle(image.gallery_image_id)
                                    }
                                    className={`relative aspect-square overflow-hidden rounded-md border-2 ${
                                        isSelected
                                            ? "border-[#3730A3]"
                                            : "border-transparent"
                                    }`}
                                >
                                    <img
                                        src={
                                            image.thumbnail_url ??
                                            image.original_url
                                        }
                                        alt={image.title ?? ""}
                                        className="h-full w-full object-cover"
                                    />
                                </button>
                            );
                        })}
                        {images.length === 0 && (
                            <p className="col-span-full py-6 text-center text-sm text-gray-500">
                                No images available.
                            </p>
                        )}
                    </div>
                </div>

                <UserEmailTagger value={emails} onChange={setEmails} />

                <DialogFooter>
                    <Button
                        variant="secondary"
                        size={"sm"}
                        type="button"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        size={"sm"}
                        type="button"
                        disabled={submitting}
                        onClick={submit}
                    >
                        {submitting ? "Tagging..." : "Tag selected"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const Gallery = () => {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Gallery
                            </h2>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setBulkOpen(true)}
                            >
                                <Tags className="mr-2" size={20} />
                                Bulk Tag
                            </Button>
                            <Button
                                variant="default"
                                onClick={() => setUploadOpen(true)}
                            >
                                <ImagePlus className="mr-2" size={20} />
                                Upload Images
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            key={refreshKey}
                            columns={columns}
                            endpoint="/gallery/all"
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

            <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Upload images</DialogTitle>
                        <DialogDescription>
                            Upload one or more images. Thumbnails are generated
                            automatically.
                        </DialogDescription>
                    </DialogHeader>
                    <GalleryUploadForm
                        onSuccess={() => {
                            setUploadOpen(false);
                            setRefreshKey((key) => key + 1);
                        }}
                    />
                </DialogContent>
            </Dialog>

            <BulkTagDialog open={bulkOpen} onOpenChange={setBulkOpen} />
        </AppLayout>
    );
};

export default Gallery;
