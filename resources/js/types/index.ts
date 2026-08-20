export interface AuthUser {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar_url: string | null;
    is_creator: boolean;
    is_affiliate: boolean;
    is_admin: boolean;
    is_super_admin: boolean;
    roles: string[];
    email_verified: boolean;
}

export interface AuthStore {
    id: number;
    username: string;
    name: string;
    is_published: boolean;
    public_url: string;
    avatar_url: string | null;
}

export interface PlanSnapshot {
    slug: string;
    name: string;
    transaction_fee_percent: number;
    features: Record<string, { enabled: boolean; limit: number | null }>;
}

export interface NotificationItem {
    id: string;
    title: string;
    message: string;
    url: string | null;
    created_at: string;
}

export interface PageProps {
    app: { name: string; demo: boolean };
    auth: {
        user: AuthUser | null;
        store: AuthStore | null;
        plan: PlanSnapshot | null;
        impersonating: boolean;
    };
    notifications: NotificationItem[];
    flash: {
        success: string | null;
        error: string | null;
        info: string | null;
    };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface StoreTheme {
    primary_color: string;
    accent_color: string;
    background_type: 'solid' | 'gradient' | 'image';
    background_value: string;
    font_family: string;
    button_style: 'rounded' | 'pill' | 'square';
    card_style: 'soft' | 'outline' | 'flat';
    product_layout: 'grid' | 'list';
    color_scheme: 'light' | 'dark' | 'auto';
}

export interface StorefrontProduct {
    id: number;
    slug: string;
    type: string;
    type_label: string;
    name: string;
    short_description: string | null;
    thumbnail_url: string | null;
    price: number;
    compare_at_price: number | null;
    discount_percent: number;
    is_pay_what_you_want: boolean;
    minimum_price: number | null;
    external_url: string | null;
    is_buyable: boolean;
    sales_count?: number;
}

export interface StorefrontBlock {
    id: number;
    type: string;
    title: string | null;
    content: Record<string, any>;
    style: Record<string, any>;
    visible_mobile: boolean;
    visible_desktop: boolean;
    animation: string | null;
}
