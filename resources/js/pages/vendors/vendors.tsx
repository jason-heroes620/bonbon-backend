import AppLayout from '@/layouts/AppLayout'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/react'
import { DataTable }  from "@/components/datatable/data-table"
import type {
  ColumnDef,
} from "@tanstack/react-table"
import type { Vendor } from '@/types'
import { Pencil } from 'lucide-react'

export const columns: ColumnDef<Vendor>[] = [
  {
    accessorKey: 'vendor_name',
    header: 'Vendor Name',
    cell: ({ row }) => row.original.vendor_name,
  },
  {
    accessorKey: 'email',
    header: 'Email',
    cell: ({ row }) => row.original.email,
  },
  {
    accessorKey: 'contact_no',
    header: 'Contact No',
    cell: ({ row }) => row.original.contact_no,
  },
  {
    accessorKey: 'contact_person',
    header: 'Contact Person',
    cell: ({ row }) => row.original.contact_person,
  },
  {
    accessorKey: 'is_active',
    header: 'Is Active',
    cell: ({ row }) => row.original.is_active.toLocaleUpperCase(),
  },
  {
    accessorKey: 'actions',
    header: 'Actions',
    cell: ({ row }) => (
      <div className='flex items-center gap-2'>
        <Button size={"sm"} variant="default" onClick={() => router.visit(route('vendors.edit', row.original.vendor_id))}>
          <Pencil />
        </Button>
      </div>
    ),
  },
]

const Vendors = () => {
  return (
    <AppLayout>
      <div className='flex px-4 py-2 w-full'>
        <div className='flex-1'>
          <div className='flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md'>
            <div>
              Vendors
            </div>
            <div>
              <Button variant="default" onClick={() => router.visit(route('vendors.create'))}>
                Add Vendor
              </Button>
            </div>
          </div>
          <div className='mt-4'>
            <div >
              <DataTable
                  columns={columns}
                  endpoint="/vendors/all"
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

export default Vendors