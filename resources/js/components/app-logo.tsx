import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

/**
 * The mark sits as ink on the page rather than inside a filled tile, so the
 * wordmark reads as a masthead instead of an app icon.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-7 shrink-0 items-center justify-center text-sidebar-primary">
                <AppLogoIcon className="size-6 fill-current" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left">
                <span className="display truncate text-[1.0625rem] leading-tight text-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}
