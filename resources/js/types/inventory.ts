export type Condition = 'available' | 'inspection' | 'damaged' | 'with_supplier';

export type StockSplit = {
    available: number;
    inspection: number;
    damaged: number;
    with_supplier: number;
    physical?: number;
};

export type Option = { id: number; name: string; code?: string; is_default?: boolean };

export type SelectOption = { value: string; label: string };

export type PaginationLink = { url: string | null; label: string; active: boolean };

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AttributeValue = { id: number; value: string; code?: string | null; swatch: string | null };

export type Attribute = { id: number; name: string; is_color: boolean; values: AttributeValue[] };

export type Variant = {
    id: number;
    product_id: number;
    sku: string;
    barcode: string | null;
    name: string;
    status: 'active' | 'archived';
    low_stock_threshold: number;
    value_ids?: number[];
    product_name?: string;
    stock?: StockSplit & { physical: number };
};

export type ProductLookup = { id: number; name: string; code: string; variants_count: number };

export type VariantLookup = {
    product: { id: number; name: string; code: string };
    attributes: Attribute[];
    variants: Variant[];
};

export type AppNotification = {
    id: string;
    data: { kind: string; title: string; body: string; url: string };
    read_at: string | null;
    created_at: string | null;
};

export type HistoryRecord = {
    id: number;
    action: string;
    from_status?: string | null;
    to_status?: string | null;
    comment: string | null;
    user: string | null;
    created_at: string | null;
};
