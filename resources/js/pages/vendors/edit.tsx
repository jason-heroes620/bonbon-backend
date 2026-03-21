import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { Vendor } from "@/types";
import { router, Head } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";
import Form from "./form";
import Profile from "./profile";
import { toast } from "sonner";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { FormProvider, useForm } from "react-hook-form";

const Edit = ({
    vendor,
    categories,
}: {
    vendor: Vendor;
    categories: { value: string; label: string }[];
}) => {
    const mode = "update";
    const rawSocialMedias = (vendor as any).social_medias;
    const parsedSocialMedias = (() => {
        if (!rawSocialMedias) {
            return {};
        }
        if (typeof rawSocialMedias === "string") {
            try {
                const obj = JSON.parse(rawSocialMedias);
                if (obj && typeof obj === "object") {
                    return obj;
                }
                return {};
            } catch {
                return {};
            }
        }
        if (typeof rawSocialMedias === "object") {
            return rawSocialMedias;
        }
        return {};
    })();

    const methods = useForm({
        defaultValues: {
            vendor_name: vendor.vendor_name,
            email: vendor.email,
            contact_no: vendor.contact_no,
            first_name: vendor.first_name,
            last_name: vendor.last_name,
            business_registration_number: vendor.business_registration_number,
            company_profile: vendor.company_profile,
            our_services: (vendor as any).our_services || "",
            website: (vendor as any).website || "",
            social_medias: {
                facebook: parsedSocialMedias.facebook || "",
                instagram: parsedSocialMedias.instagram || "",
                youtube: parsedSocialMedias.youtube || "",
                tiktok: parsedSocialMedias.tiktok || "",
                xiaohungshu: parsedSocialMedias.xiaohungshu || "",
            },
            is_active: vendor.is_active,
            profile_picture: vendor.profile_picture || null,
            categories: vendor.categories || [],
            locations: vendor.locations || [],
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: any) => {
        return new Promise<void>((resolve) => {
            router.post(route("vendors.update", vendor.vendor_id), values, {
                forceFormData: true,
                onSuccess: () => {
                    toast.success("Vendor updated successfully");
                    router.visit(route("vendors.index"));
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to update vendor");
                    Object.values(errors).forEach((error) =>
                        toast.error(error),
                    );
                },
                onFinish: () => resolve(),
            });
        });
    };

    return (
        <AppLayout>
            <Head title="Edit Vendor" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("vendors.index"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit Vendor
                            </h2>
                        </div>
                    </div>
                </div>
                <div className="flex-1 mt-4">
                    <FormProvider {...methods}>
                        <form onSubmit={methods.handleSubmit(handleSubmit)}>
                            <Tabs defaultValue="details" className="w-full">
                                <TabsList className="w-full md:w-1/2">
                                    <TabsTrigger
                                        value="details"
                                        className="py-4 data-[state=active]:bg-primary data-[state=active]:text-white"
                                    >
                                        Details
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="profile"
                                        className="py-4 data-[state=active]:bg-primary data-[state=active]:text-white"
                                    >
                                        Profile
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="details">
                                    <div>
                                        <Form
                                            mode={mode}
                                            categories={categories}
                                        />
                                    </div>
                                </TabsContent>
                                <TabsContent value="profile">
                                    <div>
                                        <Profile mode={mode} />
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </form>
                    </FormProvider>
                </div>
            </div>
        </AppLayout>
    );
};

export default Edit;
