import AppLayout from '@/layouts/AppLayout'
import { Button } from '@/components/ui/button'
import { router, useForm } from '@inertiajs/react'
import { ChevronLeft } from 'lucide-react'
import Form from './form'
import { toast } from 'sonner'

const Create = () => {
  const mode = 'create';
  const {
    data, setData, 
    errors, processing, post
  } = useForm({
    vendor_name: '',
    email: '',
    contact_no: '',
    contact_person: '',
    busines_registration_number: '',
    company_profile: '',
    profile_picture: null,
    is_active: 'inactive',
  })

  const handleSubmit = (e: SubmitEvent) => {
    e.preventDefault()
    post(route('vendors.store'), {
      onSuccess: () => {
        toast.success('Vendor created successfully')
        router.visit(route('vendors.index'))
      },
      onError: (errors: Record<string, string>) => {
        toast.error('Vendor creation failed')
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

export default  Create
