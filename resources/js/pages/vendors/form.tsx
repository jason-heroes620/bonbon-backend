import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'

const items = [
  {
    value: 'active',
    label: 'Active',
  },
  {
    value: 'inactive',
    label: 'Inactive',
  },
]

const Form = ({mode, data, setData, errors, processing, handleSubmit}: 
  {mode: 'create' | 'update', data: any, setData: any, errors: any, processing: boolean, handleSubmit: any}) => {

  return (
    <div>
      <form onSubmit={handleSubmit} className='bg-white p-6 rounded-md shadow-md'>
        {
            data.profile_picture && data.profile_picture !== null && (
            <div className='flex justify-start items-center pb-4'>
              <img src={data.profile_picture} alt={data.vendor_name} className='w-20 h-20 rounded-full p-4' />
            </div>
          )
        }
      <div className='flex flex-col md:grid md:grid-cols-2 gap-4'>
        <div className='flex flex-col gap-2'>
          <Label htmlFor="vendor_name">Vendor Name</Label>
          <Input type="text" id="vendor_name" name="vendor_name" 
          value={data.vendor_name}
          maxLength={150}
          onChange={(e) => setData({...data, vendor_name: e.target.value})}
          required
          className='border border-[#D1D5DB] rounded-md px-4 py-2' />
        </div>
        <div className='flex flex-col gap-2'>
          <Label htmlFor="email">Email</Label>
          <Input type="email" id="email" name="email" 
          value={data.email}
          maxLength={200}
          onChange={(e) => setData({...data, email: e.target.value})}
          required
          disabled={mode === 'update' ? true : false}
          className='border border-[#D1D5DB] rounded-md px-4 py-2' />
        </div>
        <div className='flex flex-col gap-2'>
          <Label htmlFor="contact_no">Contact Number</Label>
          <Input type="tel" id="contact_no" name="contact_no" 
          value={data.contact_no}
          maxLength={25}
          onChange={(e) => setData({...data, contact_no: e.target.value})}
          required
          className='border border-[#D1D5DB] rounded-md px-4 py-2' />
        </div>
        <div className='flex flex-col gap-2'>
          <Label htmlFor="contact_person">Contact Person</Label>
          <Input type="text" id="contact_person" name="contact_person" 
          value={data.contact_person}
          maxLength={150}
          onChange={(e) => setData({...data, contact_person: e.target.value})}
          required
          className='border border-[#D1D5DB] rounded-md px-4 py-2' />
        </div>
        <div className='flex flex-col gap-2'>
          <Label htmlFor="business_registration_number">Business Registration Number</Label>
          <Input type="text" id="business_registration_number" name="business_registration_number" 
          value={data.business_registration_number}
          maxLength={100}
          onChange={(e) => setData({...data, business_registration_number: e.target.value})}
          required
          className='border border-[#D1D5DB] rounded-md px-4 py-2' />
        </div>
        <div className='flex flex-col gap-2'>
          <Label htmlFor="company_profile">Company Profile</Label>
          <Textarea id="company_profile" name="company_profile" 
          value={data.company_profile}
          onChange={(e) => setData({...data, company_profile: e.target.value})}
          className='border border-[#D1D5DB] rounded-md px-4 py-2' />
        </div>
        <div className='flex flex-col gap-2'>
            <Label htmlFor="profile_picture">Profile Picture</Label>
            <Input type="file" id="profile_picture" name="profile_picture" 
            onChange={(e) => setData({...data, profile_picture: e.target.files?.[0]})}
            className='border border-[#D1D5DB] items-center rounded-md' />
        </div>
        {
            mode === 'update' && (
                <div className='flex flex-col gap-2'>
                    <Label htmlFor="is_active">Status</Label>
                    <Select items={items} 
                    value={data.is_active}
                    onValueChange={(value: string) => setData({...data, is_active: value})}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {items.map((item) => (
                                    <SelectItem key={item.value} value={item.value}>
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            )
        }
        <div></div>
        <div className='flex flex-end md:col-span-2 justify-end gap-2'>
            <Button size={"sm"} type="submit" disabled={processing}>{processing ? 'Submitting...' : 'Submit'}</Button>
        </div>
      </div>
    </form>
    </div>
  )
}

export default Form