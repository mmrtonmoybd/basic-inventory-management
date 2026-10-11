import {
    createSortedRowModel,
    rowSortingFeature,
    sortFns,
    tableFeatures,
    useTable,
    type CellData,
    type ColumnDef as CoreColumnDef,
    type RowData,
    type TableFeatures,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

const features = tableFeatures({ rowSortingFeature, sortedRowModel: createSortedRowModel(), sortFns });

type Features = typeof features;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type ColumnDef<TData extends RowData> = CoreColumnDef<Features, TData, any>;

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<in out TFeatures extends TableFeatures, in out TData extends RowData, TValue extends CellData = CellData> {
        numeric?: boolean;
        className?: string;
    }
}

/**
 * Shared table: client-side sorting on the current page, numeric columns right-aligned with tabular figures.
 */
export function DataTable<TData extends RowData>({
    columns,
    data,
    empty,
    footer,
    onRowClick,
    sortable = true,
    className,
}: {
    columns: ColumnDef<TData>[];
    data: TData[];
    empty?: ReactNode;
    footer?: ReactNode;
    onRowClick?: (row: TData) => void;
    sortable?: boolean;
    className?: string;
}) {
    const table = useTable({ features, columns, data, enableSorting: sortable });

    return (
        <Table className={className}>
            <TableHeader>
                {table.getHeaderGroups().map((group) => (
                    <TableRow key={group.id} className="bg-muted/50 hover:bg-muted/50">
                        {group.headers.map((header) => {
                            const meta = header.column.columnDef.meta;
                            const canSort = header.column.getCanSort();
                            const sorted = header.column.getIsSorted();

                            return (
                                <TableHead key={header.id} className={cn(meta?.numeric && 'text-right', meta?.className)}>
                                    {header.isPlaceholder ? null : canSort ? (
                                        <button
                                            type="button"
                                            onClick={header.column.getToggleSortingHandler()}
                                            className={cn('inline-flex items-center gap-1 hover:text-foreground', meta?.numeric && 'flex-row-reverse')}
                                        >
                                            <table.FlexRender header={header} />
                                            {sorted === 'asc' ? <ArrowUp className="size-3" /> : sorted === 'desc' ? <ArrowDown className="size-3" /> : <ChevronsUpDown className="size-3 opacity-40" />}
                                        </button>
                                    ) : (
                                        <table.FlexRender header={header} />
                                    )}
                                </TableHead>
                            );
                        })}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {table.getRowModel().rows.length === 0 ? (
                    <TableRow className="hover:bg-transparent">
                        <TableCell colSpan={columns.length} className="p-0 whitespace-normal">
                            {empty ?? <p className="py-10 text-center text-sm text-muted-foreground">এখানে এখনো কিছু নেই।</p>}
                        </TableCell>
                    </TableRow>
                ) : (
                    table.getRowModel().rows.map((row) => (
                        <TableRow
                            key={row.id}
                            onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                            className={cn('transition-colors hover:bg-accent/50', onRowClick && 'cursor-pointer')}
                        >
                            {row.getAllCells().map((cell) => {
                                const meta = cell.column.columnDef.meta;

                                return (
                                    <TableCell key={cell.id} className={cn(meta?.numeric && 'text-right tabular-nums', meta?.className)}>
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                );
                            })}
                        </TableRow>
                    ))
                )}
            </TableBody>
            {footer && <TableFooter>{footer}</TableFooter>}
        </Table>
    );
}
