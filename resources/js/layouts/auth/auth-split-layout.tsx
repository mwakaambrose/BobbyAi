import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col justify-between border-r border-border bg-sidebar p-10 lg:flex">
                <Link
                    href={home()}
                    className="relative z-20 flex items-center gap-2.5"
                >
                    <span className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="font-serif text-lg font-medium tracking-[-0.01em]">
                        {name}
                    </span>
                </Link>

                <blockquote className="relative z-20 max-w-md space-y-2">
                    <p className="font-serif text-xl leading-relaxed text-balance italic">
                        Nothing reaches LinkedIn until you approve the exact
                        words.
                    </p>
                    <footer className="font-mono text-[11px] text-muted-foreground">
                        The approval gate, always on
                    </footer>
                </blockquote>
            </div>

            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <span className="flex aspect-square size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-6 fill-current" />
                        </span>
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="font-serif text-2xl font-medium tracking-[-0.01em]">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
