import type { Branch } from './membership';

export type ReportKey = 'summary' | 'territories' | 'registrations' | 'nfc';
export type ReportGroup = 'day' | 'month' | 'year';

export type ReportFilters = {
    report: ReportKey;
    start_date: string | null;
    end_date: string | null;
    branch: number | null;
    hood: number | null;
    membership_type: string | null;
    status: string | null;
    card_status: string | null;
    group: ReportGroup;
};

export type ReportOptions = {
    branches: Branch[];
    membershipTypes: string[];
    statuses: string[];
    cardStatuses: string[];
    groups: ReportGroup[];
    isMainAdmin: boolean;
};

export type ReportStats = Record<string, number>;

export type SummaryReportData = {
    stats: {
        total: number;
        active: number;
        inactive: number;
        pending: number;
        registered: number;
        archived: number;
    };
    territories: Array<{
        id: number;
        name: string;
        total: number;
        active: number;
        inactive: number;
        pending: number;
    }>;
    types: Array<{
        type: string;
        total: number;
    }>;
};

export type TerritoryReportData = {
    stats: {
        total: number;
        active: number;
        inactive: number;
        pending: number;
        hoods: number;
        territories: number;
    };
    rows: Array<{
        territory_id: number;
        territory: string;
        hood_id: number | null;
        hood: string;
        total: number;
        active: number;
        inactive: number;
        pending: number;
    }>;
    territoryTotals: Array<{
        id: number;
        name: string;
        total: number;
        active: number;
        inactive: number;
        pending: number;
        hoods: number;
    }>;
};

export type RegistrationRow = {
    id: number;
    full_name: string;
    membership_type: string;
    join_date: string;
    status: string;
    branch?: { id: number; name: string } | null;
    hood?: { id: number; name: string } | null;
};

export type RegistrationReportData = {
    stats: {
        total: number;
        active: number;
        inactive: number;
        pending: number;
        periods: number;
    };
    trend: Array<{
        period: string;
        label: string;
        total: number;
        active: number;
        inactive: number;
        pending: number;
    }>;
    records: {
        data: RegistrationRow[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

export type NfcReportData = {
    stats: {
        total: number;
        assigned: number;
        unassigned: number;
        members: number;
        active: number;
        inactive: number;
        lost: number;
    };
    cards: Array<{
        id: number;
        member_name: string;
        member_status: string | null;
        card_uid: string;
        card_status: string;
        issued_date: string | null;
        territory: string | null;
        hood: string | null;
    }>;
};

export type ReportData =
    | SummaryReportData
    | TerritoryReportData
    | RegistrationReportData
    | NfcReportData;
