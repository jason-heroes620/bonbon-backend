import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import axios from "axios";
import { useState } from "react";
import { router } from "@inertiajs/react";

type Props = {
    token: string;
    email?: string | null;
};

const schema = z
    .object({
        email: z.string().email("Invalid email address"),
        password: z
            .string()
            .min(8, "Password must be at least 8 characters")
            .regex(
                /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/,
                "Password must include uppercase, lowercase, number and symbol",
            ),
        password_confirmation: z.string(),
    })
    .refine((data) => data.password === data.password_confirmation, {
        message: "Passwords do not match",
        path: ["password_confirmation"],
    });

type FormValues = z.infer<typeof schema>;

export default function ResetPassword({ token, email }: Props) {
    const [serverMessage, setServerMessage] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    const form = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: {
            email: email ?? "",
            password: "",
            password_confirmation: "",
        },
    });

    const onSubmit = async (data: FormValues) => {
        setIsLoading(true);
        setServerMessage(null);
        setServerError(null);
        try {
            const res = await axios.post("/reset-password", {
                token,
                email: data.email,
                password: data.password,
                password_confirmation: data.password_confirmation,
            });
            setServerMessage(
                res.data?.message ?? "Password has been reset successfully.",
            );
            setTimeout(() => router.visit("/login"), 1000);
        } catch (error: any) {
            if (error.response?.data?.message) {
                setServerError(error.response.data.message);
            } else {
                setServerError("Failed to reset password. Please try again.");
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex bg-gray-50">
            <div className="w-full flex items-center justify-center p-6">
                <div className="w-full max-w-md space-y-8 bg-white shadow-sm rounded-xl p-6">
                    <div className="text-center">
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900">
                            Reset your password
                        </h2>
                        <p className="mt-2 text-sm text-gray-600">
                            Enter your email and new password to complete the
                            reset.
                        </p>
                    </div>

                    {serverMessage && (
                        <div
                            className="p-3 text-sm text-green-700 bg-green-100 rounded-lg"
                            role="alert"
                        >
                            {serverMessage}
                        </div>
                    )}
                    {serverError && (
                        <div
                            className="p-3 text-sm text-red-700 bg-red-100 rounded-lg"
                            role="alert"
                        >
                            {serverError}
                        </div>
                    )}

                    <form
                        onSubmit={form.handleSubmit(onSubmit)}
                        className="space-y-5"
                    >
                        <div>
                            <label
                                htmlFor="email"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                placeholder="you@example.com"
                                {...form.register("email")}
                                className="mt-1 h-12 px-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {form.formState.errors.email && (
                                <p className="mt-1 text-sm text-red-600">
                                    {form.formState.errors.email.message}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="password"
                                className="block text-sm font-medium text-gray-700"
                            >
                                New password
                            </label>
                            <input
                                id="password"
                                type="password"
                                placeholder="Enter a strong password"
                                {...form.register("password")}
                                className="mt-1 h-12 px-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {form.formState.errors.password && (
                                <p className="mt-1 text-sm text-red-600">
                                    {form.formState.errors.password.message}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="password_confirmation"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Confirm new password
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                placeholder="Repeat the new password"
                                {...form.register("password_confirmation")}
                                className="mt-1 h-12 px-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {form.formState.errors.password_confirmation && (
                                <p className="mt-1 text-sm text-red-600">
                                    {
                                        form.formState.errors
                                            .password_confirmation.message
                                    }
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={isLoading}
                            className="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {isLoading ? "Resetting..." : "Reset password"}
                        </button>
                    </form>

                    <div className="text-center">
                        <button
                            onClick={() => router.visit("/login")}
                            className="text-sm text-indigo-600 hover:text-indigo-500"
                        >
                            Back to sign in
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
