import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M10.5 0h11C27.299 0 32 4.701 32 10.5v11C32 27.299 27.299 32 21.5 32h-11C4.701 32 0 27.299 0 21.5v-11C0 4.701 4.701 0 10.5 0Zm2.9 7.4a1.45 1.45 0 0 0-2.9 0v10.85a6.1 6.1 0 1 0 2.9-5.185V7.4Zm3.2 8.05a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Z"
            />
        </svg>
    );
}
