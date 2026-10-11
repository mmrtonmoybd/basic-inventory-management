import { Link, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    BarChart3,
    Boxes,
    ClipboardCheck,
    FileUp,
    FlaskConical,
    History,
    LayoutDashboard,
    ListChecks,
    PackageX,
    Repeat,
    Ruler,
    Scale,
    ShieldCheck,
    Shirt,
    Tags,
    Truck,
    Undo2,
    UserCog,
    Warehouse,
    type LucideIcon,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { bnDigits } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { index as adjustmentsIndex } from '@/routes/adjustments';
import { index as attributesIndex } from '@/routes/admin/attributes';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as demoDataIndex } from '@/routes/admin/demo-data';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import { index as warehousesIndex } from '@/routes/admin/warehouses';
import { index as claimsIndex } from '@/routes/claims';
import { index as damageIndex } from '@/routes/damage';
import { create as importCreate } from '@/routes/import';
import { index as movementsIndex } from '@/routes/movements';
import { index as productsIndex } from '@/routes/products';
import { index as reconciliationIndex } from '@/routes/reconciliation';
import { index as reportsIndex } from '@/routes/reports';
import { index as returnsIndex } from '@/routes/returns';
import { create as stockCreate } from '@/routes/stock';
import { index as suppliersIndex } from '@/routes/suppliers';

type Item = { title: string; href: string; icon: LucideIcon; permission?: string; badge?: number; exact?: boolean };

const GROUP_TONES: Record<string, string> = {
    overview: 'from-indigo-400 to-violet-500',
    stock: 'from-emerald-400 to-teal-500',
    quality: 'from-amber-400 to-orange-500',
    suppliers: 'from-violet-400 to-fuchsia-500',
    control: 'from-sky-400 to-blue-500',
    setup: 'from-slate-400 to-slate-500',
};

export function AppSidebar() {
    const can = useCan();
    const { badges } = usePage().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const groups: { key: string; label: string; items: Item[] }[] = [
        {
            key: 'overview',
            label: 'সারসংক্ষেপ',
            items: [
                { title: 'ড্যাশবোর্ড', href: dashboard.url(), icon: LayoutDashboard },
                { title: 'পণ্য', href: productsIndex.url(), icon: Shirt, permission: 'products.view' },
            ],
        },
        {
            key: 'stock',
            label: 'স্টক',
            items: [
                { title: 'স্টক ইন', href: stockCreate.url('in'), icon: ArrowDownToLine, permission: 'stock.in', exact: true },
                { title: 'স্টক আউট', href: stockCreate.url('out'), icon: ArrowUpFromLine, permission: 'stock.out', exact: true },
                { title: 'শুরুর মজুদ', href: stockCreate.url('opening'), icon: Boxes, permission: 'stock.opening', exact: true },
                { title: 'স্টক লেনদেন', href: movementsIndex.url(), icon: History, permission: 'movements.view' },
            ],
        },
        {
            key: 'quality',
            label: 'মান যাচাই',
            items: [
                { title: 'কাস্টমার রিটার্ন', href: returnsIndex.url(), icon: Undo2, permission: 'movements.view', badge: badges?.returns },
                { title: 'ক্ষতিগ্রস্ত মাল', href: damageIndex.url(), icon: PackageX, permission: 'movements.view' },
            ],
        },
        {
            key: 'suppliers',
            label: 'সরবরাহকারী',
            items: [
                { title: 'বদলির দাবি', href: claimsIndex.url(), icon: Repeat, permission: 'claims.view', badge: badges?.claims },
                { title: 'সরবরাহকারী', href: suppliersIndex.url(), icon: Truck, permission: 'suppliers.view' },
            ],
        },
        {
            key: 'control',
            label: 'নিয়ন্ত্রণ ও রিপোর্ট',
            items: [
                { title: 'স্টক সমন্বয়', href: adjustmentsIndex.url(), icon: Scale, permission: 'movements.view', badge: badges?.adjustments },
                { title: 'রিপোর্ট', href: reportsIndex.url(), icon: BarChart3, permission: 'reports.view' },
                { title: 'হিসাব মিলানো', href: reconciliationIndex.url(), icon: ClipboardCheck, permission: 'ledger.reconcile' },
                { title: 'কাজের ইতিহাস', href: activityIndex.url(), icon: ListChecks, permission: 'activity.view' },
                { title: 'CSV আমদানি', href: importCreate.url(), icon: FileUp, permission: 'stock.import' },
            ],
        },
        {
            key: 'setup',
            label: 'সেটআপ',
            items: [
                { title: 'সাইজ, রং ইত্যাদি', href: attributesIndex.url(), icon: Ruler, permission: 'catalog.manage' },
                { title: 'ক্যাটাগরি', href: categoriesIndex.url(), icon: Tags, permission: 'catalog.manage' },
                { title: 'গুদাম', href: warehousesIndex.url(), icon: Warehouse, permission: 'catalog.manage' },
                { title: 'ব্যবহারকারী', href: usersIndex.url(), icon: UserCog, permission: 'users.manage' },
                { title: 'রোল ও অনুমতি', href: rolesIndex.url(), icon: ShieldCheck, permission: 'users.manage' },
                { title: 'ডেমো ডেটা', href: demoDataIndex.url(), icon: FlaskConical, permission: '*' },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" className="[&_[data-sidebar=sidebar]]:bg-gradient-to-b [&_[data-sidebar=sidebar]]:from-[#1e1b4b] [&_[data-sidebar=sidebar]]:via-[#231f5c] [&_[data-sidebar=sidebar]]:to-[#2e1065]">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-white/5">
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0">
                {groups.map((group) => {
                    const items = group.items.filter((item) => !item.permission || can(item.permission));

                    if (items.length === 0) {
                        return null;
                    }

                    return (
                        <SidebarGroup key={group.key} className="py-1.5">
                            <SidebarGroupLabel className="text-[11px] font-semibold text-sidebar-foreground/55">{group.label}</SidebarGroupLabel>
                            <SidebarMenu>
                                {items.map((item) => {
                                    const active = item.exact || item.href === dashboard.url() ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);

                                    return (
                                        <SidebarMenuItem key={item.title}>
                                            <SidebarMenuButton
                                                asChild
                                                isActive={active}
                                                tooltip={{ children: item.title }}
                                                className={cn(
                                                    'relative h-9 gap-2.5 rounded-lg text-[0.9rem] text-sidebar-foreground transition-all duration-200 hover:translate-x-0.5 hover:bg-white/8 hover:text-white',
                                                    'data-[active=true]:bg-white/12 data-[active=true]:font-semibold data-[active=true]:text-white data-[active=true]:shadow-[inset_3px_0_0_0_#a5b4fc]',
                                                )}
                                            >
                                                <Link href={item.href} prefetch>
                                                    <span className={cn('flex size-6 shrink-0 items-center justify-center rounded-md bg-gradient-to-br text-white shadow-sm', GROUP_TONES[group.key], !active && 'opacity-80')}>
                                                        <item.icon className="size-3.5" />
                                                    </span>
                                                    <span>{item.title}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                            {item.badge ? <SidebarMenuBadge className="top-2 rounded-full bg-amber-400 px-1.5 text-[11px] font-bold text-amber-950">{bnDigits(item.badge)}</SidebarMenuBadge> : null}
                                        </SidebarMenuItem>
                                    );
                                })}
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                })}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
