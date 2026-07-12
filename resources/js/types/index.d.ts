export type Vendor = {
    vendor_id: number;
    vendor_name: string;
    email: string;
    contact_no: string;
    first_name: string;
    last_name: string;
    business_registration_number: string;
    company_profile: string;
    website?: string | null;
    social_medias?: Record<string, string> | string | null;
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
            contact_no?: string;
        },
    ];
};

export type Voucher = {
    voucher_id: number;
    vendor_name: string;
    voucher_name: string;
    voucher_short_description: string;
    voucher_description: string;
    voucher_value: number;
    what_you_get: string;
    voucher_code: string;
    voucher_discount: number;
    voucher_type: string;
    voucher_start_date: string;
    voucher_expiry_date: string;
    voucher_limit: number;
    voucher_claim_per_user: number;
    voucher_claim_period?: "week" | "month" | null;
    voucher_claim_per_period?: number | null;
    voucher_image_path?: string | null;
    voucher_status: boolean;
};

export type Discount = {
    discount_id: string;
    discount_code: string;
    user_id?: string | null;
    discount_name: string;
    discount_description: string;
    discount_type: "P" | "F";
    discount_amount: string;
    discount_start_date: string;
    discount_end_date: string;
    is_active: boolean;
    applies_to: "all" | "specific";
    discount_usage_limit: number;
    is_unlimited: boolean;
};

export type User = {
    user_id: string;
    first_name: string;
    last_name: string;
    email: string;
    contact_no: string;
    is_active: boolean;
    role: string;
    profile_picture?: string | null;
    credit_balance?: number;
};

export type ReferralByUser = {
    user_id: string;
    first_name: string;
    last_name: string;
    email: string;
    total_referrals: number;
    pending_count: number;
    qualified_count: number;
    rewarded_count: number;
    revoked_count: number;
    latest_referral_date?: string | null;
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
    active_pricing_tiers_count?: number;
    images?: ProductImage[];
    primary_image?: ProductImage | null;
    primaryImage?: ProductImage | null;
};

export type ProductImage = {
    product_image_id: string;
    product_id: string;
    image_url: string;
    image_path: string;
    mobile_image_url?: string | null;
    mobile_image_path?: string | null;
    is_active: boolean;
    is_primary: boolean;
    image_width?: number | null;
    image_height?: number | null;
    file_size_bytes?: number | null;
    mobile_file_size_bytes?: number | null;
};

export type ProductPricingTier = {
    product_pricing_tier_id: string;
    product_id: string;
    pricing_mode: "unit_price" | "percentage_discount";
    min_qty: number;
    unit_price?: string | null;
    discount_percent?: string | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
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

export type Charge = {
    charges_id: string;
    charges_type: string;
    charges_name: string;
    charges_rate: string;
    charges_description: string;
    charges_status: boolean;
    charges_start_date: string;
    charges_end_date: string;
    sort_order: number;
};

export type Contract = {
    tender_compartment_id: string;
    rack_name: string;
    location_name: string;
    vendor_location_name: string;
    compartment_label: string;
    vendor_name?: string | null;
    tender_status: "selected" | "paid";
    tender_start_date?: string | Date | null;
    tender_end_date?: string | Date | null;
};

export type Event = {
    event_id: string;
    event_name: string;
    event_image_path?: string | null;
    event_images?: {
        event_image_id: number;
        event_image_path: string;
        is_enabled?: boolean;
    }[];
    categories?: string[];
    event_start_date: string;
    event_end_date: string;
    event_start_time: string;
    event_end_time: string;
    event_location: string;
    event_description: string;
    location_name: string;
    location_latitude: string;
    location_longitude: string;
    place_id: string;
    registration_type?: "free" | "paid";
    base_price?: string;
    is_unlimited_seats?: boolean;
    seat_limit?: number | null;
    seat_hold_minutes?: number;
    rsvp_open_at?: string | null;
    rsvp_close_at?: string | null;
    require_questionnaire?: boolean;
    is_published: boolean;
    is_active: boolean;
};

export type MembershipType = {
    membership_type_id: string;
    membership_type: string;
    is_active?: boolean;
};

export type TransactionType = {
    id: number;
    transaction_type: string;
    transaction_name: string;
    credit_amount: number;
    effective_date: string;
    expire_date: string | null;
    is_active: boolean;
};

export type EventCategory = {
    event_category_id: number;
    event_id: string;
    category_id: string;
    event_name?: string;
    category_name?: string;
};

export type EvCategory = {
    category_id: string;
    category_name: string;
    is_active: boolean;
};

export type OrderItem = {
    order_item_id: string;
    order_id: string;
    product_id: string | null;
    line_type?: "product" | "event";
    source_id?: string | null;
    line_name?: string;
    line_description?: string | null;
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

export type QuestionTemplate = {
    question_template_id: string;
    created_by_user_id?: string | null;
    question_label: string;
    question_help_text?: string | null;
    question_type:
        | "short_text"
        | "long_text"
        | "single_select"
        | "multi_select"
        | "yes_no";
    is_required_default: boolean;
    is_active: boolean;
    options?: {
        event_question_option_id?: string;
        question_template_id?: string;
        event_questionnaire_id?: string | null;
        option_label: string;
        option_value: string;
        sort_order: number;
        is_active: boolean;
    }[];
    created_at?: string;
    updated_at?: string;
};

export type Order = {
    order_id: string;
    user_id: string;
    email: string;
    order_no: string;
    order_date: string;
    order_description?: string | null;
    total_price: string;
    total_charges: string;
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

export type Rack = {
    rack_id: string;
    vendor_location_id: string;
    rack_name: string;
    rack_type?: string | null;
    rack_capacity?: string | null;
    rack_rows: string;
    rack_columns: string;
    rack_status: "active" | "inactive";
    vendor_name?: string | null;
    vendor_location_name?: string | null;
};

export type Tender = {
    tender_id: string;
    rack_id: string;
    tender_status: "active" | "inactive";
    rack_name?: string | null;
    vendor_location_name?: string | null;
    created_at?: string | null;
};

export type TenderAvailability = {
    tender_id: string;
    rack_id: string;
    rack_name: string;
    vendor_location_id: string;
    vendor_location_name: string;
    open_compartments_count: number;
    tender_status: "active" | "inactive";
};

export type RackAvailability = {
    rack_id: string;
    rack_name: string;
    vendor_location_id: string;
    vendor_location_name: string;
    open_compartments_count: number;
    rack_status: "active" | "inactive";
};

export type TenderCompartment = {
    tender_compartment_id: string;
    rack_id?: string;
    compartment_id: string;
    vendor_id: string;
    bid_price: string;
    durations: number;
    tender_status: "pending" | "selected" | "paid" | "expired" | "rejected";
    selected_at?: string | null;
    tender_start_date?: string | null;
    tender_end_date?: string | null;
    vendor_name?: string | null;
    rack_name?: string | null;
    compartment_label?: string | null;
    vendor_location_name?: string | null;
};

export type Compartment = {
    compartment_id: string;
    rack_id: string;
    label: string;
    row_index: number;
    column_index: number;
    size_dimensions?: string | null;
    min_price: string;
    min_month: number;
    compartment_status: "open" | "reviewing" | "allocated" | "closed";
    is_active: boolean;
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
