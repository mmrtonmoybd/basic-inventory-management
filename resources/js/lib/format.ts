const TIMEZONE = 'Asia/Dhaka';
const LOCALE = 'bn-BD';

const integer = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 0 });
const BENGALI_DIGITS = '০১২৩৪৫৬৭৮৯';

/** A whole number in Bengali digits, e.g. 1250 → "১,২৫০". */
export function qty(value: number | null | undefined): string {
    return integer.format(value ?? 0);
}

export function signedQty(value: number): string {
    return value > 0 ? `+${qty(value)}` : value < 0 ? `−${qty(Math.abs(value))}` : qty(0);
}

export function compactQty(value: number): string {
    return new Intl.NumberFormat(LOCALE, { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

/** Converts any digits in a string to Bengali digits (for codes and dates shown in text). */
export function bnDigits(value: string | number): string {
    return String(value).replace(/[0-9]/g, (digit) => BENGALI_DIGITS[Number(digit)]);
}

/**
 * Keeps only digits from typed input, accepting both Bengali (০-৯) and English (0-9) digits,
 * and returns them as English digits for the server.
 */
export function onlyDigits(value: string): string {
    return value.replace(/[০-৯]/g, (digit) => String(BENGALI_DIGITS.indexOf(digit))).replace(/\D/g, '');
}

/** Formats a YYYY-MM-DD business date without timezone shifting. */
export function businessDate(value: string | null | undefined, style: 'short' | 'long' = 'short'): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));

    return new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        year: style === 'long' ? 'numeric' : undefined,
        timeZone: 'UTC',
    }).format(date);
}

/** Formats an ISO timestamp in Dhaka time. */
export function dateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: TIMEZONE,
    }).format(new Date(value));
}

export function longDate(date: Date = new Date()): string {
    return new Intl.DateTimeFormat(LOCALE, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: TIMEZONE }).format(date);
}

export function relativeTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat('bn', { numeric: 'auto' });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'এইমাত্র';
}

export function uuid(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

const ACTION_LABELS: Record<string, string> = {
    submitted: 'দাবি জমা দেওয়া হয়েছে',
    reviewed: 'ম্যানেজার যাচাই করেছেন',
    supplier_agreed: 'সরবরাহকারীর সম্মতি লেখা হয়েছে',
    approved: 'চূড়ান্ত অনুমোদন',
    rejected: 'বাতিল',
    dispatched: 'সরবরাহকারীর কাছে পাঠানো হয়েছে',
    received: 'বদলি মাল গ্রহণ',
    closed_short: 'ঘাটতিসহ বন্ধ',
    requested: 'অনুরোধ করা হয়েছে',
};

export function actionLabel(action: string): string {
    return ACTION_LABELS[action] ?? action.replace(/_/g, ' ');
}

/** Bengali display names for the built-in roles; custom roles show as entered. */
const ROLE_LABELS: Record<string, string> = {
    'Super Admin': 'সুপার অ্যাডমিন',
    'Inventory Manager': 'ইনভেন্টরি ম্যানেজার',
    'Stock Operator': 'স্টক অপারেটর',
    'Return/QC Operator': 'রিটার্ন/কিউসি অপারেটর',
    'Supplier Coordinator': 'সরবরাহকারী সমন্বয়ক',
    'Exchange Approver': 'বদলি অনুমোদনকারী',
    'Report Viewer': 'রিপোর্ট দর্শক',
};

export function roleLabel(role: string): string {
    return ROLE_LABELS[role] ?? role;
}

export function pieces(value: number): string {
    return `${qty(value)} পিস`;
}
