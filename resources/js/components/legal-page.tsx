import { Head } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

/** A legal page: plain text, with a banner while it's still a draft. */
export function LegalPage({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <>
            <Head title={title} />
            <main className="mx-auto max-w-2xl space-y-4 p-4 text-sm leading-relaxed sm:p-6 [&_h2]:pt-2 [&_h2]:text-base [&_h2]:font-medium [&_ul]:list-disc [&_ul]:pl-5">
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                    Draft, to be reviewed by a lawyer before launch. Items in
                    [brackets] are still to be filled in.
                </p>
                {children}
            </main>
        </>
    );
}
