import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Eye, EyeOff, Check, ArrowRight, Sparkles } from "lucide-react";
import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import { router } from "@inertiajs/react";
import axios from "axios";

function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const strongPasswordSchema = z
    .string()
    .min(8, "Password must be at least 8 characters")
    .regex(/[A-Z]/, "Must contain at least one uppercase letter")
    .regex(/[a-z]/, "Must contain at least one lowercase letter")
    .regex(/[0-9]/, "Must contain at least one number")
    .regex(/[^A-Za-z0-9]/, "Must contain at least one special character");

const loginSchema = z.object({
    email: z.email("Invalid email address"),
    password: z.string().min(1, "Password is required"),
});

const registerSchema = z
    .object({
        first_name: z.string().min(1, "First name is required"),
        last_name: z.string().min(1, "Last name is required"),
        email: z.email("Invalid email address"),
        password: strongPasswordSchema,
        confirmPassword: z.string(),
        terms: z.boolean().refine((value) => value === true, {
            message: "You must accept the terms and conditions",
        }),
    })
    .refine((data) => data.password === data.confirmPassword, {
        message: "Passwords don't match",
        path: ["confirmPassword"],
    });

type LoginFormValues = z.infer<typeof loginSchema>;
type RegisterFormValues = z.infer<typeof registerSchema>;

export default function AuthPage() {
    const [isLogin, setIsLogin] = useState(true);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);

    const loginForm = useForm<LoginFormValues>({
        resolver: zodResolver(loginSchema),
    });

    const registerForm = useForm<RegisterFormValues>({
        resolver: zodResolver(registerSchema),
    });

    const onLoginSubmit = async (data: LoginFormValues) => {
        setIsLoading(true);
        setServerError(null);
        try {
            await axios.post("/login", data);
            router.visit("/dashboard"); // Redirect to dashboard after successful login
        } catch (error: any) {
            if (
                error.response &&
                error.response.data &&
                error.response.data.errors
            ) {
                // Handle validation errors from backend if format matches
                const errors = error.response.data.errors;
                if (errors.email) {
                    loginForm.setError("email", { message: errors.email[0] });
                }
            } else if (
                error.response &&
                error.response.data &&
                error.response.data.message
            ) {
                setServerError(error.response.data.message);
            } else {
                setServerError(
                    "An unexpected error occurred. Please try again.",
                );
            }
        } finally {
            setIsLoading(false);
        }
    };

    const onRegisterSubmit = async (data: RegisterFormValues) => {
        setIsLoading(true);
        setServerError(null);
        try {
            await axios.post("/register", {
                first_name: data.first_name,
                last_name: data.last_name,
                email: data.email,
                password: data.password,
                password_confirmation: data.confirmPassword,
            });
            // After registration, user is logged in, redirect to dashboard
            router.visit("/dashboard");
        } catch (error: any) {
            if (
                error.response &&
                error.response.data &&
                error.response.data.errors
            ) {
                const errors = error.response.data.errors;
                if (errors.email) {
                    registerForm.setError("email", {
                        message: errors.email[0],
                    });
                }
                if (errors.password) {
                    registerForm.setError("password", {
                        message: errors.password[0],
                    });
                }
            } else if (
                error.response &&
                error.response.data &&
                error.response.data.message
            ) {
                setServerError(error.response.data.message);
            } else {
                setServerError(
                    "An unexpected error occurred. Please try again.",
                );
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex bg-gray-50">
            {/* Left Panel - Branding */}
            <div className="hidden lg:flex lg:w-1/2 relative bg-[#F90606] overflow-hidden">
                <div className="absolute inset-0 bg-[#F90606] opacity-90" />
                <div className="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1557683316-973673baf926?auto=format&fit=crop&q=80')] bg-cover bg-center mix-blend-overlay" />
                <div className="relative w-full flex flex-col justify-between p-12 text-white">
                    <div className="flex items-center space-x-2">
                        <div className="bg-white/10 p-2 rounded-lg backdrop-blur-sm">
                            <img src="/bonbon-logo.png" alt="" width={40} />
                        </div>
                        <span className="text-xl font-bold tracking-wider">
                            BONBON
                        </span>
                    </div>
                    <div className="space-y-6 max-w-lg">
                        <h1 className="text-4xl font-bold leading-tight">
                            {isLogin
                                ? "Welcome back to your workspace."
                                : "Start your journey with us."}
                        </h1>
                        <p className="text-white text-lg">
                            Experience the sweet taste of productivity. Manage
                            your projects, collaborate with your team, and
                            achieve your goals with Bonbon.
                        </p>
                    </div>
                    <div className="flex items-center space-x-4 text-sm text-white">
                        <span>© {new Date().getFullYear()} Bonbon.</span>
                        {/* <span>•</span> */}
                        {/* <a
                            href="#"
                            className="hover:text-white transition-colors"
                        >
                            Privacy Policy
                        </a>
                        <span>•</span>
                        <a
                            href="#"
                            className="hover:text-white transition-colors"
                        >
                            Terms of Service
                        </a> */}
                    </div>
                </div>
            </div>

            {/* Right Panel - Auth Forms */}
            <div className="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-8">
                <div className="w-full max-w-md space-y-8">
                    <div className="text-center lg:text-left">
                        <h2 className="text-3xl font-bold tracking-tight text-gray-900">
                            {isLogin
                                ? "Sign in to account"
                                : "Create an account"}
                        </h2>
                        <p className="mt-2 text-sm text-[#F90606]">
                            {isLogin
                                ? "Don't have an account? "
                                : "Already have an account? "}
                            <button
                                onClick={() => {
                                    setIsLogin(!isLogin);
                                    setServerError(null);
                                    loginForm.reset();
                                    registerForm.reset();
                                }}
                                className="font-medium text-indigo-600 hover:text-indigo-500 transition-colors"
                            >
                                {isLogin ? "Sign up for free" : "Sign in here"}
                            </button>
                        </p>
                    </div>

                    <div className="mt-8">
                        {serverError && (
                            <div
                                className="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg"
                                role="alert"
                            >
                                <span className="font-medium">Error:</span>{" "}
                                {serverError}
                            </div>
                        )}
                        {isLogin ? (
                            <form
                                onSubmit={loginForm.handleSubmit(onLoginSubmit)}
                                className="space-y-6"
                            >
                                <div className="space-y-5">
                                    <InputField
                                        id="login-email"
                                        label="Email address"
                                        type="email"
                                        placeholder="you@example.com"
                                        registration={loginForm.register(
                                            "email",
                                        )}
                                        error={loginForm.formState.errors.email}
                                    />

                                    <div className="relative">
                                        <InputField
                                            id="login-password"
                                            label="Password"
                                            type={
                                                showPassword
                                                    ? "text"
                                                    : "password"
                                            }
                                            placeholder="••••••••"
                                            registration={loginForm.register(
                                                "password",
                                            )}
                                            error={
                                                loginForm.formState.errors
                                                    .password
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword(!showPassword)
                                            }
                                            className="absolute top-[34px] right-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        >
                                            {showPassword ? (
                                                <EyeOff className="h-5 w-5" />
                                            ) : (
                                                <Eye className="h-5 w-5" />
                                            )}
                                        </button>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between">
                                    <div className="flex items-center">
                                        <input
                                            id="remember-me"
                                            name="remember-me"
                                            type="checkbox"
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <label
                                            htmlFor="remember-me"
                                            className="ml-2 block text-sm text-gray-900"
                                        >
                                            Remember me
                                        </label>
                                    </div>

                                    <div className="text-sm">
                                        <a
                                            href="/forgot-password"
                                            className="font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            Forgot your password?
                                        </a>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    disabled={isLoading}
                                    className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#F90606] hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {isLoading ? "Signing in..." : "Sign in"}
                                    {!isLoading && (
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    )}
                                </button>
                            </form>
                        ) : (
                            <form
                                onSubmit={registerForm.handleSubmit(
                                    onRegisterSubmit,
                                )}
                                className="space-y-6"
                            >
                                <div className="space-y-5">
                                    <InputField
                                        id="register-first-name"
                                        label="First name"
                                        type="text"
                                        placeholder="John"
                                        registration={registerForm.register(
                                            "first_name",
                                        )}
                                        error={
                                            registerForm.formState.errors
                                                .first_name
                                        }
                                    />
                                    <InputField
                                        id="register-last-name"
                                        label="Last name"
                                        type="text"
                                        placeholder="Doe"
                                        registration={registerForm.register(
                                            "last_name",
                                        )}
                                        error={
                                            registerForm.formState.errors
                                                .last_name
                                        }
                                    />
                                    <InputField
                                        id="register-email"
                                        label="Email address"
                                        type="email"
                                        placeholder="you@example.com"
                                        registration={registerForm.register(
                                            "email",
                                        )}
                                        error={
                                            registerForm.formState.errors.email
                                        }
                                    />

                                    <div className="relative">
                                        <InputField
                                            id="register-password"
                                            label="Password"
                                            type={
                                                showPassword
                                                    ? "text"
                                                    : "password"
                                            }
                                            placeholder="Create a strong password"
                                            registration={registerForm.register(
                                                "password",
                                            )}
                                            error={
                                                registerForm.formState.errors
                                                    .password
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword(!showPassword)
                                            }
                                            className="absolute top-[34px] right-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        >
                                            {showPassword ? (
                                                <EyeOff className="h-5 w-5" />
                                            ) : (
                                                <Eye className="h-5 w-5" />
                                            )}
                                        </button>
                                    </div>

                                    {/* Password Strength Indicator */}
                                    <div className="p-4 bg-gray-50 rounded-lg space-y-2 border border-gray-100">
                                        <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">
                                            Password Requirements
                                        </p>
                                        <div className="grid grid-cols-1 gap-2">
                                            <PasswordRequirement
                                                label="At least 8 characters"
                                                met={
                                                    registerForm.watch(
                                                        "password",
                                                    )?.length >= 8
                                                }
                                            />
                                            <PasswordRequirement
                                                label="Contains uppercase & lowercase"
                                                met={
                                                    /[A-Z]/.test(
                                                        registerForm.watch(
                                                            "password",
                                                        ) || "",
                                                    ) &&
                                                    /[a-z]/.test(
                                                        registerForm.watch(
                                                            "password",
                                                        ) || "",
                                                    )
                                                }
                                            />
                                            <PasswordRequirement
                                                label="Contains number & special char"
                                                met={
                                                    /[0-9]/.test(
                                                        registerForm.watch(
                                                            "password",
                                                        ) || "",
                                                    ) &&
                                                    /[^A-Za-z0-9]/.test(
                                                        registerForm.watch(
                                                            "password",
                                                        ) || "",
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="relative">
                                        <InputField
                                            id="confirmPassword"
                                            label="Confirm Password"
                                            type={
                                                showConfirmPassword
                                                    ? "text"
                                                    : "password"
                                            }
                                            placeholder="Repeat your password"
                                            registration={registerForm.register(
                                                "confirmPassword",
                                            )}
                                            error={
                                                registerForm.formState.errors
                                                    .confirmPassword
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowConfirmPassword(
                                                    !showConfirmPassword,
                                                )
                                            }
                                            className="absolute top-[34px] right-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        >
                                            {showConfirmPassword ? (
                                                <EyeOff className="h-5 w-5" />
                                            ) : (
                                                <Eye className="h-5 w-5" />
                                            )}
                                        </button>
                                    </div>

                                    <div className="flex items-start">
                                        <div className="flex items-center h-5">
                                            <input
                                                id="terms"
                                                type="checkbox"
                                                {...registerForm.register(
                                                    "terms",
                                                )}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                        </div>
                                        <div className="ml-3 text-sm">
                                            <label
                                                htmlFor="terms"
                                                className="font-medium text-gray-700"
                                            >
                                                I agree to the{" "}
                                                <a
                                                    href="#"
                                                    className="text-indigo-600 hover:text-indigo-500 underline"
                                                >
                                                    Terms and Conditions
                                                </a>
                                            </label>
                                            {registerForm.formState.errors
                                                .terms && (
                                                <p className="mt-1 text-sm text-red-600">
                                                    {
                                                        registerForm.formState
                                                            .errors.terms
                                                            .message
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    disabled={isLoading}
                                    className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {isLoading
                                        ? "Creating Account..."
                                        : "Create Account"}
                                    {!isLoading && (
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    )}
                                </button>
                            </form>
                        )}

                        {/* <div className="mt-8 relative">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-gray-200" />
                            </div>
                            <div className="relative flex justify-center text-sm">
                                <span className="px-2 bg-white text-gray-500">
                                    Or continue with
                                </span>
                            </div>
                        </div>

                        <div className="mt-6 grid grid-cols-2 gap-3">
                            <SocialButton
                                icon="https://www.svgrepo.com/show/475656/google-color.svg"
                                label="Google"
                            />
                            <SocialButton
                                icon="https://www.svgrepo.com/show/503173/apple-logo.svg"
                                label="Apple"
                            />
                        </div> */}
                    </div>
                </div>
            </div>
        </div>
    );
}

function InputField({
    id,
    label,
    type,
    placeholder,
    registration,
    error,
}: any) {
    return (
        <div>
            <label
                htmlFor={id}
                className="block text-sm font-medium text-gray-700 mb-1"
            >
                {label}
            </label>
            <input
                id={id}
                type={type}
                placeholder={placeholder}
                {...registration}
                className={cn(
                    "appearance-none block w-full px-4 py-3 border rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors sm:text-sm",
                    error
                        ? "border-red-300 focus:border-red-500 focus:ring-red-500/20"
                        : "border-gray-200",
                )}
            />
            {error && (
                <p className="mt-1 text-sm text-red-600 flex items-center">
                    <span className="mr-1">⚠</span> {error.message}
                </p>
            )}
        </div>
    );
}

function PasswordRequirement({ label, met }: { label: string; met: boolean }) {
    return (
        <div
            className={cn(
                "flex items-center text-xs transition-colors duration-200",
                met ? "text-green-600 font-medium" : "text-gray-400",
            )}
        >
            <div
                className={cn(
                    "w-4 h-4 rounded-full flex items-center justify-center mr-2 border",
                    met
                        ? "bg-green-100 border-green-200"
                        : "bg-gray-100 border-gray-200",
                )}
            >
                {met && <Check className="h-3 w-3 text-green-600" />}
            </div>
            {label}
        </div>
    );
}

function SocialButton({ icon, label }: { icon: string; label: string }) {
    return (
        <button
            type="button"
            className="w-full inline-flex justify-center items-center py-2.5 px-4 border border-gray-200 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
        >
            <img className="h-5 w-5 mr-2" src={icon} alt={label} />
            <span className="sr-only">Sign in with</span>
            {label}
        </button>
    );
}
