import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { router, Head } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";
import Form from "./form";
import { toast } from "sonner";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import Profile from "./profile";
import { FormProvider, useForm } from "react-hook-form";

const Create = ({
    categories,
}: {
    categories: { value: string; label: string }[];
}) => {
    const mode = "create";

    const methods = useForm({
        defaultValues: {
            vendor_name: "",
            email: "",
            contact_no: "",
            first_name: "",
            last_name: "",
            business_registration_number: "",
            company_profile: "",
            our_services: "",
            profile_picture: null,
            website: "",
            social_medias: {
                facebook: "",
                instagram: "",
                youtube: "",
                tiktok: "",
                xiaohungshu: "",
            },
            is_active: "inactive",
            categories: [],
            locations: [],
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: any) => {
        const payload = {
            ...values,
            busines_registration_number: values.business_registration_number,
        };

        return new Promise<void>((resolve) => {
            router.post(route("vendors.store"), payload, {
                forceFormData: true,
                onSuccess: () => {
                    toast.success("Vendor created successfully");
                    router.visit(route("vendors.index"));
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Vendor creation failed");
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
            <Head title="Create Vendor" />
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
                                Create Vendor
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

export default Create;
