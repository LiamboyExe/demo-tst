import type { User } from './auth';

export type MembershipType =
    | 'High Table Council'
    | 'Shot Caller'
    | 'Alas'
    | 'Pointman'
    | 'Carnal'
    | 'Carnala';
export type MemberStatus = 'Active' | 'Inactive' | 'Pending';
export type NfcCardStatus = 'Active' | 'Inactive' | 'Lost';

export type Hood = {
    id: number;
    branch_id: number;
    name: string;
    members_count?: number;
    created_at: string;
    updated_at: string;
};

export type Branch = {
    id: number;
    name: string;
    location: string;
    manager: string;
    phone: string;
    email: string;
    members_count?: number;
    active_members_count?: number;
    inactive_members_count?: number;
    pending_members_count?: number;
    hoods_count?: number;
    local_admins_count?: number;
    admins?: User[];
    hoods?: Hood[];
    created_at: string;
    updated_at: string;
};

export type Member = {
    id: number;
    branch_id: number;
    hood_id: number | null;
    first_name: string;
    last_name: string;
    alias: string | null;
    birthdate: string | null;
    age: number | null;
    avatar_url: string | null;
    full_name: string;
    initials: string;
    email: string;
    phone: string | null;
    address: string | null;
    membership_type: MembershipType;
    status: MemberStatus;
    join_date: string;
    profile_slug: string;
    approved: boolean;
    branch?: Branch;
    hood?: Hood | null;
    nfc_card?: NfcCard | null;
    user?: MemberUser | null;
    profile_url?: string;
    created_at: string;
    updated_at: string;
};

export type NfcCard = {
    id: number;
    member_id: number;
    card_uid: string;
    profile_url: string;
    card_status: NfcCardStatus;
    issued_date: string;
    notes: string | null;
    member?: Member;
    created_at: string;
    updated_at: string;
};

export type MemberUser = {
    id: number;
    username: string | null;
    activated_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
