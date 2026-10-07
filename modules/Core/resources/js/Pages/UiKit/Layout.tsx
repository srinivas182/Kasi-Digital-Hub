import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Card, CardTitle, StatCard } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { ScoreRing } from '@/components/ui/ScoreRing';
import { toast } from '@/components/ui/Toast';
import { AppLayout } from '@/layouts/AppLayout';
import { AuthLayout } from '@/layouts/AuthLayout';
import { ConsoleLayout } from '@/layouts/ConsoleLayout';
import { KioskLayout } from '@/layouts/KioskLayout';
import { PortalLayout } from '@/layouts/PortalLayout';
import { PublicLayout } from '@/layouts/PublicLayout';

function BackToKit() {
    return (
        <Link href="/ui-kit#layouts" className="text-primary text-sm font-semibold hover:underline">
            ← Back to UI kit
        </Link>
    );
}

function SampleContent({ heading }: { heading: string }) {
    return (
        <>
            <BackToKit />
            <h1 className="text-fg mt-3 text-2xl font-bold tracking-tight">{heading}</h1>
            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                <StatCard label="New matches" value="3" change="1 interview on Thursday" />
                <StatCard label="Course progress" value="64%" />
                <StatCard label="Orders" value="2" emphasis change="R1 840 this month" />
            </div>
            <Card className="mt-6 flex items-center gap-4">
                <ScoreRing score={9.4} />
                <div className="flex-1">
                    <CardTitle>Cashier - Mopani Fresh Market</CardTitle>
                    <p className="text-fg-muted text-sm">Giyani, 6 km away</p>
                </div>
                <Button variant="accent">Apply</Button>
            </Card>
        </>
    );
}

/** Each layout rendered with sample content (UI kit). */
export default function LayoutPreview({ layout }: { layout: string }) {
    const title = `${layout[0]?.toUpperCase()}${layout.slice(1)} layout`;

    switch (layout) {
        case 'auth':
            return (
                <AuthLayout
                    title="Create your free account"
                    description="We'll send a code to your phone. No email needed."
                >
                    <Head title={title} />
                    <Field label="Cellphone number">
                        <Input type="tel" placeholder="072 123 4567" />
                    </Field>
                    <Button block size="lg" className="mt-6">
                        Send my code
                    </Button>
                    <div className="mt-4 text-center">
                        <BackToKit />
                    </div>
                </AuthLayout>
            );
        case 'app':
            return (
                <AppLayout userName="Thandi Mabasa">
                    <Head title={title} />
                    <SampleContent heading="Molweni, Thandi" />
                </AppLayout>
            );
        case 'portal':
            return (
                <PortalLayout module="Work" userName="Thandi Mabasa">
                    <Head title={title} />
                    <SampleContent heading="Jobs for me" />
                </PortalLayout>
            );
        case 'console':
            return (
                <ConsoleLayout module="Admin" breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Overview' }]}>
                    <Head title={title} />
                    <SampleContent heading="National overview" />
                </ConsoleLayout>
            );
        case 'kiosk':
            return (
                <KioskLayout
                    hubName="Tsutsumani Digital Hub"
                    timeoutSeconds={45}
                    warningSeconds={15}
                    onTimeout={() => toast('Signed out for privacy')}
                >
                    <Head title={title} />
                    <BackToKit />
                    <h1 className="text-fg mt-3 text-3xl font-bold">Welcome to the hub</h1>
                    <p className="text-fg-muted mt-2">
                        Stay idle for 30 seconds to see the automatic sign-out warning.
                    </p>
                    <div className="mt-8 grid gap-4 sm:grid-cols-2">
                        <Button size="lg" className="min-h-20 text-lg">
                            Find a job
                        </Button>
                        <Button size="lg" variant="secondary" className="min-h-20 text-lg">
                            Take a course
                        </Button>
                    </div>
                </KioskLayout>
            );
        default:
            return (
                <PublicLayout
                    links={[
                        { label: 'Find jobs', href: '/jobs' },
                        { label: 'Courses', href: '/courses' },
                        { label: 'Our hubs', href: '/hubs' },
                    ]}
                >
                    <Head title={title} />
                    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                        <SampleContent heading="Public page" />
                    </div>
                </PublicLayout>
            );
    }
}
