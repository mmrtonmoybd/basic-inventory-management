import type { Condition } from '@/types';

export const CONDITIONS: { key: Condition; label: string; bar: string; text: string; soft: string; physical: boolean }[] = [
    { key: 'available', label: 'বিক্রয়যোগ্য', bar: 'bg-available', text: 'text-available', soft: 'bg-available-soft', physical: true },
    { key: 'inspection', label: 'পরীক্ষাধীন', bar: 'bg-inspection', text: 'text-inspection-ink', soft: 'bg-inspection-soft', physical: true },
    { key: 'damaged', label: 'ক্ষতিগ্রস্ত', bar: 'bg-damaged', text: 'text-damaged', soft: 'bg-damaged-soft', physical: true },
    { key: 'with_supplier', label: 'সরবরাহকারীর কাছে', bar: 'bg-supplier', text: 'text-supplier', soft: 'bg-supplier-soft', physical: false },
];

export function conditionMeta(key: Condition) {
    return CONDITIONS.find((condition) => condition.key === key)!;
}

export function conditionByLabel(label: string | null | undefined) {
    return CONDITIONS.find((condition) => condition.label === label);
}
