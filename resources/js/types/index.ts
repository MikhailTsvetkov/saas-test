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

export interface SharedProps {
    appName: string;
    auth: { user: User | null };
    community: CurrentCommunity | null;
    urls: { login: string; loginStore: string; logout: string };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
