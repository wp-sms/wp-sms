import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';

const Providers: React.FC<PropsWithChildren<unknown>> = ({ children }) => {
    const client = new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60 * 1000 * 5,
                refetchOnWindowFocus: false,
                retry: 1,
                experimental_prefetchInRender: true,
            },
        },
    });

    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
};

export default Providers;
