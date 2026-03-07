import AppLayout from '@/layouts/AppLayout'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/react'
import { DataTable }  from "@/components/datatable/data-table"
import type {
  ColumnDef,
} from "@tanstack/react-table"
import type { Voucher } from '@/types'
import { Pencil } from 'lucide-react'

export const columns: ColumnDef<Voucher>[] = [
  {
    accessorKey: 'vendor_name',
    header: 'Vendor Name',
    cell: ({ row }) => row.original.vendor_name,
  },
  {
    accessorKey: 'voucher_name',
    header: 'Voucher Name',
    cell: ({ row }) => row.original.voucher_name,
  },
  {
    accessorKey: 'voucher_start_date',
    header: 'Start Date',
    cell: ({ row }) => row.original.voucher_start_date,
  },
  {
    accessorKey: 'voucher_expiry_date',
    header: 'Expiry Date',
    cell: ({ row }) => row.original.voucher_expiry_date,
  },
  {
    accessorKey: 'voucher_status',
    header: 'Status',
    cell: ({ row }) => row.original.voucher_status ? 'Active' : 'Inactive',
  },
  {
    accessorKey: 'actions',
    header: 'Actions',
    cell: ({ row }) => (
      <div className='flex items-center gap-2'>
        <Button size={"sm"} variant="default" onClick={() => router.visit(route('vouchers.edit', row.original.voucher_id))}>
          <Pencil />
        </Button>
      </div>
    ),
  },
]

const vouchers = () => {
  return (
      <AppLayout>
        <div className='flex px-4 py-2 w-full'>
          <div className='flex-1'>
            <div className='flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md'>
              <div>
                <h2 className='text-lg font-bold text-[#3730A3]'>Vouchers</h2>
              </div>
              <div>
                <Button variant="default" onClick={() => router.visit(route('vouchers.create'))}>
                  Add Voucher
                </Button>
              </div>
            </div>
            <div className='mt-4'>
              <div >
                <DataTable
                    columns={columns}
                    endpoint="/vouchers/all"
                    options={{
                        showSearch: true,
                        showFilters: true,
                        showPagination: true,
                        defaultPageSize: 10,
                    }}
                />
              </div>
            </div>
          </div>
          
        </div>
      </AppLayout>
    )
}

export default vouchers