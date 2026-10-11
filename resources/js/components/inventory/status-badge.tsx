import { cn } from '@/lib/utils';

const TONES: Record<string, string> = {
    pending_review: 'bg-inspection-soft text-inspection-ink ring-inspection/30',
    reviewed: 'bg-sky-soft text-sky ring-sky/30',
    supplier_agreed: 'bg-accent text-accent-foreground ring-primary/25',
    approved: 'bg-available-soft text-available ring-available/30',
    dispatched: 'bg-supplier-soft text-supplier ring-supplier/30',
    partially_received: 'bg-supplier-soft text-supplier ring-supplier/30',
    closed: 'bg-muted text-muted-foreground ring-border',
    rejected: 'bg-damaged-soft text-damaged ring-damaged/30',
    pending_inspection: 'bg-inspection-soft text-inspection-ink ring-inspection/30',
    partially_inspected: 'bg-sky-soft text-sky ring-sky/30',
    inspected: 'bg-available-soft text-available ring-available/30',
    open: 'bg-damaged-soft text-damaged ring-damaged/30',
    in_claim: 'bg-supplier-soft text-supplier ring-supplier/30',
    resolved: 'bg-muted text-muted-foreground ring-border',
    pending: 'bg-inspection-soft text-inspection-ink ring-inspection/30',
    active: 'bg-available-soft text-available ring-available/30',
    archived: 'bg-muted text-muted-foreground ring-border',
    inactive: 'bg-muted text-muted-foreground ring-border',
};

const LABELS: Record<string, string> = {
    active: 'চালু',
    archived: 'আর্কাইভ',
    inactive: 'বন্ধ',
    pending: 'অনুমোদনের অপেক্ষায়',
    approved: 'অনুমোদিত',
    rejected: 'বাতিল',
};

export function StatusBadge({ status, label, className }: { status: string; label?: string; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset', TONES[status] ?? 'bg-muted text-muted-foreground ring-border', className)}>
            <span className="size-1.5 rounded-full bg-current" />
            {label ?? LABELS[status] ?? status}
        </span>
    );
}

const MOVEMENT_TONES: Record<string, string> = {
    opening: 'bg-available-soft text-available',
    stock_in: 'bg-available-soft text-available',
    stock_out: 'bg-accent text-primary',
    return_receipt: 'bg-inspection-soft text-inspection-ink',
    return_inspection: 'bg-sky-soft text-sky',
    damage: 'bg-damaged-soft text-damaged',
    supplier_dispatch: 'bg-supplier-soft text-supplier',
    supplier_receipt: 'bg-supplier-soft text-supplier',
    supplier_write_off: 'bg-rose-soft text-rose',
    adjustment: 'bg-muted text-foreground',
};

export function MovementType({ type, label }: { type: string; label: string }) {
    return <span className={cn('inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold whitespace-nowrap', MOVEMENT_TONES[type] ?? 'bg-muted')}>{label}</span>;
}
