import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useState } from "react"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { Calendar as CalendarIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import { format } from "date-fns"

const Form = ({mode, data, setData, errors, processing, handleSubmit}: {
    mode: 'create' | 'update', 
    data: any, 
    setData: any, 
    errors: any, 
    processing: boolean, 
    handleSubmit: any
}) => {
    const [shortDescriptionLength, setShortDescriptionLength] = useState(0);

    const handleShortDescriptionChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setShortDescriptionLength(e.target.value.length);
        setData('voucher_short_description', e.target.value);
    }

  return (
     <div>
      <form onSubmit={handleSubmit} className='bg-white p-6 rounded-md shadow-md'>
        <div className='flex flex-col md:grid md:grid-cols-2 gap-4'>
            <div>
                <Label htmlFor="voucher_name">Voucher Name</Label>
                <Input id="voucher_name" name="voucher_name" value={data.voucher_name} onChange={e => setData('voucher_name', e.target.value)} 
                maxLength={255}
                className='mt-2'
                />
            </div>
            <div className="flex flex-col">
                <Label htmlFor="voucher_short_description">Short Description</Label>
                <Input id="voucher_short_description" name="voucher_short_description" value={data.voucher_short_description} onChange={e => handleShortDescriptionChange(e)} 
                maxLength={100}
                className='mt-2'
                />
                <span className="text-right text-sm text-muted-foreground">{shortDescriptionLength}/100</span>
            </div>
            <div>
                <Label htmlFor="voucher_start_date">Start Date</Label>
                <Popover >
                    <PopoverTrigger asChild className="w-full">
                        <Button
                            variant="outline"
                            data-empty={!data.voucher_start_date}
                            className="justify-start text-left font-normal data-[empty=true]:text-muted-foreground mt-2"
                        >
                        <CalendarIcon size={20} />
                        {data.voucher_start_date ? format(data.voucher_start_date, "PPP") : <span>Pick a date</span>}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0">
                        <Calendar mode="single" selected={data.voucher_start_date} onSelect={value => setData('voucher_start_date', value)} />
                    </PopoverContent>
                    </Popover>
            </div>
            <div>
                <Label htmlFor="voucher_start_date">Expiry Date</Label>
                <Popover >
                    <PopoverTrigger asChild className="w-full">
                        <Button
                            variant="outline"
                            data-empty={!data.voucher_expiry_date}
                            className="justify-start text-left font-normal data-[empty=true]:text-muted-foreground mt-2"
                        >
                        <CalendarIcon size={20} />
                        {data.voucher_expiry_date ? format(data.voucher_expiry_date, "PPP") : <span>Pick a date</span>}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0">
                        <Calendar 
                            mode="single" 
                            selected={data.voucher_expiry_date}
                            disabled={(day) => (data.voucher_start_date ? day < data.voucher_start_date : false)}
                            onSelect={value => setData('voucher_expiry_date', value)} />
                    </PopoverContent>
                    </Popover>
            </div>
        </div>
      </form>
    </div>
  )
}

export default Form