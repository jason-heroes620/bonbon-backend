import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { Voucher } from "@/types";
import { router, useForm } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";
import Form from "./form";
import { toast } from "sonner";

const Create = () => {
    const mode = "create";
    const { data, setData, processing, errors, post } = useForm({
        voucher_name: '',
        voucher_short_description: '',

    })

  const handleSubmit = (e: SubmitEvent) => {
    e.preventDefault();
    post(route.post('vouchers.store'), {
        onSuccess: () => {
            toast.success("Voucher created.")
            router.visit(route('vouchers.index'))
        },
        onError: () => {
            toast.error("Failed to create voucher")
            Object.values(errors).forEach(error => toast.error(error))
        }
    })
  }
  return (
    <AppLayout>
     <div className='flex flex-col px-4 py-2 w-full'>
        <div className='flex-1'>
          <div className='flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md'>
            <div className='flex items-center gap-4'>
              <Button variant="default" onClick={() => router.visit(route('vouchers.index'))}>
                <ChevronLeft className="mr" size={20} />
                Back
              </Button>
              <span className='text-lg font-bold text-[#3730A3]'>Create Voucher</span>
            </div>
          </div>
        </div>
        <div className='mt-4'>
          <div>
            <Form 
            mode={mode} 
            data={data} 
            setData={setData} 
            errors={errors} 
            processing={processing} 
            handleSubmit={handleSubmit}
            />
          </div>
        </div>
      </div>
    </AppLayout>
  )
}

export default Create