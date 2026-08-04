import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

/**
 * The mark carries the brand colour on its own, so it needs no tile behind it.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-7 shrink-0 items-center justify-center text-sidebar-primary">
                <AppLogoIcon className="size-6 fill-current" />
            </div>
            <div className="ml-1.5 grid flex-1 text-left">
                <span className="display truncate text-sm leading-tight text-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}
