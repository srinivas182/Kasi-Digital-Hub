import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface Column<Row> {
    key: string;
    header: string;
    cell: (row: Row) => ReactNode;
    /** Hide this column in the stacked phone view (e.g. secondary details). */
    hideOnMobile?: boolean;
    className?: string;
}

export interface DataTableProps<Row> {
    caption: string;
    columns: Column<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string | number;
    empty?: ReactNode;
}

/**
 * Table on desktop, stacked cards on phones (no sideways scrolling).
 * The caption is required for screen readers; it is visually hidden.
 */
export function DataTable<Row>({ caption, columns, rows, rowKey, empty }: DataTableProps<Row>) {
    if (rows.length === 0 && empty) return <>{empty}</>;

    return (
        <>
            <div className="rounded-card border-line bg-surface hidden overflow-hidden border md:block">
                <table className="w-full text-sm">
                    <caption className="sr-only">{caption}</caption>
                    <thead className="bg-surface-muted text-fg-muted text-left text-xs font-semibold uppercase">
                        <tr>
                            {columns.map((column) => (
                                <th key={column.key} scope="col" className={cn('px-4 py-3', column.className)}>
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={rowKey(row)} className="border-line border-t">
                                {columns.map((column) => (
                                    <td key={column.key} className={cn('text-fg px-4 py-3', column.className)}>
                                        {column.cell(row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul className="flex flex-col gap-3 md:hidden" aria-label={caption}>
                {rows.map((row) => (
                    <li key={rowKey(row)} className="rounded-card border-line bg-surface border p-4">
                        <dl className="flex flex-col gap-2">
                            {columns
                                .filter((column) => !column.hideOnMobile)
                                .map((column) => (
                                    <div key={column.key} className="flex items-start justify-between gap-4">
                                        <dt className="text-fg-muted text-xs font-semibold uppercase">
                                            {column.header}
                                        </dt>
                                        <dd className="text-fg text-right text-sm">{column.cell(row)}</dd>
                                    </div>
                                ))}
                        </dl>
                    </li>
                ))}
            </ul>
        </>
    );
}
