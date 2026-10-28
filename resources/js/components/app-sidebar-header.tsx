import { Link } from '@inertiajs/react';
import { MessageSquare, Upload } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-border/60 bg-background/80 px-4 backdrop-blur-sm md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" className="hidden sm:inline-flex" asChild>
                    <Link href="/uploads">
                        <Upload className="size-3.5" />
                        Unggah
                    </Link>
                </Button>
                <Button size="sm" asChild>
                    <Link href="/chat">
                        <MessageSquare className="size-3.5" />
                        Chat Aira
                    </Link>
                </Button>
            </div>
        </header>
    );
}
