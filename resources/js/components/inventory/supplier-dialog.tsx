import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/suppliers';

export type SupplierRecord = {
    id: number;
    name: string;
    code: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
};

export function SupplierDialog({ supplier, open, onOpenChange }: { supplier?: SupplierRecord; open: boolean; onOpenChange: (open: boolean) => void }) {
    const form = useForm({
        name: supplier?.name ?? '',
        code: supplier?.code ?? '',
        contact_person: supplier?.contact_person ?? '',
        phone: supplier?.phone ?? '',
        email: supplier?.email ?? '',
        address: supplier?.address ?? '',
        notes: supplier?.notes ?? '',
        is_active: supplier?.is_active ?? true,
    });

    const text = (key: 'name' | 'code' | 'contact_person' | 'phone' | 'email', label: string) => (
        <div className="space-y-2">
            <Label htmlFor={`supplier-${key}`}>{label}</Label>
            <Input id={`supplier-${key}`} value={form.data[key]} onChange={(event) => form.setData(key, key === 'code' ? event.target.value.toUpperCase() : event.target.value)} />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
                        supplier ? form.put(update.url(supplier.id), options) : form.post(store.url(), options);
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>{supplier ? `${supplier.name} সম্পাদনা` : 'নতুন সরবরাহকারী'}</DialogTitle>
                        <DialogDescription>সরবরাহকারীর নাম স্টক ইন আর বদলির দাবিতে দেখা যাবে।</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-[1fr_8rem]">
                        {text('name', 'নাম')}
                        {text('code', 'কোড')}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {text('contact_person', 'যোগাযোগের ব্যক্তি')}
                        {text('phone', 'ফোন')}
                    </div>
                    {text('email', 'ইমেইল')}
                    <div className="space-y-2">
                        <Label htmlFor="supplier-address">ঠিকানা</Label>
                        <Textarea id="supplier-address" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} rows={2} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="supplier-notes">নোট</Label>
                        <Textarea id="supplier-notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} rows={2} />
                    </div>
                    <label className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm">
                        চালু আছে
                        <Switch checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                    </label>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            বাতিল
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {supplier ? 'সেভ করুন' : 'সরবরাহকারী যোগ করুন'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
