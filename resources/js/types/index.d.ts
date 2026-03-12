export type Vendor = {
    vendor_id: number;
    vendor_name: string;
    email: string;
    contact_no: string;
    contact_person: string;
    business_registration_number: string;
    company_profile: string;
    is_active: string;
    profile_picture?: string | null;
    categories?: [
        {
            category_id: string;
            category_name: string;
        },
    ];
    locations?: [
        {
            location_name: string;
            latitude: number;
            longitude: number;
            place_id?: string;
            address?: string;
            viewport?: any;
            raw_place?: any;
            is_primary?: boolean;
        },
    ];
};

export type Voucher = {
    voucher_id: number;
    vendor_name: string;
    voucher_name: string;
    voucher_short_description: string;
    voucher_description: string;
    duration: number;
    what_you_get: string;
    voucher_code: string;
    voucher_discount: number;
    voucher_type: string;
    voucher_start_date: string;
    voucher_expiry_date: string;
    voucher_limit: number;
    voucher_claim_per_user: number;
    voucher_image_path?: string | null;
    voucher_status: boolean;
};

export type User = {
    user_id: string;
    name: string;
    email: string;
    contact_no: string;
    is_active: boolean;
    role: string;
    profile_picture?: string | null;
};

export type Membership = {
    membership_id: string;
    membership_code: string;
    membership_name: string;
    membership_description?: string | null;
    membership_type_id?: string | null;
    membership_type: string;
    membership_price: string;
    duration: number;
    duration_unit: "days" | "months" | "years";
    membership_start_date: string;
    membership_end_date?: string | null;
    is_active: boolean;
    sort_order: number;
    best_value: boolean;
};

export type Product = {
    product_id: string;
    product_name: string;
    product_code?: string | null;
    product_sku?: string | null;
    product_description: string;
    category_id?: string | null;
    stock_quantity: number;
    uom: string;
    product_weight?: string | null;
    product_dimensions?: string | null;
    is_featured: boolean;
    is_visible: boolean;
    is_taxable: boolean;
    tax_rate_id: string;
    retail_price: string;
    sale_price: string;
    is_active: boolean;
    is_unlimited: boolean;
};

export type Category = {
    category_id: string;
    category_name: string;
    is_active: boolean;
    parent_id?: string | null;
    parent?: {
        category_id: string;
        category_name: string;
    } | null;
};

export type ProductDiscount = {
    product_discount_id: string;
    product_id: string;
    discount_type: "P" | "F";
    discount_amount: string;
    discount_start_date: string;
    discount_end_date: string;
    is_active: boolean;
    product?: {
        product_id: string;
        product_name: string;
    } | null;
};

export type Tax = {
    tax_rate_id: string;
    tax_name: string;
    tax_rate: string;
    is_active: boolean;
};

export type MembershipType = {
    membership_type_id: string;
    membership_type: string;
    is_active?: boolean;
};

export type OrderItem = {
    order_item_id: string;
    order_id: string;
    product_id: string;
    quantity: number;
    uom: string;
    unit_price: string;
    tax: string;
    discount: string;
    total_price: string;
    product?: {
        product_id: string;
        product_name: string;
        uom: string;
    } | null;
};

export type Order = {
    order_id: string;
    user_id: string;
    order_no: string;
    order_date: string;
    total_price: string;
    total_tax: string;
    total_discount: string;
    total_payment: string;
    shipping_method: string;
    shipping_address: string;
    billing_address: string;
    discount_code?: string | null;
    wallet_credit_used?: string | null;
    order_status:
        | "pending"
        | "processing"
        | "shipped"
        | "completed"
        | "refunded";
    order_items?: OrderItem[];
};

export type Payment = {
    payment_id: string;
    order_id: string;
    order_no: string;
    transaction_id: string;
    ref_no: string;
    payment_description: string;
    payment_method: string;
    payment_amount: string;
    payment_date: string;
    issuing_bank: string;
    payment_ref: string;
    bank_ref: string;
    cc_name: string;
    cc_number: string;
    payment_status: number;
};

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
