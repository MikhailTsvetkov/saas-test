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
    };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
