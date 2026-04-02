import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { WalletProvider } from '@/hooks/useWallet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Toaster } from '@/components/ui/sonner';
import './index.css';

createInertiaApp({
    title: (title) => {
        const siteName = window.__INERTIA_SITE_NAME || 'Visadorm P2P';
        return title ? `${title} — ${siteName}` : siteName;
    },
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx');
        const match = pages[`./pages/${name}.jsx`];
        if (!match) {
            throw new Error(`Page not found: ${name}`);
        }
        return match().then((module) => {
            const page = module.default;
            // Preserve persistent layouts
            if (page.layout) {
                return { default: page, layout: page.layout };
            }
            return module;
        });
    },
    setup({ el, App, props }) {
        // Make site name available for title resolver
        window.__INERTIA_SITE_NAME = props.initialPage.props.site?.name || 'Visadorm P2P';

        createRoot(el).render(
            <WalletProvider blockchain={props.initialPage.props.blockchain}>
                <TooltipProvider>
                    <App {...props} />
                    <Toaster />
                </TooltipProvider>
            </WalletProvider>
        );
    },
});
