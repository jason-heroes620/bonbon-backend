import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { router, Head } from "@inertiajs/react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type Props = {
    retention_period_days: number;
    financial_retention_years: number;
};

type FormValues = {
    email: string;
    password: string;
};

const DeleteAccount = ({
    retention_period_days,
    financial_retention_years,
}: Props) => {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const methods = useForm<FormValues>({
        defaultValues: { email: "", password: "" },
    });

    const handleSubmit = (values: FormValues) => {
        setIsSubmitting(true);
        router.post("/delete-account", values, {
            onError: (errors: Record<string, string>) => {
                if (errors.email) {
                    methods.setError("email", { message: errors.email });
                }
                if (errors.password) {
                    methods.setError("password", { message: errors.password });
                }
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
            onSuccess: () => {
                methods.reset({ email: "", password: "" });
                toast.success("Account deletion request submitted.");
            },
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
            <Head title="Delete Account" />
            <div className="w-full max-w-2xl space-y-6">
                <div className="bg-white border rounded-xl p-6 space-y-3">
                    <h1 className="text-2xl font-semibold text-gray-900">
                        Request Account Deletion
                    </h1>
                    <p className="text-sm text-gray-700">
                        This page lets you request deletion of your BonBon
                        account.
                    </p>
                </div>

                <div className="bg-white border rounded-xl p-6 space-y-4">
                    <h2 className="text-base font-semibold text-gray-900">
                        Steps to Request Deletion
                    </h2>
                    <ol className="list-decimal ml-5 space-y-2 text-sm text-gray-800">
                        <li>Enter the email address of the account.</li>
                        <li>Enter your account password to confirm.</li>
                        <li>
                            Submit the request. Your account is deactivated
                            immediately.
                        </li>
                    </ol>
                </div>

                <div className="bg-white border rounded-xl p-6 space-y-4">
                    <h2 className="text-base font-semibold text-gray-900">
                        What We Delete, Keep, and Retain
                    </h2>
                    <div className="space-y-3 text-sm text-gray-800">
                        <div className="space-y-1">
                            <div className="font-medium text-gray-900">
                                Deleted / Disabled
                            </div>
                            <ul className="list-disc ml-5 space-y-1">
                                <li>
                                    Your ability to log in (account is marked
                                    inactive immediately)
                                </li>
                                <li>
                                    Push notification tokens linked to your
                                    account
                                </li>
                            </ul>
                        </div>

                        <div className="space-y-1">
                            <div className="font-medium text-gray-900">
                                Kept for legal / compliance purposes
                            </div>
                            <ul className="list-disc ml-5 space-y-1">
                                <li>
                                    Payment and transaction records (retained{" "}
                                    {financial_retention_years} years)
                                </li>
                                <li>
                                    Security/audit records required for fraud
                                    prevention or legal obligations
                                </li>
                            </ul>
                        </div>

                        <div className="space-y-1">
                            <div className="font-medium text-gray-900">
                                Retention period
                            </div>
                            <p className="text-gray-800">
                                After you submit a deletion request, we retain
                                account-related data for up to{" "}
                                {retention_period_days} days before completing
                                the deletion process, unless a longer retention
                                is required for the records listed above.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="bg-white border rounded-xl p-6 space-y-4">
                    <h2 className="text-base font-semibold text-gray-900">
                        Submit Request
                    </h2>
                    <form
                        className="space-y-4"
                        onSubmit={methods.handleSubmit(handleSubmit)}
                    >
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-gray-900">
                                Email
                            </label>
                            <Input
                                type="email"
                                autoComplete="email"
                                aria-invalid={Boolean(
                                    methods.formState.errors.email,
                                )}
                                {...methods.register("email")}
                            />
                            {methods.formState.errors.email?.message ? (
                                <div className="text-sm text-red-600">
                                    {methods.formState.errors.email.message}
                                </div>
                            ) : null}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium text-gray-900">
                                Password
                            </label>
                            <Input
                                type="password"
                                autoComplete="current-password"
                                aria-invalid={Boolean(
                                    methods.formState.errors.password,
                                )}
                                {...methods.register("password")}
                            />
                            {methods.formState.errors.password?.message ? (
                                <div className="text-sm text-red-600">
                                    {methods.formState.errors.password.message}
                                </div>
                            ) : null}
                        </div>

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                disabled={isSubmitting}
                                variant="destructive"
                            >
                                Request Deletion
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default DeleteAccount;
