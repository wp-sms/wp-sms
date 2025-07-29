import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { Toaster } from './components/ui/sonner';

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

    return (
        <QueryClientProvider client={client}>
            <Toaster />
            {children}
        </QueryClientProvider>
    );
};

export default Providers;
