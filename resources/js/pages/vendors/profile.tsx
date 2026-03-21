import Editor from "@/components/editor/editor";
import Location from "@/components/location/location";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { router } from "@inertiajs/react";
import { useFormContext } from "react-hook-form";

const Profile = ({ mode }: { mode: "create" | "update" }) => {
    const { control, getValues, formState, setValue, watch } = useFormContext();

    const locations = watch("locations");

    return (
        <div className="bg-white p-6 rounded-md shadow-md">
            <div className="flex flex-col md:grid md:grid-cols-1 gap-4">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="company_profile">Company Profile</Label>
                    <Editor
                        placeholder="Company Profile..."
                        control={control}
                        name="company_profile"
                        defaultValue={getValues("company_profile")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="our_services">Our Services</Label>
                    <Editor
                        placeholder="Our Services..."
                        control={control}
                        name="our_services"
                        defaultValue={getValues("our_services")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label>Locations</Label>
                    <Location
                        mode={mode}
                        data={{ locations }}
                        setData={(key: string, value: any) => {
                            if (key === "locations") {
                                setValue("locations", value);
                            }
                        }}
                    />
                </div>
                <div className="flex justify-end items-center pt-4 gap-2">
                    <Button
                        type="button"
                        size={"sm"}
                        variant="secondary"
                        onClick={() => router.visit(route("vendors.index"))}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        size={"sm"}
                        variant="default"
                        className=" text-white px-4 py-2 rounded-md"
                        disabled={formState.isSubmitting}
                    >
                        {formState.isSubmitting
                            ? "Submitting..."
                            : mode === "create"
                              ? "Save"
                              : "Update"}
                    </Button>
                </div>
            </div>
        </div>
    );
};

export default Profile;
