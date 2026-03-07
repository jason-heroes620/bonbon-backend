export type Vendor = {
  vendor_id: number
  vendor_name: string
  email: string
  contact_no: string
  contact_person: string
  business_registration_number: string
  company_profile: string
  is_active: string
  profile_picture?: string | null
}

export type Voucher = {
  voucher_id: number
  vendor_name: string
  voucher_name: string
  voucher_short_description: string
  voucher_description: string
  duration: number
  what_you_get: string
  voucher_code: string
  voucher_discount: number
  voucher_type: string
  voucher_start_date: string
  voucher_expiry_date: string
  voucher_limit: number
  voucher_claim_per_user: number
  voucher_image_path?: string | null
  voucher_status: boolean
}

export interface ApiResponse<T> {
    data: T[];
    meta: PaginationMeta;
    filters?: Record<string, any>;
}

export interface TableOptions {
    showSearch?: boolean;
    showFilters?: boolean;
    showPagination?: boolean;
    defaultPageSize?: number;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}