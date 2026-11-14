import '@inertiajs/core';

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface CurrentCommunity {
    id: number;
    name: string;
    slug: string;
    role: 'resident' | 'manager';
}

export interface CommunityListItem extends CurrentCommunity {
    description: string | null;
    dashboardUrl: string;
}

export interface CommunityMember extends User {
    role: CurrentCommunity['role'];
}

export interface SharedProps {
    appName: string;
    auth: { user: User | null };
    community: CurrentCommunity | null;
    urls: {
        login: string;
        loginStore: string;
        logout: string;
        communities: string;
        dashboard: string | null;
        members: string | null;
        requests: string | null;
    };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
        flashDataType: { success?: string };
    }
}

export type RequestStatus = 'new' | 'in_progress' | 'resolved';

export interface RequestAuthor {
    id: number;
    name: string;
}

export interface ServiceRequestListItem {
    id: number;
    title: string;
    status: RequestStatus;
    created_at: string;
    updated_at: string;
    author: RequestAuthor | null;
    showUrl: string;
}

export interface PaginatedRequests {
    data: ServiceRequestListItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface RequestStatusHistory {
    id: number;
    from_status: RequestStatus | null;
    to_status: RequestStatus;
    author: RequestAuthor | null;
    created_at: string;
}

export interface RequestMessage {
    id: number;
    body: string;
    author: RequestAuthor;
    role: 'resident' | 'manager' | null;
    created_at: string;
}

export interface ServiceRequest {
    id: number;
    title: string;
    description: string;
    status: RequestStatus;
    author: RequestAuthor;
    created_at: string;
    updated_at: string;
    resolved_at: string | null;
    allowed_statuses: RequestStatus[];
    statusHistories: RequestStatusHistory[];
    messages?: RequestMessage[];
}
