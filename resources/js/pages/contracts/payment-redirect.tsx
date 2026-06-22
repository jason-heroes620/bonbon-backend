import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { Head, router } from "@inertiajs/react";
import { useEffect, useRef } from "react";

type Props = {
    gatewayUrl: string;
    fields: Record<string, string>;
};

export default function ContractPaymentRedirect({ gatewayUrl, fields }: Props) {
    const formRef = useRef<HTMLFormElement | null>(null);

    useEffect(() => {
        formRef.current?.submit();
    }, []);

    return (
        <AppLayout>
            <Head title="Redirecting To Payment" />
            <div className="flex px-4 py-8 w-full">
                <div className="mx-auto w-full max-w-xl rounded-lg border bg-white p-6">
                    <h2 className="text-lg font-bold text-[#3730A3]">
                        Redirecting To Payment Gateway
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Please wait while we redirect you to complete your
                        contract payment.
                    </p>

                    <form ref={formRef} method="post" action={gatewayUrl}>
                        {Object.entries(fields).map(([key, value]) => (
                            <input
                                key={key}
                                type="hidden"
                                name={key}
                                value={value}
                            />
                        ))}
                    </form>

                    <div className="mt-6 flex gap-2">
                        <Button
                            variant="default"
                            onClick={() => formRef.current?.submit()}
                        >
                            Continue
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.visit(route("contracts.index"))}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
