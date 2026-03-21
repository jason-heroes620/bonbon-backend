import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";
import { MultiSelect } from "@/components/ui/multi-select";
import { useEffect, useState } from "react";
import { Controller, useFormContext } from "react-hook-form";

const items: { value: string; label: string }[] = [
    {
        value: "active",
        label: "Active",
    },
    {
        value: "inactive",
        label: "Inactive",
    },
];

const Form = ({
    mode,
    categories,
}: {
    mode: "create" | "update";
    categories: { value: string; label: string }[];
}) => {
    const { register, control, watch, setValue, formState } = useFormContext();
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const profilePicture = watch("profile_picture");
    const vendorName = watch("vendor_name");

    useEffect(() => {
        if (profilePicture instanceof File) {
            const url = URL.createObjectURL(profilePicture);
            setPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        }
        if (typeof profilePicture === "string") {
            setPreviewUrl(profilePicture);
            return;
        }
        setPreviewUrl(null);
    }, [profilePicture]);

    return (
        <div>
            <div className="bg-white p-6 rounded-md shadow-md">
                {previewUrl && (
                    <div className="flex justify-start items-center pb-4">
                        <img
                            src={previewUrl}
                            alt={vendorName}
                            className="w-20 h-20 rounded-full p-4"
                        />
                    </div>
                )}
                <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="vendor_name">Vendor Name</Label>
                        <Input
                            type="text"
                            id="vendor_name"
                            maxLength={150}
                            required
                            className="border border-[#D1D5DB] rounded-md px-4 py-2"
                            {...register("vendor_name")}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            type="email"
                            id="email"
                            maxLength={200}
                            required
                            disabled={mode === "update" ? true : false}
                            className="border border-[#D1D5DB] rounded-md px-4 py-2"
                            {...register("email")}
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="contact_person">Contact Person</Label>
                        <Input
                            type="text"
                            id="first_name"
                            maxLength={150}
                            required
                            placeholder="First Name"
                            className="border border-[#D1D5DB] rounded-md px-4 py-2"
                            {...register("first_name")}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="contact_person"> </Label>
                        <Input
                            type="text"
                            id="last_name"
                            maxLength={150}
                            required
                            placeholder="Last Name"
                            className="border border-[#D1D5DB] rounded-md px-4 py-2 mt-3"
                            {...register("last_name")}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="contact_no">Contact Number</Label>
                        <Input
                            type="tel"
                            id="contact_no"
                            maxLength={25}
                            required
                            className="border border-[#D1D5DB] rounded-md px-4 py-2"
                            {...register("contact_no")}
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="business_registration_number">
                            Business Registration Number
                        </Label>
                        <Input
                            type="text"
                            id="business_registration_number"
                            maxLength={100}
                            required
                            className="border border-[#D1D5DB] rounded-md px-4 py-2"
                            {...register("business_registration_number")}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label>Categories</Label>
                        <Controller
                            name="categories"
                            control={control}
                            render={({ field }) => (
                                <MultiSelect
                                    defaultValue={field.value || []}
                                    options={categories}
                                    onValueChange={field.onChange}
                                    placeholder="Choose categories"
                                />
                            )}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="profile_picture">Profile Picture</Label>
                        <Input
                            type="file"
                            id="profile_picture"
                            name="profile_picture"
                            onChange={(e) =>
                                setValue(
                                    "profile_picture",
                                    e.target.files?.[0] || null,
                                )
                            }
                            className="border border-[#D1D5DB] items-center rounded-md"
                        />
                    </div>
                    <hr className="col-span-2" />
                    <section className="flex flex-col gap-2 col-span-2 bg-gray-100 px-2 py-4">
                        <Label>Social Medias</Label>
                        <div className="flex flex-col gap-4 md:grid md:grid-cols-2 py-4 px-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="website">Website</Label>
                                <Input
                                    type="text"
                                    id="website"
                                    maxLength={200}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...register("website")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="social_medias_facebook">
                                    Facebook
                                </Label>
                                <Input
                                    type="text"
                                    id="social_medias_facebook"
                                    maxLength={200}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...register("social_medias.facebook")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="social_medias_instagram">
                                    Instagram
                                </Label>
                                <Input
                                    type="text"
                                    id="social_medias_instagram"
                                    maxLength={200}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...register("social_medias.instagram")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="social_medias_youtube">
                                    YouTube
                                </Label>
                                <Input
                                    type="text"
                                    id="social_medias_youtube"
                                    maxLength={200}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...register("social_medias.youtube")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="social_medias_tiktok">
                                    TikTok
                                </Label>
                                <Input
                                    type="text"
                                    id="social_medias_tiktok"
                                    maxLength={200}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...register("social_medias.tiktok")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="social_medias_xiaohungshu">
                                    Xiaohongshu
                                </Label>
                                <Input
                                    type="text"
                                    id="social_medias_xiaohungshu"
                                    maxLength={200}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...register("social_medias.xiaohungshu")}
                                />
                            </div>
                        </div>
                    </section>
                    <hr className="col-span-2" />

                    {mode === "update" && (
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="is_active">Status</Label>
                            <Controller
                                name="is_active"
                                control={control}
                                render={({ field }) => (
                                    <Select
                                        value={field.value}
                                        onValueChange={field.onChange}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {items.map((item) => (
                                                    <SelectItem
                                                        key={item.value}
                                                        value={item.value}
                                                    >
                                                        {item.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </div>
                    )}
                    <div></div>
                    <div className="flex flex-end md:col-span-2 justify-end gap-2">
                        <Button
                            size={"sm"}
                            type="button"
                            variant="secondary"
                            onClick={() => router.visit(route("vendors.index"))}
                        >
                            Cancel
                        </Button>
                        <Button
                            size={"sm"}
                            type="submit"
                            disabled={formState.isSubmitting}
                        >
                            {formState.isSubmitting
                                ? "Saving..."
                                : mode === "create"
                                  ? "Save"
                                  : "Update"}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Form;
