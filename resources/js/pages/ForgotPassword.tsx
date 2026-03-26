import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import axios from "axios";
import { useState } from "react";
import { router } from "@inertiajs/react";

const schema = z.object({
    email: z.string().email("Invalid email address"),
});

type FormValues = z.infer<typeof schema>;

export default function ForgotPassword() {
    const [serverMessage, setServerMessage] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    const form = useForm<FormValues>({
        resolver: zodResolver(schema),
    });

    const onSubmit = async (data: FormValues) => {
        setIsLoading(true);
        setServerMessage(null);
        setServerError(null);
        try {
            const res = await axios.post("/forgot-password", {
                email: data.email,
            });
            setServerMessage(
                res.data?.message ??
                    "If the email exists, a reset link has been sent.",
            );
        } catch (error: any) {
            if (error.response?.data?.message) {
                setServerError(error.response.data.message);
            } else {
                setServerError(
                    "Failed to request password reset. Please try again.",
                );
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
                            Forgot your password?
                        </h2>
                        <p className="mt-2 text-sm text-gray-600">
                            Enter your email address and we will send you a
                            password reset link if an account exists.
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

                        <button
                            type="submit"
                            disabled={isLoading}
                            className="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {isLoading ? "Sending..." : "Send reset link"}
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
