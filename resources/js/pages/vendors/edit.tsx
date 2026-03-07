import { Button } from '@/components/ui/button'
import AppLayout from '@/layouts/AppLayout'
import type { Vendor } from '@/types'
import { router, useForm } from '@inertiajs/react'
import { ChevronLeft } from 'lucide-react'
import Form from './form'
import { toast } from 'sonner'

const Edit = ({ vendor }: { vendor: Vendor}) => {
  const mode = 'update'
  const { data, setData, errors, processing, post } = useForm({
    vendor_name: vendor.vendor_name,
    email: vendor.email,
    contact_no: vendor.contact_no,
    contact_person: vendor.contact_person,
    business_registration_number: vendor.business_registration_number,
    company_profile: vendor.company_profile,
    is_active: vendor.is_active,
    profile_picture: vendor.profile_picture,
  })

  const handleSubmit = (e: SubmitEvent) => {
    e.preventDefault()
    post(route('vendors.update', vendor.vendor_id), {
      forceFormData: true,
      onSuccess: () => {
        toast.success('Vendor updated successfully')
        router.visit(route('vendors.index'))
      },
      onError: () => {
        toast.error('Failed to update vendor')
      }
    })
  }

  return (
    <AppLayout>
     <div className='flex flex-col px-4 py-2 w-full'>
        <div className='flex-1'>
          <div className='flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md'>
            <div className='flex items-center gap-4'>
              <Button variant="default" onClick={() => router.visit(route('vendors.index'))}>
                <ChevronLeft className="mr" size={20} />
                Back
              </Button>
              <span>Create Vendor</span>
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

export default Edit